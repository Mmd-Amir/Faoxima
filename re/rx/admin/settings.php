<?php

if ($datain == "settimecornremove" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['setdayremove'] . $setting['removedayc'] . "روز", $backadmin, 'HTML');
    step("getdaycron", $from_id);
} elseif ($user['step'] == "getdaycron") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "removedayc", $text);
} elseif ($text == "🌐 ثبت آدرس API ترنادو" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "urlpaymenttron", "select");
    $currentUrl = is_array($PaySetting) && isset($PaySetting['ValuePay']) ? $PaySetting['ValuePay'] : 'تنظیم نشده';
    $recommendedUrl = (defined('TRONADO_ORDER_TOKEN_ENDPOINTS') && isset(TRONADO_ORDER_TOKEN_ENDPOINTS[0]))
        ? TRONADO_ORDER_TOKEN_ENDPOINTS[0]
        : 'https://bot.tronado.cloud/api/v1/Order/GetOrderToken';
    $texttronseller = "🌐 آدرس API مورد استفاده برای اتصال به ترنادو را ارسال کنید.\n\nآدرس فعلی: {$currentUrl}\n\nℹ️ پیشنهاد ویژه برای ترنادو:\n{$recommendedUrl}";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('urlpaymenttron', $from_id);
} elseif ($user['step'] == "urlpaymenttron") {
    $submittedUrl = trim($text);
    $oldDomain = 'tronseller.storeddownloader.fun';
    if (stripos($submittedUrl, $oldDomain) !== false) {
        $warningMessage = "⚠️ دامنه قدیمی ترنادو هنوز استفاده می‌شود. لطفاً آدرس جدید را وارد کنید.";
        nm_adminInstantReply($from_id, $warningMessage, $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $submittedUrl, "NamePay", "urlpaymenttron");
    step('home', $from_id);
} elseif ($text == "✏️ ویرایش آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
    step("getnameforedite", $from_id);
} elseif ($user['step'] == "getnameforedite") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $helpedit, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step("home", $from_id);
} elseif ($text == "ویرایش نام" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نام جدید را ارسال کنید", $backadmin, 'HTML');
    step('changenamehelp', $from_id);
} elseif ($user['step'] == "changenamehelp") {
    if (strlen($text) >= 150) {
        nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    update("help", "name_os", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ نام آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "ویرایش دسته بندی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "دسته بندی جدید خود را ارسال کنید", $backadmin, 'HTML');
    step('changecategoryhelp', $from_id);
} elseif ($user['step'] == "changecategoryhelp") {
    if (strlen($text) >= 150) {
        nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    update("help", "category", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ نام دسته آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "ویرایش توضیحات" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "توضیحات جدید را ارسال کنید", $backadmin, 'HTML');
    step('changedeshelp', $from_id);
} elseif ($user['step'] == "changedeshelp") {
    update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ توضیحات  آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "ویرایش رسانه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "تصویر یا فیلم جدید را ارسال کنید", $backadmin, 'HTML');
    step('changemedia', $from_id);
} elseif ($user['step'] == "changemedia") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    }
    nm_adminInstantReply($from_id, "✅ توضیحات  آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "💰  غیرفعالسازی  نمایش شماره کارت") {
    nm_adminInstantReply($from_id, "برای تمامی کاربران غیرفعال گردید یا کاربران جدید؟
    کاربران جدید 0
    همه کاربران 1
    2 کاربران بجز نمایندگان", null, 'HTML');
    step('showcardallusers', $from_id);
} elseif ($user['step'] == "showcardallusers") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['disableshowcardstatus'], null, 'HTML');
    if (intval($text) == "1") {
        update("user", "cardpayment", "0");
        update("setting", "showcard", "0");
    } elseif (intval($text) == 2) {
        update("user", "cardpayment", "0", "agent", "f");
        update("setting", "showcard", "0");
    } else {
        update("setting", "showcard", "0");
    }
} elseif ($text == "💰 فعالسازی نمایش شماره کارت") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['activeshowcardstatus'], null, 'HTML');
    update("user", "cardpayment", "1");
    update("setting", "showcard", "1");
} elseif ($text == "🔋 روش تمدید سرویس" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $Methodextend, 'HTML');
    step('updateextendmethod', $from_id);
} elseif ($user['step'] == "updateextendmethod") {
    $aarayvalid = array(
        'ریست حجم و زمان',
        'اضافه شدن زمان و حجم به ماه بعد',
        'ریست زمان و اضافه کردن حجم قبلی',
        'ریست شدن حجم و اضافه شدن زمان',
        'اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده'
    );
    if (!in_array($text, $aarayvalid)) {
        nm_adminInstantReply($from_id, "❌ روش تمدید نامعتبر می باشد از لیست زیر روش تمدید درست را انتخاب کنید", null, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    update("marzban_panel", "Methodextend", $text, "name_panel", $panelName);
    update("user", "Processing_value", $panelName, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['Algortimeextend']['SaveData']);
    step('home', $from_id);
} elseif ($text == "♻️ تایید خودکار رسید" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    if ($paymentverify == "onauto") {
        nm_adminInstantReply($from_id, "❌ ابتدا تایید خودکار بدون بررسی را خاموش کنید.", null, 'HTML');
        return;
    }
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
    $card_Status_auto = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $PaySetting, 'callback_data' => $PaySetting],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['autoconfirmcard'], $card_Status_auto, 'HTML');
} elseif ($datain == "onautoconfirm" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "offautoconfirm", "NamePay", "statuscardautoconfirm");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffautoconfirmcard'], null);
} elseif ($datain == "offautoconfirm" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "onautoconfirm", "NamePay", "statuscardautoconfirm");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonautoconfirmcard'], null);
} elseif ($text == "/token") {
    $secret_key = select("admin", "*", "id_admin", $from_id, "select");
    $secret_key = base64_encode($secret_key['password']);
    nm_adminInstantReply($from_id, "<code>$secret_key</code>", null, 'HTML');
} elseif ($text == "/token2") {
    $token = bin2hex(random_bytes(16));
    file_put_contents('api/hash.txt', $token);
    nm_adminInstantReply($from_id, "توکن api شما : <code>$token</code>", null, 'HTML');
    sendDocument($from_id, 'api/documents.txt', "📌 داکیومنت api ربات
نکات :
۱ - در صورتی که به endpoint خاصی نیاز داشتید به اکانت پشتیبانی پیام دهید تا بررسی شود.");
} elseif ($text == "✅ فعالسازی پنل تحت وب" && $adminrulecheck['rule'] == "administrator") {
    $admin_select = select("admin", "*", "id_admin", $from_id, "select");
    $randomString = bin2hex(random_bytes(6));
    update("admin", "username", $from_id, "id_admin", $from_id);
    if ($admin_select['password'] == null) {
        update("admin", "password", $randomString, "id_admin", $from_id);
    } else {
        $randomString = $admin_select['password'];
    }
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تنظیم آیپی ورود", 'callback_data' => 'iploginset'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "✅  پنل تحت وب شما با موفقیت فعال گردید.


🔗آدرس ورود : https://$domainhosts/panel
👤نام کاربری :  <code>$from_id</code>
🔑رمز عبور :  <code>$randomString</code>", $keyboardstatistics, 'HTML');
} elseif (preg_match('/addordermanualـ(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['towstep'], $backadmin, 'HTML');
    step('getusernameconfig', $from_id);
} elseif ($user['step'] == "getusernameconfig") {
    $text = strtolower($text);
    if (!preg_match('/^\w{3,32}$/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['Invalidusername'], $backuser, 'html');
        return;
    }
    if (in_array($text, $usernameinvoice)) {
        nm_adminInstantReply($from_id, "❌ این نام کاربری از قبل داخل ربات وجود دارد.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['threestep'], $json_list_marzban_panel, 'HTML');
    step('getnamepanelconfig', $from_id);
} elseif ($user['step'] == "getnamepanelconfig") {
    update("user", "Processing_value_tow", $text, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['fourstep'], $json_list_product_list_admin, 'HTML');
    step('stependforaddorder', $from_id);
} elseif ($user['step'] == "stependforaddorder") {
    $sql = "SELECT * FROM product  WHERE name_product = :name_product AND (Location = :location OR Location = '/all') LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
    $stmt->bindParam(':location', $user['Processing_value_tow'], PDO::PARAM_STR);
    $stmt->execute();
    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_tow'], "select");
    $DataUserOut = $ManagePanel->DataUser($user['Processing_value_tow'], $user['Processing_value_one']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $datetimestep = strtotime("+" . $info_product['Service_time'] . "days");
        if ($info_product['Service_time'] == 0) {
            $datetimestep = 0;
        } else {
            $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
        }
        $datac = array(
            'expire' => $datetimestep,
            'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
            'from_id' => $user['Processing_value'],
            'username' => "",
            'type' => 'buy'
        );
        $DataUserOut = $ManagePanel->createUser($user['Processing_value_tow'], $info_product['code_product'], $user['Processing_value_one'], $datac);
        if ($DataUserOut['username'] == null) {
            nm_adminInstantReply($from_id, "❌ خطایی در ساخت اشتراک رخ داده است برای رفع مشکل علت خطا را در گروه گزارش تان بررسی کنید", null, 'HTML');
            $DataUserOut['msg'] = json_encode($DataUserOut['msg']);
            $texterros = "
خطا در ساخت کافنیگ از پنل ادمین
✍️ دلیل خطا :
{$DataUserOut['msg']}
آیدی ادمین : $from_id
نام پنل : {$marzban_list_get['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
                step("home", $from_id);
            }
            return;
        }
    } else {
        $DataUserOut['configs'] = $DataUserOut['links'];
    }
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status,notifctions) VALUES (:id_user, :id_invoice, :username, :time_sell, :Service_location, :name_product, :price_product, :Volume, :Service_time, :Status,:notifctions)");
    $Status = "active";
    $stmt->bindParam(':id_user', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(':id_invoice', $randomString, PDO::PARAM_STR);
    $stmt->bindParam(':username', $user['Processing_value_one'], PDO::PARAM_STR);
    $stmt->bindParam(':time_sell', $date, PDO::PARAM_STR);
    $stmt->bindParam(':Service_location', $user['Processing_value_tow'], PDO::PARAM_STR);
    $stmt->bindParam(':name_product', $info_product['name_product'], PDO::PARAM_STR);
    $stmt->bindParam(':price_product', $info_product['price_product'], PDO::PARAM_STR);
    $stmt->bindParam(':Volume', $info_product['Volume_constraint'], PDO::PARAM_STR);
    $stmt->bindParam(':Service_time', $info_product['Service_time'], PDO::PARAM_STR);
    $stmt->bindParam(':Status', $Status, PDO::PARAM_STR);
    $stmt->bindParam(':notifctions', $notifctions, PDO::PARAM_STR);
    $stmt->execute();
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $DataUserOut['subscription_url'] : "";
    $config = "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($DataUserOut['configs'])) {
        foreach ($DataUserOut['configs'] as $link) {
            $config .= "\n" . $link;
        }
    }
    $Shoppinginfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    $textcreatuser = str_replace('{username}', "<code>{$DataUserOut['username']}</code>", $datatextbot['textafterpay']);
    $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    if (intval($info_product['Volume_constraint']) == 0) {
        $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
    }
    if ($marzban_list_get['type'] == "Manualsale" || $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = str_replace('{password}', $DataUserOut['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $DataUserOut['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $DataUserOut['configs'], $output_config_link, $DataUserOut['username'], $Shoppinginfo, $textcreatuser, $randomString, $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['fivestep'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬇️ حداقل موجودی خرید عمده" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("shopSetting", "value", "Namevalue", "minbalancebuybulk", "select")['value'];
    $textmin = "📌 حداقل مبلغی که می خواهید کاربر  خرید انبوه کند را ارسال کنید.

مبلغ فعلی : $PaySetting";
    nm_adminInstantReply($from_id, $textmin, $backadmin, 'HTML');
    step('minbalancebulk', $from_id);
} elseif ($user['step'] == "minbalancebulk") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $shopkeyboard, 'HTML');
    update("shopSetting", "value", $text, "Namevalue", "minbalancebuybulk");
    step('home', $from_id);
} elseif (preg_match('/showcarduser-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    sendmessage($id_user, "💳 کاربر عزیز شماره کارت برای شما فعال شد هم اکنون می توانید خرید خود را انجام دهید.", null, 'HTML');
    nm_adminInstantReply($from_id, "✅  شماره کارت فعال گردید", null, 'HTML');
    update("user", "cardpayment", "1", "id", $id_user);
} elseif (preg_match('/carduserhide-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    nm_adminInstantReply($from_id, "✅  شماره کارت غیرفعال گردید", null, 'HTML');
    update("user", "cardpayment", "0", "id", $id_user);
} elseif ($text == "❌ حذف شماره کارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 شماره کارتی که می خواهید حذف کنید را ارسال نمایید.", $list_card_remove, 'HTML');
    step('getcardremove', $from_id);
} elseif ($user['step'] == "getcardremove") {
    $stmt = $pdo->prepare("DELETE FROM card_number WHERE cardnumber = :cardnumber");
    $stmt->bindParam(':cardnumber', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ شماره کارت با موفقیت حذف گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/rejectrequesta_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");

    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "درخواست مورد نظر یافت نشد.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }

    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected_status");
        $stmt->execute([
            ':status' => 'reject',
            ':type' => 'None',
            ':id' => $id_user,
            ':expected_status' => 'waiting',
        ]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                'show_alert' => true,
                'cache_time' => 5,
            ));
            return;
        }

        $stmtBalance = $pdo->prepare("UPDATE user SET Balance = Balance + :amount WHERE id = :id");
        $stmtBalance->execute([
            ':amount' => intval($setting['agentreqprice']),
            ':id' => $id_user,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $keyboardreject = json_encode([
        'inline_keyboard' => [
            [['text' => "✅درخواست رد شده.", 'callback_data' => "reject"]],
        ]
    ]);
    nm_adminInstantReply($from_id, "✅ درخواست با موفقیت رد گردید.", null, 'HTML');
    sendmessage($id_user, "❌ کاربر گرامی درخواست نمایندگی شما رد گردید.", null, 'HTML');
    $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textrequestagent .= "\nوضعیت: رد شد.";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "درخواست با موفقیت رد شد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif (preg_match('/addagentrequest_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");
    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "درخواست مورد نظر یافت نشد.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $defaultAgentType = 'n';
    $agentTypeLabels = [
        'n' => 'نماینده عادی',
        'n2' => 'نماینده پیشرفته',
    ];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected_status");
        $stmt->execute([
            ':status' => 'accept',
            ':type' => $defaultAgentType,
            ':id' => $id_user,
            ':expected_status' => 'waiting',
        ]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                'show_alert' => true,
                'cache_time' => 5,
            ));
            return;
        }

        $stmtUser = $pdo->prepare("UPDATE user SET agent = :agent, expire = NULL WHERE id = :id");
        $stmtUser->execute([
            ':agent' => $defaultAgentType,
            ':id' => $id_user,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    sendmessage($id_user, "✅ کاربر گرامی با درخواست نمایندگی شما موافقت و شما نماینده شدید.", null, 'HTML');
    nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
    $agentTypeButtons = [];
    foreach ($agentTypeLabels as $typeCode => $label) {
        $buttonText = ($typeCode === $defaultAgentType ? "✅ " : "") . $label;
        $agentTypeButtons[] = [
            'text' => $buttonText,
            'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
        ];
    }
    $keyboardreject = json_encode([
        'inline_keyboard' => [
            [['text' => "✅درخواست تایید شده.", 'callback_data' => "accept"]],
            $agentTypeButtons,
            [['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => 'expireset_' . $id_user]],
            [['text' => "مدیریت کاربر", 'callback_data' => 'manageuser_' . $id_user]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textrequestagent .= "\nوضعیت: تایید شد ({$agentTypeLabels[$defaultAgentType]})";
    $textrequestagent .= "\nبرای تغییر نوع نماینده از دکمه‌های زیر استفاده کنید.";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "درخواست تایید شد و نماینده عادی فعال شد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif (preg_match('/^setagenttype_(n|n2)_(\w+)/', $datain, $datagetr)) {
    $selectedType = $datagetr[1];
    $id_user = $datagetr[2];
    $agentTypeLabels = [
        'n' => 'نماینده عادی',
        'n2' => 'نماینده پیشرفته',
    ];
    if (!array_key_exists($selectedType, $agentTypeLabels)) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['agent']['invalidtypeagent'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "agent", $selectedType, "id", $id_user);
    update("Requestagent", "type", $selectedType, "id", $id_user);
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");
    if ($request_agent) {
        $agentTypeButtons = [];
        foreach ($agentTypeLabels as $typeCode => $label) {
            $buttonText = ($typeCode === $selectedType ? "✅ " : "") . $label;
            $agentTypeButtons[] = [
                'text' => $buttonText,
                'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
            ];
        }
        $keyboardreject = json_encode([
            'inline_keyboard' => [
                [['text' => "✅درخواست تایید شده.", 'callback_data' => "accept"]],
                $agentTypeButtons,
                [['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => 'expireset_' . $id_user]],
                [['text' => "مدیریت کاربر", 'callback_data' => 'manageuser_' . $id_user]]
            ]
        ], JSON_UNESCAPED_UNICODE);
        $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
        $textrequestagent .= "\nوضعیت: تایید شد ({$agentTypeLabels[$selectedType]})";
        $textrequestagent .= "\nبرای تغییر نوع نماینده از دکمه‌های زیر استفاده کنید.";
        Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    }
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "نوع نماینده به {$agentTypeLabels[$selectedType]} تغییر کرد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif ($datain == "iranpay2setting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $trnado, 'HTML');
} elseif ($datain == "iranpay3setting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $iranpaykeyboard, 'HTML');
} elseif ($text == "وضعیت  درگاه ترونادو" && $adminrulecheck['rule'] == "administrator") {
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    $textternado = "در این بخش می توانید درگاه ترنادو را خاموش یا روشن کنید";
    nm_adminInstantReply($from_id, $textternado, $statusternadoo, 'HTML');
} elseif ($datain == "onternado") {
    update("PaySetting", "ValuePay", "offternado", "NamePay", "statustarnado");
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "خاموش گردید", $statusternadoo);
} elseif ($datain == "offternado") {
    update("PaySetting", "ValuePay", "onternado", "NamePay", "statustarnado");
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن گردید", $statusternadoo);
} elseif ($text == "🔑 ثبت API Key ترنادو" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiternado", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $texttronseller = "🔑 کلید API ترنادو خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('apiternado', $from_id);
} elseif ($user['step'] == "apiternado") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiternado");
    step('home', $from_id);
} elseif ($datain == "affilnecurrencysetting") {
    nm_adminInstantReply($from_id, "یک گزینه را انتخاب کنید", $tronnowpayments, 'HTML');
} elseif ($text == "🗂 نام درگاه کارت به کارت") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("getnamecarttocart", $from_id);
} elseif ($user['step'] == "getnamecarttocart") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $CartManage, 'HTML');
    update("textbot", "text", $text, "id_text", "carttocart");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه nowpayment") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("getnamenowpayment", $from_id);
} elseif ($user['step'] == "getnamenowpayment") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
    update("textbot", "text", $text, "id_text", "textsnowpayment");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ریالی بدون احراز") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("getnamecarttopaynotverify", $from_id);
} elseif ($user['step'] == "getnamecarttopaynotverify") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $CartManage, 'HTML');
    update("textbot", "text", $text, "id_text", "textpaymentnotverify");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه   plisio") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextnowpayment", $from_id);
} elseif ($user['step'] == "gettextnowpayment") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $NowPaymentsManage, 'HTML');
    update("textbot", "text", $text, "id_text", "textnowpayment");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه رمز ارز آفلاین") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextnowpaymentTRON", $from_id);
} elseif ($user['step'] == "gettextnowpaymentTRON") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $tronnowpayments, 'HTML');
    update("textbot", "text", $text, "id_text", "textnowpaymenttron");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ارزی ریالی") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextiranpay2", $from_id);
} elseif ($user['step'] == "gettextiranpay2") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $Swapinokey, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay2");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه استار") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextstartelegram", $from_id);
} elseif ($user['step'] == "gettextstartelegram") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $Swapinokey, 'HTML');
    update("textbot", "text", $text, "id_text", "text_star_telegram");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه ترنادو") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه ترنادو را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextiranpay3", $from_id);
} elseif ($user['step'] == "gettextiranpay3") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $trnado, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay3");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ارزی ریالی سوم") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextiranpay1", $from_id);
} elseif ($user['step'] == "gettextiranpay1") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $iranpaykeyboard, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay1");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه آقای پرداخت") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextaqayepardakht", $from_id);
} elseif ($user['step'] == "gettextaqayepardakht") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $aqayepardakht, 'HTML');
    update("textbot", "text", $text, "id_text", "aqayepardakht");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه زرین پال") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextzarinpal", $from_id);
} elseif ($user['step'] == "gettextzarinpal") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $keyboardzarinpal, 'HTML');
    update("textbot", "text", $text, "id_text", "zarinpal");
    step("home", $from_id);
} elseif ($text == "⚙️  اینباند اکانت غیرفعال" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocoldisable', $from_id);
} elseif ($user['step'] == "getprotocoldisable") {
    global $json_list_marzban_panel_inbounds;
    $protocol = ["vless", "vmess", "trojan", "shadowsocks"];
    if (!in_array($text, $protocol)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['invalidprotocol'], null, 'HTML');
        return;
    }
    $getinbounds = getinbounds($user['Processing_value'])[$text];
    $list_marzban_panel_inbounds = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($getinbounds as $button) {
        $list_marzban_panel_inbounds['keyboard'][] = [
            ['text' => $button['tag']]
        ];
    }
    $list_marzban_panel_inbounds['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    $json_list_marzban_panel_inbounds = json_encode($list_marzban_panel_inbounds);
    update("user", "Processing_value_one", $text, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['getInbound'], $json_list_marzban_panel_inbounds, 'HTML');
    step('getInbounddisable', $from_id);
} elseif ($user['step'] == "getInbounddisable") {
    nm_adminInstantReply($from_id, "نام اینباند با موفقیت ذخیره گردید", $optionMarzban, 'HTML');
    $textpro = "{$user['Processing_value_one']}*$text";
    update("marzban_panel", "inbound_deactive", $textpro, "name_panel", $user['Processing_value']);
    step("home", $from_id);
} elseif ($text == "🗑 بهینه سازی ربات" && $adminrulecheck['rule'] == "administrator") {
    $textoptimize = "❌❌❌❌❌❌❌ متن زیر را با دقت بخوانید

📌 با تایید گزینه زیر عملیات زیر انجام خواهد شد. و قابل بازگشت نیستند

1 - سفارش های غیرفعال حذف خواهند شد
2 - سفارش های پرداخت نشده حذف خواهند شد.
3 - سفارش های حذف شده توسط ادمین
4 - حذف سرویس های تست غیرفعال
5 - سفارش های حذف شده توسط کاربر
6 - سفارشاتی که زمان یا حجم شان تمام شده باشد
7 - فاکتورهای قدیمی پرداخت نشده (بیش از ۳۰ روز)

🛡 اگر در عملکرد ربات با باگ یا مشکلی مواجه شدید، از طریق گیت هاب یا گروه فاکسیما اطلاع رسانی کنید
<a href=\"https://github.com/Mmd-Amir/Faoxima\">لینک گیت هاب</a>";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید و  بهینه سازی", 'callback_data' => 'optimizebot'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textoptimize, $Response, 'HTML');
} elseif ($text == "💀 بازنشانی ربات" && $adminrulecheck['rule'] == "administrator") {
    global $adminnumber;
    $mainAdminId = trim((string) ($adminnumber ?? ''));
    $currentUserId = trim((string) $from_id);
    if ($mainAdminId !== '' && $currentUserId !== $mainAdminId) {
        nm_adminInstantReply($from_id, "⚠️ فقط ادمین اصلی می‌تواند این بخش را مشاهده کند.", null, 'HTML');
        return;
    }
    $resetWarning = "⚠️ هشدار مهم\n\nبا تایید بازنشانی، تمامی جداول پایگاه داده حذف و مجدداً ساخته خواهند شد. این عملیات غیرقابل بازگشت است.\n\nآیا از انجام این کار مطمئن هستید؟";
    $resetKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ بله، مطمئن هستم", 'callback_data' => 'resetbot_confirm'],
                ['text' => "❌ خیر", 'callback_data' => 'resetbot_cancel'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, $resetWarning, $resetKeyboard, 'HTML');
} elseif ($datain == "resetbot_cancel") {
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "عملیات لغو شد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
    Editmessagetext($from_id, $message_id, "❌ عملیات بازنشانی لغو شد.", null);
} elseif ($datain == "resetbot_confirm" && $adminrulecheck['rule'] == "administrator") {
    global $pdo, $domainhosts, $adminnumber;
    $mainAdminId = trim((string) ($adminnumber ?? ''));
    $currentUserId = trim((string) $from_id);
    if ($mainAdminId !== '' && $currentUserId !== $mainAdminId) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "❌ شما اجازه انجام این عملیات را ندارید.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "⏳ در حال بازنشانی...",
        'show_alert' => false,
        'cache_time' => 5,
    ));
    Editmessagetext($from_id, $message_id, "⏳ عملیات بازنشانی ربات آغاز شد. لطفاً منتظر بمانید...", null);

    $dropError = null;
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($tables)) {
            foreach ($tables as $tableName) {
                $tableName = trim($tableName);
                if ($tableName !== '') {
                    $pdo->exec("DROP TABLE IF EXISTS `{$tableName}`;");
                }
            }
        }
    } catch (Throwable $exception) {
        $dropError = $exception;
    } finally {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        } catch (Throwable $ignored) {
        }
    }

    if ($dropError !== null) {
        file_put_contents(REFACTORED_LEGACY_ROOT . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] DROP ERROR: " . $dropError->getMessage() . PHP_EOL, FILE_APPEND);
        Editmessagetext($from_id, $message_id, "❌ خطا در حذف جداول. لطفاً فایل resetbot_error.log را بررسی کنید.", null);
        nm_adminInstantReply($from_id, "❌ عملیات بازنشانی به دلیل خطا در حذف جداول متوقف شد.", null, 'HTML');
        return;
    }

    $resetUrlUsed = '';
    $reinstallSuccess = false;
    $installerErrors = [];
    $candidateUrls = [];
    $normalizedHost = '';

    if (!empty($domainhosts)) {
        $normalizedHost = rtrim($domainhosts, '/');
        $candidateUrls[] = "https://{$normalizedHost}/table.php";
        $candidateUrls[] = "http://{$normalizedHost}/table.php";
    }

    $attemptInstallerRequest = function (string $url) use (&$resetUrlUsed, &$reinstallSuccess, &$installerErrors) {
        if ($reinstallSuccess || $url === '') {
            return;
        }

        $response = false;
        $httpCode = null;

        if (function_exists('curl_init')) {
            $curlHandle = @curl_init($url);
            if ($curlHandle !== false) {
                curl_setopt_array($curlHandle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ]);
                $response = curl_exec($curlHandle);
                if ($response === false) {
                    $installerErrors[] = 'cURL error: ' . curl_error($curlHandle) . " ({$url})";
                } else {
                    $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
                }
                curl_close($curlHandle);
            }
        }

        if ($response === false) {
            $streamContext = stream_context_create([
                'http' => [
                    'timeout' => 20,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $response = @file_get_contents($url, false, $streamContext);
            if ($response === false) {
                $installerErrors[] = 'stream error: unable to fetch ' . $url;
            } else {
                $httpCode = 200;
            }
        }

        if ($response !== false && ($httpCode === null || ($httpCode >= 200 && $httpCode < 400))) {
            $resetUrlUsed = $url;
            $reinstallSuccess = true;
        }
    };

    foreach ($candidateUrls as $candidateUrl) {
        $attemptInstallerRequest($candidateUrl);
        if ($reinstallSuccess) {
            break;
        }
    }

    if (!$reinstallSuccess) {
        $localTablePath = REFACTORED_LEGACY_ROOT . '/table.php';
        if (is_file($localTablePath)) {
            try {
                include $localTablePath;
                $reinstallSuccess = true;
                $resetUrlUsed = 'local include';
            } catch (Throwable $tableError) {
                $installerErrors[] = 'local table include: ' . $tableError->getMessage();
                file_put_contents(REFACTORED_LEGACY_ROOT . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] TABLE ERROR: " . $tableError->getMessage() . PHP_EOL, FILE_APPEND);
                Editmessagetext($from_id, $message_id, "⚠️ جداول حذف شدند اما اجرای table.php با خطا مواجه شد.", null);
                nm_adminInstantReply($from_id, "⚠️ اجرای table.php با خطا مواجه شد. لطفاً فایل resetbot_error.log را بررسی کنید.", null, 'HTML');
                return;
            }
        }
    }

    if ($reinstallSuccess) {
        $successMessage = "✅ بازنشانی ربات با موفقیت انجام شد." . (!empty($resetUrlUsed) ? "\nمنبع اجرا: {$resetUrlUsed}" : '');
        Editmessagetext($from_id, $message_id, $successMessage, null);
        nm_adminInstantReply($from_id, "✅ عملیات بازنشانی ربات با موفقیت انجام شد.", null, 'HTML');
    } else {
        if (!empty($installerErrors)) {
            file_put_contents(REFACTORED_LEGACY_ROOT . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] INSTALL ERROR: " . implode(' | ', $installerErrors) . PHP_EOL, FILE_APPEND);
        }
        $manualUrlHint = !empty($normalizedHost) ? "لطفاً لینک https://{$normalizedHost}/table.php را به صورت دستی باز کنید." : "لطفاً فایل table.php را به صورت دستی اجرا کنید.";
        $warningText = "⚠️ جداول حذف شدند اما اجرای table.php انجام نشد. {$manualUrlHint}";
        Editmessagetext($from_id, $message_id, $warningText, null);
        nm_adminInstantReply($from_id, $warningText, null, 'HTML');
    }
} elseif ($datain == "optimizebot") {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $countunpiadorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $countdisableorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE (Status = 'removebyadmin' OR Status = 'removedbyadmin')");
    $stmt->execute();
    $countremoveadminorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $countdisableordtester = (int)$stmt->fetchColumn();

    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $countoldunpaid = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE payment_Status IN ('expire','reject')");
    $stmt->execute();
    $countpayexpired = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyadmin'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removedbyadmin'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removeTime'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removevolume'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyuser'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'unpaid' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE payment_Status IN ('expire','reject')");
    $stmt->execute();

    $optimizebot = "✅ بهینه سازی با موفقیت انجام شد

📊 خلاصه عملیات:
✅ {$countunpiadorder} سفارش پرداخت نشده حذف گردید
✅ {$countdisableorder} سفارش غیرفعال حذف گردید
✅ {$countremoveadminorder} سفارش حذف شده توسط ادمین پاک گردید
✅ {$countdisableordtester} سرویس تست غیرفعال حذف گردید
✅ {$countoldunpaid} فاکتور تست پرداخت نشده پاک گردید
✅ {$countpayexpired} گزارش تراکنش منقضی/رد شده پاک گردید";

    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $optimizebot, null);
    }
    nm_adminInstantReply($from_id, $optimizebot, $setting_panel, 'HTML');

    $time = time();
    $logss = "optimize_{$countunpiadorder}_{$countdisableorder}_{$countremoveadminorder}_{$countdisableordtester}_$time";
    file_put_contents('log.txt', "\n" . $logss, FILE_APPEND);
} elseif ($datain == "settimecornvolume") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تنظیم کنید که اگر حجم کاربر به x رسید پیام اخطار ارسال شود. حجم را براساس گیگ ارسال نمایید.", $backadmin, 'HTML');
    step("getvolumewarn", $from_id);
} elseif ($user['step'] == "getvolumewarn") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    update("setting", "volumewarn", $text);
    nm_adminInstantReply($from_id, "✅ تغییرات با موفقیت ذخیره شد", $setting_panel, 'HTML');
    step("home", $from_id);
} elseif ($text == "🔧 ساخت کانفیگ دستی") {
    savedata("clear", "idpanel", $user['Processing_value']);
    nm_adminInstantReply($from_id, "📌در این بخش میتوانید یک سفارش را بطور دستی ایجاد و دریافت کنید
⚠️ در صورتی که می خواهید  کانفیگ به حساب کاربر اضافه شود و کاربر مدیریت کند باید از گزینه افزودن سفارش  استفاده نمایید.
- برای اضافه کردن کانفیگ ابتدا نام کاربری را ارسال نمایید.", $backadmin, 'HTML');
    step('getusernameconfigcr', $from_id);
} elseif ($user['step'] == "getusernameconfigcr") {
    if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['invalidusername'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    step('getcountcreate', $from_id);
    nm_adminInstantReply($from_id, "📌 تعداد کانفیگی که میخواهید ساخته شود را ارسال کنید حداکثر ۱۰ تا می توانید ارسال کنید", $backadmin, 'HTML');
} elseif ($user['step'] == "getcountcreate") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) > 10 or intval($text) < 0) {
        nm_adminInstantReply($from_id, "❌ حداقل ۱ عدد و حداکثر می توانید ۱۰ عدد ارسال کنید.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "count", $text);
    step('getvolumesconfig', $from_id);
    nm_adminInstantReply($from_id, "📌 حجم مصرفی اکانت را ارسال نمایید . حجم براساس گیگابایت است.", $backadmin, 'HTML');
} elseif ($user['step'] == "getvolumesconfig") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    update("user", "Processing_value_tow", $text, "id", $from_id);
    nm_adminInstantReply($from_id, "📌 زمان سرویس را ارسال نمایید زمان براساس روز است.", $backadmin, 'HTML');
    step("gettimeaccount", $from_id);
} elseif ($user['step'] == "gettimeaccount") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    if (intval($text) == 0) {
        $expire = 0;
    } else {
        $datetimestep = strtotime("+" . $text . "days");
        $expire = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }
    $datac = array(
        'expire' => $expire,
        'data_limit' => $user['Processing_value_tow'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => "$username",
        'type' => "new by admin $from_id"
    );
    $panel = select("marzban_panel", "*", "name_panel", $userdata['idpanel'], "select");
    for ($i = 0; $i < $userdata['count']; $i++) {
        $usernameconfig = $user['Processing_value_one'] . "_" . $i;
        $dataoutput = $ManagePanel->createUser($userdata['idpanel'], "usertest", $usernameconfig, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            nm_adminInstantReply($from_id, $textbotlang['users']['sell']['ErrorConfig'], null, 'HTML');
            $texterros = "
⭕️ یک کاربر قصد دریافت اکانت داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$panel['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
                step("home", $from_id);
            }
            return;
        }
        $randomString = bin2hex(random_bytes(5));
        $output_config_link = $panel['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
        $config = "";
        if ($panel['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $link) {
                $config .= "\n" . $link;
            }
        }
        $datatextbot['textafterpay'] = $panel['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $panel['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $panel['type'] == "ibsng" || $panel['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
        if (intval($text) == 0)
            $text = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', "پلن دلخواه", $textcreatuser);
        $textcreatuser = str_replace('{location}', $panel['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $text, $textcreatuser);
        $textcreatuser = str_replace('{volume}', $user['Processing_value_tow'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        if ($panel['type'] == "Manualsale" || $panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
        }
        sendMessageService($panel, $dataoutput['configs'], $output_config_link, $dataoutput['username'], null, $textcreatuser, $randomString);
    }
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
    $text_report = "";
    if (strlen($setting['Channel_Report']) > 0) {
        $text_report = " 🛍 ساخت کانفیگ توسط ادمین

نام کاربری کانفیگ : {$user['Processing_value_one']}
حجم کانفیگ  : {$user['Processing_value_tow']} گیگ
زمان کانفیگ : $text روز
آیدی عددی ادمین : $from_id
نام کاربری ادمین : $username
تعداد ساخت : {$userdata['count']}";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("user", "Processing_value", $userdata['idpanel'], "id", $from_id);
    step("home", $from_id);
} elseif ($text == "🛠 قابلیت های پنل") {
    nm_adminInstantReply($from_id, "🪚 برای استفاده از این قابلیت یکی از پنل های زیر را انتخاب نمایید", $json_list_marzban_panel, 'HTML');
    step('getlocoption', $from_id);
} elseif ($user['step'] == "getlocoption") {
    update("user", "Processing_value", $text, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $text, "select")['type'];
    if ($typepanel == "marzban") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
    } elseif ($typepanel == "x-ui_single") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "hiddify") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "alireza") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "alireza_single") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "marzneshin") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "WGDashboard") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    }
    step("home", $from_id);
} elseif ($text == "🖥 مدیریت نود ها" || $datain == "bakcnode") {
    if ($adminnumber != $from_id) {
        nm_adminInstantReply($from_id, "❌ این بخش فقط در دسترس ادمین اصلی است", null, 'HTML');
        return;
    }
    $nodes = Get_Nodes($user['Processing_value']);
    if (!empty($nodes['error'])) {
        nm_adminInstantReply($from_id, $nodes['error'], null, 'HTML');
        return;
    }
    if (!empty($nodes['status']) && $nodes['status'] != 200) {
        nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$nodes['status']}", null, 'HTML');
        return;
    }
    $nodes = json_decode($nodes['body'], true);
    if (count($nodes) == 0) {
        nm_adminInstantReply($from_id, "❌  امکان مشاهده تنظیمات نود ها وجود ندارد", null, 'HTML');
        return;
    }
    $keyboardlistsnode['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "actionnode"],
        ['text' => "نام", 'callback_data' => "namenode"]
    ];
    foreach ($nodes as $result) {
        if (!isset($result['id']))
            continue;
        $keyboardlistsnode['inline_keyboard'][] = [
            ['text' => "مدیریت", 'callback_data' => "node_{$result['id']}"],
            ['text' => $result['name'], 'callback_data' => "node_{$result['id']}"],
        ];
    }
    $keyboardlistsnode = json_encode($keyboardlistsnode);
    if ($datain == "bakcnode") {
        Editmessagetext($from_id, $message_id, "📌 در این بخش می توانید نود های پنل مرزبان مدیریت کنید.", $keyboardlistsnode);
    } else {
        nm_adminInstantReply($from_id, "📌 در این بخش می توانید نود های پنل مرزبان مدیریت کنید.", $keyboardlistsnode, 'HTML');
    }
} elseif (preg_match('/^node_(.*)/', $datain, $dataget)) {
    $nodeid = $dataget[1];
    update("user", "Processing_value_one", $nodeid, "id", $from_id);
    $node = Get_Node($user['Processing_value'], $nodeid);
    if (!empty($node['error'])) {
        nm_adminInstantReply($from_id, $node['error'], null, 'HTML');
        return;
    }
    if (!empty($node['status']) && $node['status'] != 200) {
        nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$node['status']}", null, 'HTML');
        return;
    }
    $nodeusage = Get_usage_Nodes($user['Processing_value']);
    if (!empty($nodeusage['error'])) {
        nm_adminInstantReply($from_id, $nodeusage['error'], null, 'HTML');
        return;
    }
    if (!empty($nodeusage['status']) && $nodeusage['status'] != 200) {
        nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$nodeusage['status']}", null, 'HTML');
        return;
    }
    $node = json_decode($node['body'], true);
    $nodeusage = json_decode($nodeusage['body'], true);
    foreach ($nodeusage['usages'] as $nodeusages) {
        if ($nodeusages['node_id'] == $nodeid) {
            $nodeusage = $nodeusages;
            break;
        }
    }
    $sumvolume = formatBytes($nodeusage['downlink'] + $nodeusage['uplink']);
    $textnode = "📌 اطلاعات نود

🖥 نام نود :  {$node['name']}
🌍 آیپی نود : {$node['address']}
🔻 پورت نود : {$node['port']}
🔺 پورت api نود : {$node['api_port']}
🔋جمع مصرف نود  : $sumvolume
🔄 ضریب مصرف نود : {$node['usage_coefficient']}
🔵 نسخه xray نود : {$node['xray_version']}
🟢 وضعیت نود : {$node['status']}
    ";
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🗂 تغییر نام نود", 'callback_data' => "changenamenode"],
                ['text' => "🔄 تغییر ضریب مصرف نود", 'callback_data' => "changecoefficient"],
            ],
            [
                ['text' => "🌍 تغییر آدرس ایپی نود", 'callback_data' => "changeipnode"],
                ['text' => "♻️ اتصال مجدد نود", 'callback_data' => "reconnectnode"],
            ],
            [
                ['text' => "❌ حذف نود", 'callback_data' => "removenode"],
            ],
            [
                ['text' => "🔙 بازگشت به لیست نود ها", 'callback_data' => "bakcnode"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($datain == "changecoefficient") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 ضریب مصرف نودتان را ارسال نمایید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getusage_coefficient", $from_id);
} elseif ($user['step'] == "getusage_coefficient") {
    $config = array(
        'usage_coefficient' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    nm_adminInstantReply($from_id, "✅ ضریب مصرف نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "changenamenode") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 نام نودتان را ارسال نمانیید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getnamenode", $from_id);
} elseif ($user['step'] == "getnamenode") {
    $config = array(
        'name' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    nm_adminInstantReply($from_id, "✅  نام نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "changeipnode") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 آیپی نود را ارسال نمانیید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getipnodeset", $from_id);
} elseif ($user['step'] == "getipnodeset") {
    $config = array(
        'address' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    nm_adminInstantReply($from_id, "✅  آدرس نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "reconnectnode") {
    reconnect_node($user['Processing_value'], $user['Processing_value_one']);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "✅ اتصال مجدد نود انجام گردید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($datain == "removenode") {
    removenode($user['Processing_value'], $user['Processing_value_one']);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "bakcnode"],
            ]
        ]
    ]);
    $textnode = "✅ نود با موفقیت حذف گردید";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($text == "💎 مالی" && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_finance', $from_id);
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    if ($arzireyali1 != "onSwapinoBot" && $arzireyali1 != "offSwapinoBot") {
        update("PaySetting", "ValuePay", "onSwapinoBot", "NamePay", "statusSwapWallet");
        $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    }
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $zarinpey = getPaySettingValue('zarinpeystatus', 'offzarinpey');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $paymentstatussnotverify = getPaySettingValue('paymentstatussnotverify', 'offpaymentstatus');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');
    $cartotcartstatus = [
        'oncard' => $textbotlang['Admin']['Status']['statuson'],
        'offcard' => $textbotlang['Admin']['Status']['statusoff']
    ][$cartotcart];
    $plisiostatus = [
        'onnowpayment' => $textbotlang['Admin']['Status']['statuson'],
        'offnowpayment' => $textbotlang['Admin']['Status']['statusoff']
    ][$plisio];
    $arzireyali1status = [
        'onSwapinoBot' => $textbotlang['Admin']['Status']['statuson'],
        'offSwapinoBot' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali1];
    $arzireyali2status = [
        'onternado' => $textbotlang['Admin']['Status']['statuson'],
        'offternado' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali2];
    $aqayepardakhtstatus = [
        'onaqayepardakht' => $textbotlang['Admin']['Status']['statuson'],
        'offaqayepardakht' => $textbotlang['Admin']['Status']['statusoff']
    ][$aqayepardakht];
    $zarinpalstatus = [
        'onzarinpal' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpal' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpal];
    $zarinpeystatus = [
        'onzarinpey' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpey' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpey];
    $affilnecurrencystatus = [
        'ondigi' => $textbotlang['Admin']['Status']['statuson'],
        'offdigi' => $textbotlang['Admin']['Status']['statusoff']
    ][$affilnecurrency];
    $arzireyali3text = [
        'oniranpay3' => $textbotlang['Admin']['Status']['statuson'],
        'offiranpay3' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali3];
    $paymentstar = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$paymentsstartelegram];
    $now_payment_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_nowpayment];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "عملیات", 'callback_data' => "actions"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "cartsetting"],
                ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
                ['text' => "🔌 کارت به کارت", 'callback_data' => "carttocart"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "plisiosetting"],
                ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
                ['text' => "📌 plisio", 'callback_data' => "plisio"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "nowpaymentsetting"],
                ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
                ['text' => "📌 nowpayment", 'callback_data' => "nowpayment"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay1setting"],
                ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
                ['text' => "📌 ارزی ریالی اول", 'callback_data' => "arzireyali1"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay2setting"],
                ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
                ['text' => "📌 ارزی ریالی دوم", 'callback_data' => "arzireyali2"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay3setting"],
                ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
                ['text' => "📌ارزی ریالی سوم", 'callback_data' => "oniranpay3"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpeysetting"],
                ['text' => $zarinpeystatus, 'callback_data' => "editpayment-zarinpey-$zarinpey"],
                ['text' => "🟠 زرین پی", 'callback_data' => "zarinpey"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "aqayepardakhtsetting"],
                ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
                ['text' => "🔵 آقای پرداخت", 'callback_data' => "aqayepardakht"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpalsetting"],
                ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
                ['text' => "🟡 زرین پال", 'callback_data' => "zarinpal"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "affilnecurrencysetting"],
                ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
                ['text' => "💵ارزی آفلاین", 'callback_data' => "affilnecurrency"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "startelegram"],
                ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
                ['text' => "💫Star Telegram", 'callback_data' => "none"],
            ],
            [
                ['text' => "⬆️ حداکثر شارژ موجودی", 'callback_data' => "maxbalanceaccount"],
                ['text' => "⬇️ حداقل شارژ موجودی", 'callback_data' => "mainbalanceaccount"],
            ],
            [
                ['text' => "💼 آدرس ولت", 'callback_data' => "walletaddress"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم فاکسیما هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status, 'HTML');
} elseif ($text == "🎁 کش بک تمدید" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 مقدار درصدی که می خواهید حساب کاربر بعد از تمدید به عنوان هدیه شارژ شود را ارسال کنید.
⚠️ در صورتی که میخواهید غیرفعال باشد عدد 0 را ارسال کنید", $backadmin, 'HTML');
    step('getpricecashback', $from_id);
} elseif ($user['step'] == "getpricecashback") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "price_cashback", $text);
    nm_adminInstantReply($from_id, "📌 نوع کاربری را انتخاب نمایید
f
n
n2", $backadmin, 'HTML');
    step('getagent', $from_id);
} elseif ($user['step'] == "getagent") {
    if (!in_array($text, ['f', 'n', 'n2'])) {
        nm_adminInstantReply($from_id, "❌ گروه کاربری نامعتبر است", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if ($text == "f") {
        update("shopSetting", "value", $userdata['price_cashback'], "Namevalue", "chashbackextend");
    } else {
        $shop_cashbackagent = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent")['value'], true);
        $shop_cashbackagent[$text] = $userdata['price_cashback'];
        update("shopSetting", "value", json_encode($shop_cashbackagent), "Namevalue", "chashbackextend_agent");
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت تنظیم شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^editpayment-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "Cartstatus") {
        if ($value == "oncard") {
            $valuenew = "offcard";
        } else {
            $valuenew = "oncard";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "Cartstatus");
    } elseif ($type == "plisio") {
        if ($value == "onnowpayment") {
            $valuenew = "offnowpayment";
        } else {
            $valuenew = "onnowpayment";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "nowpaymentstatus");
    } elseif ($type == "arzireyali1") {
        if ($value == "onSwapinoBot") {
            $valuenew = "offSwapinoBot";
        } else {
            $valuenew = "onSwapinoBot";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusSwapWallet");
    } elseif ($type == "arzireyali2") {
        if ($value == "onternado") {
            $valuenew = "offternado";
        } else {
            $valuenew = "onternado";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustarnado");
    } elseif ($type == "aqayepardakht") {
        if ($value == "onaqayepardakht") {
            $valuenew = "offaqayepardakht";
        } else {
            $valuenew = "onaqayepardakht";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusaqayepardakht");
    } elseif ($type == "zarinpey") {
        if ($value == "onzarinpey") {
            $valuenew = "offzarinpey";
        } else {
            $valuenew = "onzarinpey";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "zarinpeystatus");
    } elseif ($type == "zarinpal") {
        if ($value == "onzarinpal") {
            $valuenew = "offzarinpal";
        } else {
            $valuenew = "onzarinpal";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "zarinpalstatus");
    } elseif ($type == "affilnecurrency") {
        if ($value == "ondigi") {
            $valuenew = "offdigi";
        } else {
            $valuenew = "ondigi";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "digistatus");
    } elseif ($type == "oniranpay3") {
        if ($value == "oniranpay3") {
            $valuenew = "offiranpay3";
        } else {
            $valuenew = "oniranpay3";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusiranpay3");
    } elseif ($type == "startelegram") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusstar");
    } elseif ($type == "nowpayment") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusnowpayment");
    }
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $zarinpey = getPaySettingValue('zarinpeystatus', 'offzarinpey');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $paymentstatussnotverify = getPaySettingValue('paymentstatussnotverify', 'offpaymentstatus');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');
    $cartotcartstatus = [
        'oncard' => $textbotlang['Admin']['Status']['statuson'],
        'offcard' => $textbotlang['Admin']['Status']['statusoff']
    ][$cartotcart];
    $plisiostatus = [
        'onnowpayment' => $textbotlang['Admin']['Status']['statuson'],
        'offnowpayment' => $textbotlang['Admin']['Status']['statusoff']
    ][$plisio];
    $arzireyali1status = [
        'onSwapinoBot' => $textbotlang['Admin']['Status']['statuson'],
        'offSwapinoBot' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali1];
    $arzireyali2status = [
        'onternado' => $textbotlang['Admin']['Status']['statuson'],
        'offternado' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali2];
    $aqayepardakhtstatus = [
        'onaqayepardakht' => $textbotlang['Admin']['Status']['statuson'],
        'offaqayepardakht' => $textbotlang['Admin']['Status']['statusoff']
    ][$aqayepardakht];
    $zarinpeystatus = [
        'onzarinpey' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpey' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpey];
    $zarinpalstatus = [
        'onzarinpal' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpal' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpal];
    $affilnecurrencystatus = [
        'ondigi' => $textbotlang['Admin']['Status']['statuson'],
        'offdigi' => $textbotlang['Admin']['Status']['statusoff']
    ][$affilnecurrency];
    $arzireyali3text = [
        'oniranpay3' => $textbotlang['Admin']['Status']['statuson'],
        'offiranpay3' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali3];
    $paymentstar = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$paymentsstartelegram];
    $now_payment_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_nowpayment];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "عملیات", 'callback_data' => "actions"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "cartsetting"],
                ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
                ['text' => "🔌 کارت به کارت", 'callback_data' => "carttocart"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "plisiosetting"],
                ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
                ['text' => "📌 plisio", 'callback_data' => "plisio"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "nowpaymentsetting"],
                ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
                ['text' => "📌 nowpayment", 'callback_data' => "nowpayment"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay1setting"],
                ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
                ['text' => "📌 ارزی ریالی اول", 'callback_data' => "arzireyali1"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay2setting"],
                ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
                ['text' => "📌 ارزی ریالی دوم", 'callback_data' => "arzireyali2"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay3setting"],
                ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
                ['text' => "📌ارزی ریالی سوم", 'callback_data' => "oniranpay3"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpeysetting"],
                ['text' => $zarinpeystatus, 'callback_data' => "editpayment-zarinpey-$zarinpey"],
                ['text' => "🟠 زرین پی", 'callback_data' => "zarinpey"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "aqayepardakhtsetting"],
                ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
                ['text' => "🔵 آقای پرداخت", 'callback_data' => "aqayepardakht"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpalsetting"],
                ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
                ['text' => "🟡 زرین پال", 'callback_data' => "zarinpal"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "affilnecurrencysetting"],
                ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
                ['text' => "💵ارزی آفلاین", 'callback_data' => "affilnecurrency"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "startelegram"],
                ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
                ['text' => "💫Star Telegram", 'callback_data' => "none"],
            ],
            [
                ['text' => "⬆️ حداکثر شارژ موجودی", 'callback_data' => "maxbalanceaccount"],
                ['text' => "⬇️ حداقل شارژ موجودی", 'callback_data' => "mainbalanceaccount"],
            ],
            [
                ['text' => "💼 آدرس ولت", 'callback_data' => "walletaddress"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم فاکسیما هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status);
} elseif ($text == "💰 کش بک کارت به کارت") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashcart", $from_id);
} elseif ($user['step'] == "getcashcart") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackcart");
} elseif ($text == "💰 کش بک آقای پرداخت") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashahaypar", $from_id);
} elseif ($user['step'] == "getcashahaypar") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackaqaypardokht");
} elseif ($text == "💰 کش بک ارزی ریالی دوم") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay2", $from_id);
} elseif ($user['step'] == "getcashiranpay2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $trnado, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay2");
} elseif ($text == "💰 کش بک ارزی ریالی سوم") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay4", $from_id);
} elseif ($user['step'] == "getcashiranpay4") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay3");
} elseif ($text == "💰 کش بک ارزی ریالی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay1", $from_id);
} elseif ($user['step'] == "getcashiranpay1") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay1");
} elseif ($text == "💰 کش بک plisio") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashplisio", $from_id);
} elseif ($user['step'] == "getcashplisio") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackplisio");
} elseif ($text == "💰 کش بک nowpayment") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashnowpayment", $from_id);
} elseif ($user['step'] == "getcashnowpayment") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "cashbacknowpayment");
} elseif ($text == "💰 کش بک زرین پال") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashzarinpal", $from_id);
} elseif ($user['step'] == "getcashzarinpal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $keyboardzarinpal, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackzarinpal");
} elseif ($text == "📦 انبار شبکه ملی" && $adminrulecheck['rule'] == "administrator") {


    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) {

        nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. لطفاً ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید و سپس به این بخش بیایید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }

    savedata("clear", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $panelCode = $panel['code_panel'];
    $stockKeyboard = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : json_encode(['keyboard'=>[[['text'=>'🏠 بازگشت به منوی مدیریت']]],'resize_keyboard'=>true]);
    nm_replyOrEdit($from_id, nmStockShelfStatusText($panelCode) . "\n\n📌 پنل فعال: <b>" . htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8') . "</b>\n\nابتدا برای هر محصول یک انبار بسازید؛ مثلا «انبار 10 گیگ». بعد کانفیگ‌ها را داخل همان انبار وارد کنید تا ربات دقیقاً بداند کدام کانفیگ برای کدام محصول/حجم/مدت است.", $stockKeyboard, 'HTML');
    step('home', $from_id);

} elseif (($text == "🚨 پنل اضطراری" || $text == "🌐 وضعیت نت ملی") && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }

    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $slimNationalKb = function_exists('nmNationalEmergencyMenuKeyboard') ? nmNationalEmergencyMenuKeyboard() : $optionManualsale;
    if ($text == "🚨 پنل اضطراری") {
        $newStatus = nmPanelEmergencyEnabled($panel) ? 'off_emergency_panel' : 'on_emergency_panel';
        update("marzban_panel", "emergency_panel_status", $newStatus, "code_panel", $panel['code_panel']);
        if ($newStatus === 'on_emergency_panel' && empty($panel['emergency_source_panel'])) {
            nm_replyOrEdit($from_id, "✅ پنل اضطراری روشن شد.\n\n📌 حالا روی «📌 ثبت پنل اضطراری» بزنید و یک پنل جداگانه از پنل‌های ثبت‌شده انتخاب کنید. تا وقتی پنل اضطراری انتخاب نشود، فقط وضعیت روشن است و جایگزینی انجام نمی‌شود.", $slimNationalKb, 'HTML');
        }
    } else {
        $newStatus = nmPanelNationalEnabled($panel) ? 'off_national_net' : 'on_national_net';
        update("marzban_panel", "national_net_status", $newStatus, "code_panel", $panel['code_panel']);
        if (empty($panel['stock_source_panel'])) update("marzban_panel", "stock_source_panel", $panel['code_panel'], "code_panel", $panel['code_panel']);
    }
    $panel = select("marzban_panel", "*", "code_panel", $panel['code_panel'], "select");
    nm_replyOrEdit($from_id, nmStockAdminPanelStatusText($panel), $slimNationalKb, 'HTML');

} elseif ($text == "📌 ثبت پنل اضطراری" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل اصلی انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    if (!nmPanelEmergencyEnabled($panel)) { nm_replyOrEdit($from_id, "❌ اول گزینه «🚨 پنل اضطراری» را روشن کنید، سپس پنل اضطراری را ثبت کنید.", $optionManualsale, 'HTML'); return; }

    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $rows = [];
    $stmt = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel <> :code ORDER BY name_panel ASC");
    $stmt->execute([':code' => $panel['code_panel']]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = [['text' => $r['name_panel']]];
    $rows[] = [['text' => "🏠 بازگشت به منوی مدیریت"]];
    $rowsKeyboardArray = ['keyboard'=>$rows,'resize_keyboard'=>true];
    $rowsKeyboard = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($rowsKeyboardArray)
        : json_encode($rowsKeyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 پنل جداگانه‌ای که باید در حالت اضطراری جایگزین پنل فعلی شود را انتخاب کنید.\n\n⚠️ این پنل باید قبلاً از بخش افزودن پنل ثبت شده باشد؛ مثل مرزبان، مرزنشین، سنایی، تک‌پورت و ...", $rowsKeyboard, 'HTML');
    step('nm_select_emergency_panel', $from_id);

} elseif ($user['step'] == "nm_select_emergency_panel" && $adminrulecheck['rule'] == "administrator") {
    $mainPanel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $emPanel = select("marzban_panel", "*", "name_panel", $text, "select");
    if (!$mainPanel || !$emPanel || $mainPanel['code_panel'] == $emPanel['code_panel']) {
        nm_replyOrEdit($from_id, "❌ پنل انتخابی معتبر نیست.", $optionManualsale, 'HTML');
        step('home', $from_id);
        return;
    }
    update("marzban_panel", "emergency_source_panel", $emPanel['code_panel'], "code_panel", $mainPanel['code_panel']);
    $cnt = nmStockSyncProductMap($emPanel['code_panel'], $emPanel['code_panel'], $user['agent'] ?? 'all');
    $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ پنل اضطراری ثبت شد: <b>{$emPanel['name_panel']}</b>\n\nاز این به بعد اگر پنل اصلی قطع باشد، ساخت/تمدید/حجم اضافه ابتدا با این پنل انجام می‌شود. محصولات پنل اضطراری همگام شدند: {$cnt}", $stockKb, 'HTML');
    step('home', $from_id);

} elseif ($text == "🔄 همگام‌سازی محصولات انبار" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید و سپس به «📦 انبار شبکه ملی» بیایید.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $count = nmStockSyncProductMap($panel['code_panel'], nmPanelResolveStockCode($panel), $user['agent'] ?? 'all');
    $emPanel = nmPanelEmergencyPanel($panel);
    if ($emPanel) $count += nmStockSyncProductMap($emPanel['code_panel'], $emPanel['code_panel'], $user['agent'] ?? 'all');
    $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ همگام‌سازی محصولات انجام شد.\n\nتعداد محصولات: {$count}\n\n" . nmStockShelfStatusText($panel['code_panel']), $stockKb, 'HTML');

} elseif ($text == "📊 گزارش موجودی انبار" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $panelCode = is_array($panel) ? $panel['code_panel'] : 'auto';
    nm_replyOrEdit($from_id, nmStockShelfStatusText($panelCode) . "\n\n" . nmStockStatusText($panelCode), null, 'HTML');

} elseif ($text == "➕ افزودن انبار مدنظر" && $adminrulecheck['rule'] == "administrator") {
    nm_replyOrEdit($from_id, "📌 نام انبار را ارسال کنید.\n\nمثال: <code>انبار 10 گیگ 30 روزه</code>\nبعد از ثبت، دسته‌بندی و محصول فروشگاه را انتخاب می‌کنید و دفعات بعد کانفیگ‌ها مستقیم داخل همین انبار ثبت می‌شوند.", $backadmin, 'HTML');
    step('nm_shelf_name', $from_id);

} elseif ($user['step'] == "nm_shelf_name" && $adminrulecheck['rule'] == "administrator") {
    $shelfNameRaw = trim((string)$text);
    if ($shelfNameRaw === '' || $shelfNameRaw[0] === '{' || $shelfNameRaw[0] === '[' || mb_strlen($shelfNameRaw, 'UTF-8') > 120) {
        nm_replyOrEdit($from_id, "❌ نام انبار نامعتبر است. یک نام ساده (حداکثر ۱۲۰ کاراکتر) بدون کاراکترهای {، [ یا کد JSON ارسال کنید.\n\nمثال: <code>انبار 10 گیگ 30 روزه</code>", $backadmin, 'HTML');
        return;
    }
    $currentPanel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    savedata("clear", "nm_shelf_name", $shelfNameRaw);
    if ($currentPanel) {
        savedata("save", "namepanel", $currentPanel['name_panel']);
        savedata("save", "code_panel", $currentPanel['code_panel']);
    }
    if (function_exists('nmAnyNationalNetEnabled') && nmAnyNationalNetEnabled()) {
        $panelKeyboard = function_exists('nmNationalAdminPanelKeyboard') ? nmNationalAdminPanelKeyboard() : $json_list_marzban_panel;
        nm_replyOrEdit($from_id, "📌 وضعیت نت ملی / پنل اضطراری روشن است؛ ابتدا پنلی که این انبار برای آن ساخته می‌شود را انتخاب کنید.", $panelKeyboard, 'HTML');
        step('nm_shelf_panel', $from_id);
        return;
    }
    $catCountOff = function_exists('nmAdminCategoryCount') ? nmAdminCategoryCount(null) : -1;
    $catCountOffPanel = function_exists('nmAdminCategoryCount') ? nmAdminCategoryCount($currentPanel ?: null) : -1;
    error_log(sprintf('[STOCK_SHELF_CAT] step=nm_shelf_name user=%s national=0 panel=%s category_count_global=%d category_count_panel=%d', (string)$from_id, (string)(is_array($currentPanel) ? ($currentPanel['name_panel'] ?? '∅') : '∅'), $catCountOff, $catCountOffPanel));
    $rowsKeyboard = function_exists('nmNationalAdminCategoryKeyboard') ? nmNationalAdminCategoryKeyboard() : json_encode(['keyboard'=>[[['text'=>'بدون دسته‌بندی']],[['text'=>'🏠 بازگشت به منوی مدیریت']]],'resize_keyboard'=>true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 دسته‌بندی محصول این انبار را انتخاب کنید.", $rowsKeyboard, 'HTML');
    step('nm_shelf_category', $from_id);

} elseif ($user['step'] == "nm_shelf_panel" && $adminrulecheck['rule'] == "administrator") {
    if ($text == "❌ پنلی پیدا نشد") {
        nm_replyOrEdit($from_id, "❌ پنل فعالی پیدا نشد. ابتدا از منوی مدیریت یک پنل فعال ثبت کنید.", $optionManualsale, 'HTML');
        step('home', $from_id);
        return;
    }
    // Robustly resolve the picked panel (exact -> emoji-stripped -> normalized
    // fuzzy) so an inline/decorated button label can't strand the admin here and
    // prevent them from ever reaching the category step.
    $panel = function_exists('nmResolveActivePanelByName') ? nmResolveActivePanelByName($text) : select("marzban_panel", "*", "name_panel", $text, "select");
    if (!$panel) $panel = select("marzban_panel", "*", "name_panel", $text, "select");
    if (!$panel && function_exists('nmResolvePanelFromUserState')) $panel = nmResolvePanelFromUserState(null, $text);
    if (!$panel) {
        error_log(sprintf('[STOCK_SHELF_CAT] step=nm_shelf_panel user=%s panel_pick_unresolved text=%s', (string)$from_id, (string)$text));
        nm_replyOrEdit($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً یکی از دکمه‌های لیست پنل‌ها را انتخاب کنید.", function_exists('nmNationalAdminPanelKeyboard') ? nmNationalAdminPanelKeyboard() : $json_list_marzban_panel, 'HTML');
        return;
    }
    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $catCount = function_exists('nmAdminCategoryCount') ? nmAdminCategoryCount(null) : -1;
    $catCountPanel = function_exists('nmAdminCategoryCount') ? nmAdminCategoryCount($panel) : -1;
    error_log(sprintf('[STOCK_SHELF_CAT] step=nm_shelf_panel user=%s national=1 panel=%s category_count_global=%d category_count_panel=%d', (string)$from_id, (string)($panel['name_panel'] ?? '∅'), $catCount, $catCountPanel));
    $rowsKeyboard = function_exists('nmNationalAdminCategoryKeyboard') ? nmNationalAdminCategoryKeyboard() : json_encode(['keyboard'=>[[['text'=>'بدون دسته‌بندی']],[['text'=>'🏠 بازگشت به منوی مدیریت']]],'resize_keyboard'=>true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 حالا دسته‌بندی محصول این انبار را انتخاب کنید.", $rowsKeyboard, 'HTML');
    step('nm_shelf_category', $from_id);

} elseif ($user['step'] == "nm_shelf_category" && $adminrulecheck['rule'] == "administrator") {
    $cat = $text === "بدون دسته‌بندی" ? null : $text;
    savedata("save", "nm_shelf_category", $cat ?: "");
    $freshUser = select("user", "*", "id", $from_id, "select");
    $data = json_decode($freshUser['Processing_value'] ?? $user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($freshUser ?: $user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? '', "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره انبار را ثبت کنید و ابتدا پنل را انتخاب کنید.", $optionManualsale, 'HTML'); step('home', $from_id); return; }
    $rows = [];
    $products = function_exists('nmProductsForPanelCategory') ? nmProductsForPanelCategory($panel, $user['agent'] ?? 'all', $cat) : [];
    if (!$products) {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location=:loc OR Location='/all') ORDER BY CAST(price_product AS UNSIGNED) ASC");
        $stmt->execute([':loc' => $panel['name_panel']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    foreach ($products as $r) $rows[] = [['text' => $r['name_product']]];
    if (!$rows) $rows[] = [['text' => "❌ محصولی پیدا نشد"]];
    $rows[] = [['text' => "🏠 بازگشت به منوی مدیریت"]];
    $rowsKeyboardArray = ['keyboard'=>$rows,'resize_keyboard'=>true];
    $rowsKeyboard = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($rowsKeyboardArray)
        : json_encode($rowsKeyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 محصولی که این انبار باید به آن وصل شود را انتخاب کنید.\n\nربات از حجم و مدت همین محصول تشخیص می‌دهد کانفیگ واردشده برای چند گیگ و چند روز است.", $rowsKeyboard, 'HTML');
    step('nm_shelf_product', $from_id);

} elseif ($user['step'] == "nm_shelf_product" && $adminrulecheck['rule'] == "administrator") {
    $freshUser = select("user", "*", "id", $from_id, "select");
    $data = json_decode($freshUser['Processing_value'] ?? $user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($freshUser ?: $user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    if (!$panel) $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $categoryName = $data['nm_shelf_category'] ?? null;
    if (function_exists('nmProductByNameForShelf')) {
        $product = nmProductByNameForShelf($text, $panel ?: [], $user['agent'] ?? 'all', $categoryName);
    } elseif (function_exists('nmProductByNameForPanel')) {
        $product = nmProductByNameForPanel($text, $panel['name_panel'] ?? '', $user['agent'] ?? null, $categoryName);
    } else {
        $product = select("product", "*", "name_product", $text, "select");
    }
    if (!$product || !$panel) { nm_replyOrEdit($from_id, "❌ محصول یا پنل پیدا نشد.", $optionManualsale, 'HTML'); step('home', $from_id); return; }
    nmStockShelfCreate($data['nm_shelf_name'] ?? ('انبار '.$product['name_product']), $panel, $product, null, $categoryName);
    update("marzban_panel", "stock_source_panel", $panel['code_panel'], "code_panel", $panel['code_panel']);
    $stockKbAfterCreate = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ انبار ثبت شد.\n\nنام: <b>".htmlspecialchars($data['nm_shelf_name'] ?? '', ENT_QUOTES, 'UTF-8')."</b>\nمحصول: <b>{$product['name_product']}</b>\nحجم: {$product['Volume_constraint']} گیگ\nمدت: {$product['Service_time']} روز\n\nحالا از «➕ وارد کردن دسته‌ای انبار» یا «➕ افزودن کانفیگ تکی انبار» کانفیگ وارد کنید.", $stockKbAfterCreate, 'HTML');
    step('home', $from_id);

} elseif (($text == "➕ وارد کردن دسته‌ای انبار" || $text == "➕ افزودن کانفیگ تکی انبار") && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. لطفاً ابتدا از منوی مدیریت پنل ها یک پنل را انتخاب کنید و سپس وارد بخش انبار شبکه ملی شوید.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }


    // Preserve namepanel/code_panel without wiping prior shelf-flow state.
    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    savedata("save", "nm_stock_import_mode", $text == "➕ افزودن کانفیگ تکی انبار" ? "single" : "bulk");
    // Start a fresh DURABLE import session. Unlike Processing_value (which gets
    // reset to "0" or the panel name by unrelated handlers), this column is only
    // touched by the stock flow, so shelf_id / pending_cfg / etc. survive.
    if (function_exists('nmStockSessionClear')) nmStockSessionClear($from_id);
    if (function_exists('nmStockSessionPatch')) {
        nmStockSessionPatch($from_id, ['mode' => ($text == "➕ افزودن کانفیگ تکی انبار" ? "single" : "bulk")]);
    }
    $shelfKeyboard = nmStockShelfKeyboard($panel['code_panel']);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decodedShelfKeyboard = json_decode($shelfKeyboard, true);
        if (isset($decodedShelfKeyboard['keyboard'])) $shelfKeyboard = rx_keyboardToInline($decodedShelfKeyboard);
    }
    nm_replyOrEdit($from_id, "📌 انباری که کانفیگ‌ها باید داخل آن ثبت شوند را انتخاب کنید.", $shelfKeyboard, 'HTML');
    step('nm_stock_select_shelf_import', $from_id);

} elseif ($user['step'] == "nm_stock_select_shelf_import" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    $chosen = null;
    $needle = trim((string)$text);
    // Try panel-scoped shelves first; if none match, widen to all shelves so the
    // selection still works when the resolved panel code drifted between steps.
    $candidateLists = [nmStockShelves($panel['code_panel'] ?? null, false)];
    if (!empty($panel['code_panel'])) $candidateLists[] = nmStockShelves(null, false);
    foreach ($candidateLists as $list) {
        foreach ($list as $shelf) {
            $shelfName = trim((string)($shelf['name'] ?? ''));
            if ($shelfName === '') continue;
            if ($needle === $shelfName || strpos($needle, $shelfName) !== false) { $chosen = $shelf; break 2; }
        }
    }
    if (!$chosen) {
        $availableNames = [];
        foreach ($candidateLists as $list) {
            foreach ($list as $sh) { $availableNames[] = (string)($sh['name'] ?? ''); }
        }
        $availableNames = array_values(array_unique(array_filter($availableNames)));
        error_log(sprintf(
            '[STOCK_SHELF_MISS] step=nm_stock_select_shelf_import user=%s panel_code=%s panel_name=%s needle=%s data=%s available_count=%d names=%s',
            (string)$from_id,
            (string)($panel['code_panel'] ?? '∅'),
            (string)($panel['name_panel'] ?? '∅'),
            $needle,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            count($availableNames),
            json_encode($availableNames, JSON_UNESCAPED_UNICODE)
        ));
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "❌ انبار انتخابی پیدا نشد. لطفاً از همان لیست روی یک انبار بزنید.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    error_log(sprintf(
        '[STOCK_SHELF_PICK] step=nm_stock_select_shelf_import user=%s chosen_id=%s chosen_name=%s pre_pv=%s',
        (string)$from_id,
        var_export($chosen['id'] ?? null, true),
        (string)($chosen['name'] ?? '∅'),
        (string)($user['Processing_value'] ?? '∅')
    ));
    // Persist into BOTH Processing_value (legacy) and a durable column that the
    // generic "0" state-resets (/start, back, Add_Balance, ...) never touch, so
    // the selection survives even if Processing_value is wiped before the next tap.
    nmStockSetActiveShelf($from_id, $chosen['id']);
    // Verify the write landed — same select() path savedata uses internally.
    $postWrite = select("user", "Processing_value", "id", $from_id, "select", ['cache' => false]);
    error_log(sprintf(
        '[STOCK_SHELF_PICK] post_save_pv=%s',
        is_array($postWrite) ? (string)($postWrite['Processing_value'] ?? '∅') : '∅'
    ));

    $askSubKbArr = [
        'keyboard' => [
            [['text' => "✅ بله، لینک اشتراک هم دارم"], ['text' => "🚫 خیر، فقط کانفیگ"]],
            [['text' => "🔗 فقط لینک اشتراک (بدون کانفیگ)"]],
            [['text' => "🏠 بازگشت به منوی مدیریت"]],
        ],
        'resize_keyboard' => true,
    ];
    $askSubKb = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($askSubKbArr)
        : json_encode($askSubKbArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 چه چیزی برای این انبار وارد می‌کنید؟\n\n✅ <b>کانفیگ + لینک اشتراک</b>: هر کانفیگ به‌همراه لینک اشتراکش.\n🚫 <b>فقط کانفیگ</b>: بدون لینک اشتراک.\n🔗 <b>فقط لینک اشتراک</b>: بدون کانفیگ تکی، فقط لینک(های) اشتراک.\n\nانبار: <b>{$chosen['name']}</b>\nمحصول: <b>{$chosen['product_name']}</b>", $askSubKb, 'HTML');
    step('nm_stock_ask_has_sub', $from_id);

} elseif ($user['step'] == "nm_stock_ask_has_sub" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_ask_has_sub user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s text=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE),
            (string)$text
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }
    // Re-hydrate the shelf id (durable + legacy) so continuation steps keep working.
    nmStockSetActiveShelf($from_id, $shelf['id']);
    $session = nmStockSessionGet($user);
    $mode = $session['mode'] ?? ($data['nm_stock_import_mode'] ?? 'bulk');
    if ($text == "✅ بله، لینک اشتراک هم دارم") {
        nmStockSessionPatch($from_id, ['has_sub' => '1', 'pending_cfg' => '', 'imported' => 0]);
        $endKbArr = [
            'keyboard' => [
                [['text' => "🏁 پایان ورود کانفیگ‌ها"]],
                [['text' => "🏠 بازگشت به منوی مدیریت"]],
            ],
            'resize_keyboard' => true,
        ];
        $endKb = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
            ? rx_keyboardToInline($endKbArr)
            : json_encode($endKbArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headMsg = $mode === 'single'
            ? "📌 لطفاً کانفیگ تکی را ارسال کنید.\n\nبعد از آن، لینک اشتراک متناظرش را خواهم خواست."
            : "📌 لطفاً اولین کانفیگ را ارسال کنید.\n\nبعد از هر کانفیگ، لینک اشتراک آن را می‌خواهم؛ سپس کانفیگ بعدی و لینک بعدی به‌صورت پشت‌به‌پشت.\n\nهر زمان خواستید پایان دهید روی «🏁 پایان ورود کانفیگ‌ها» بزنید.";
        nm_replyOrEdit($from_id, $headMsg, $endKb, 'HTML');
        step('nm_stock_paired_cfg', $from_id);
    } elseif ($text == "🚫 خیر، فقط کانفیگ") {
        nmStockSessionPatch($from_id, ['has_sub' => '0', 'imported' => 0]);
        $msg = $mode === 'single'
            ? "📌 کانفیگ تکی را ارسال کنید."
            : "📌 کانفیگ‌ها را هر خط یک مورد ارسال کنید.\n\nاین کانفیگ‌ها داخل انبار <b>{$shelf['name']}</b> و محصول <b>{$shelf['product_name']}</b> ثبت می‌شوند.";
        nm_replyOrEdit($from_id, $msg, $backadmin, 'HTML');
        step('nm_stock_bulk_import', $from_id);
    } elseif ($text == "🔗 فقط لینک اشتراک (بدون کانفیگ)") {
        nmStockSessionPatch($from_id, ['has_sub' => 'link_only', 'imported' => 0]);
        $msg = $mode === 'single'
            ? "📌 لینک اشتراک را ارسال کنید (بدون کانفیگ تکی).\n\nباید یک آدرس کامل با <code>http(s)://</code> باشد."
            : "📌 لینک(های) اشتراک را هر خط یک مورد ارسال کنید (بدون کانفیگ تکی).\n\nهر خط باید یک آدرس کامل با <code>http(s)://</code> باشد.\nاین لینک‌ها داخل انبار <b>{$shelf['name']}</b> و محصول <b>{$shelf['product_name']}</b> ثبت می‌شوند.";
        nm_replyOrEdit($from_id, $msg, $backadmin, 'HTML');
        step('nm_stock_linkonly_import', $from_id);
    } else {
        $stockKbErr2 = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "❌ یکی از گزینه‌های زیر را انتخاب کنید.", $stockKbErr2, 'HTML');
        step('home', $from_id);
    }

} elseif ($user['step'] == "nm_stock_paired_cfg" && $adminrulecheck['rule'] == "administrator") {

    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_paired_cfg user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }
    $session = nmStockSessionGet($user);
    $mode = $session['mode'] ?? ($data['nm_stock_import_mode'] ?? 'bulk');
    $imported = (int)($session['imported'] ?? ($data['nm_stock_imported'] ?? 0));
    if ($text == "🏁 پایان ورود کانفیگ‌ها") {
        $stockKbDone1 = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "✅ ورود جفتی کانفیگ‌ها پایان یافت.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n➕ تعداد ثبت‌شده در این جلسه: {$imported}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbDone1, 'HTML');
        if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
        step('home', $from_id);
        return;
    }
    $cfg = trim((string)$text);
    if ($cfg === '' || mb_strlen($cfg, 'UTF-8') < 8) {
        nm_replyOrEdit($from_id, "❌ کانفیگ معتبر نیست. لطفاً مجدداً ارسال کنید.", $backadmin, 'HTML');
        return;
    }

    nmStockSessionPatch($from_id, ['pending_cfg' => $cfg]);
    $endKbArr = [
        'keyboard' => [
            [['text' => "↩️ بدون لینک اشتراک ثبت کن"]],
            [['text' => "🏁 پایان ورود کانفیگ‌ها"]],
            [['text' => "🏠 بازگشت به منوی مدیریت"]],
        ],
        'resize_keyboard' => true,
    ];
    $endKb = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($endKbArr)
        : json_encode($endKbArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 حالا لینک اشتراک (Subscription URL) متناظر با همین کانفیگ را ارسال کنید.\n\nاگر برای این کانفیگ خاص لینک ندارید، روی «↩️ بدون لینک اشتراک ثبت کن» بزنید تا فقط خود کانفیگ ذخیره شود.", $endKb, 'HTML');
    step('nm_stock_paired_sub', $from_id);

} elseif ($user['step'] == "nm_stock_paired_sub" && $adminrulecheck['rule'] == "administrator") {

    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_paired_sub user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }
    $session = nmStockSessionGet($user);
    $cfg = (string)($session['pending_cfg'] ?? ($data['nm_stock_pending_cfg'] ?? ''));
    if ($cfg === '') {
        nm_replyOrEdit($from_id, "❌ کانفیگ pending پیدا نشد. دوباره ارسال کنید.", $backadmin, 'HTML');
        step('nm_stock_paired_cfg', $from_id);
        return;
    }
    $mode = $session['mode'] ?? ($data['nm_stock_import_mode'] ?? 'bulk');
    $imported = (int)($session['imported'] ?? ($data['nm_stock_imported'] ?? 0));
    if ($text == "🏁 پایان ورود کانفیگ‌ها") {

        nmStockSessionPatch($from_id, ['pending_cfg' => '']);
        $stockKbDone2 = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "✅ ورود جفتی کانفیگ‌ها پایان یافت.\n\n⚠️ آخرین کانفیگ ارسالی به‌دلیل نداشتن لینک اشتراک ذخیره نشد.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n➕ تعداد ثبت‌شده در این جلسه: {$imported}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbDone2, 'HTML');
        if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
        step('home', $from_id);
        return;
    }
    $sub = null;
    if ($text == "↩️ بدون لینک اشتراک ثبت کن") {
        $sub = null;
    } else {
        $candidate = trim((string)$text);
        if ($candidate === '' || !preg_match('#^https?://\S+$#i', $candidate)) {
            nm_replyOrEdit($from_id, "❌ لینک اشتراک معتبر نیست. لطفاً یک URL کامل با http(s):// ارسال کنید یا روی «↩️ بدون لینک اشتراک ثبت کن» بزنید.", $backadmin, 'HTML');
            return;
        }
        $sub = $candidate;
    }

    $result = nmStockImportBatch($shelf['stock_codepanel'], $shelf['codeproduct'], $cfg, $shelf['volume_gb'], $shelf['id'], $sub);
    $imported += (int)($result['ok'] ?? 0);
    nmStockSessionPatch($from_id, ['imported' => $imported, 'pending_cfg' => '']);
    if ($mode === 'single') {
        $stockKbDone3 = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "✅ کانفیگ تکی همراه با لینک اشتراک ذخیره شد.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n➕ ثبت‌شده: {$result['ok']}\n♻️ تکراری: {$result['duplicate']}\n⚠️ نامعتبر: {$result['bad']}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbDone3, 'HTML');
        if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
        step('home', $from_id);
        return;
    }

    $endKbArr = [
        'keyboard' => [
            [['text' => "🏁 پایان ورود کانفیگ‌ها"]],
            [['text' => "🏠 بازگشت به منوی مدیریت"]],
        ],
        'resize_keyboard' => true,
    ];
    $endKb = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($endKbArr)
        : json_encode($endKbArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "✅ جفت کانفیگ + لینک ثبت شد. (تعداد ثبت‌شده تا اینجا: {$imported})\n\n📌 کانفیگ بعدی را ارسال کنید یا روی «🏁 پایان ورود کانفیگ‌ها» بزنید.", $endKb, 'HTML');
    step('nm_stock_paired_cfg', $from_id);

} elseif ($user['step'] == "nm_stock_bulk_import" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_bulk_import user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }
    $raw = $text;
    $sub = null;
    if (preg_match('/^(.+?)\nSUB=(https?:\/\/\S+)/is', $raw, $m)) { $raw = trim($m[1]); $sub = trim($m[2]); }
    $result = nmStockImportBatch($shelf['stock_codepanel'], $shelf['codeproduct'], $raw, $shelf['volume_gb'], $shelf['id'], $sub);
    $stockKbBulk = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ واردسازی انبار انجام شد.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n➕ ثبت‌شده: {$result['ok']}\n♻️ تکراری: {$result['duplicate']}\n⚠️ نامعتبر: {$result['bad']}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbBulk, 'HTML');
    if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
    step('home', $from_id);

} elseif ($user['step'] == "nm_stock_linkonly_import" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_linkonly_import user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }
    $raw = trim((string)$text);
    if ($raw === '') {
        nm_replyOrEdit($from_id, "❌ چیزی دریافت نشد. لطفاً لینک(های) اشتراک را ارسال کنید.", $backadmin, 'HTML');
        return;
    }
    $result = nmStockImportLinkOnly($shelf['stock_codepanel'], $shelf['codeproduct'], $raw, $shelf['volume_gb'], $shelf['id']);
    $stockKbLink = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ واردسازی لینک‌های اشتراک انجام شد.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n🔗 ثبت‌شده: {$result['ok']}\n♻️ تکراری: {$result['duplicate']}\n⚠️ نامعتبر (آدرس غیرمعتبر): {$result['bad']}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbLink, 'HTML');
    if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
    step('home', $from_id);
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $shelfKeyboard = nmStockShelfKeyboard($panel['code_panel'] ?? null);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decodedShelfKeyboard = json_decode($shelfKeyboard, true);
        if (isset($decodedShelfKeyboard['keyboard'])) $shelfKeyboard = rx_keyboardToInline($decodedShelfKeyboard);
    }
    nm_replyOrEdit($from_id, "📌 برای ویرایش اتصال انبار، همان انبار را انتخاب کنید؛ سپس نام/دسته/محصول را دوباره ثبت کنید.", $shelfKeyboard, 'HTML');
    step('nm_edit_shelf_select', $from_id);

} elseif ($user['step'] == "nm_edit_shelf_select" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    $chosen = null;
    foreach (nmStockShelves($panel['code_panel'] ?? null, false) as $shelf) if (strpos($text, $shelf['name']) !== false) { $chosen = $shelf; break; }
    if (!$chosen) { nm_replyOrEdit($from_id, "❌ انبار پیدا نشد.", $optionManualsale, 'HTML'); step('home', $from_id); return; }
    savedata("clear", "nm_shelf_name", $user['Processing_value']);
    savedata("save", "nm_shelf_name", $chosen['name']);
    nm_replyOrEdit($from_id, "📌 نام جدید انبار را ارسال کنید یا همین نام را مجدد ارسال کنید:\n<code>{$chosen['name']}</code>", $backadmin, 'HTML');
    step('nm_shelf_name', $from_id);

} elseif ($text == "🗑 حذف کامل انبار" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $shelfKeyboard = nmStockShelfKeyboard($panel['code_panel'] ?? null);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decodedShelfKeyboard = json_decode($shelfKeyboard, true);
        if (isset($decodedShelfKeyboard['keyboard'])) $shelfKeyboard = rx_keyboardToInline($decodedShelfKeyboard);
    }
    nm_replyOrEdit($from_id, "📌 انباری که می‌خواهید کاملاً حذف شود را انتخاب کنید.\n\n⚠️ همه کانفیگ‌های این انبار هم با آن حذف می‌شوند.", $shelfKeyboard, 'HTML');
    step('nm_delete_shelf_select', $from_id);

} elseif ($user['step'] == "nm_delete_shelf_select" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    $needle = trim((string)$text);
    $chosen = null;
    foreach (nmStockShelves($panel['code_panel'] ?? null, false) as $shelf) {
        $shelfName = trim((string)($shelf['name'] ?? ''));
        if ($shelfName !== '' && ($needle === $shelfName || strpos($needle, $shelfName) !== false)) { $chosen = $shelf; break; }
    }
    if (!$chosen) {
        foreach (nmStockShelves(null, false) as $shelf) {
            $shelfName = trim((string)($shelf['name'] ?? ''));
            if ($shelfName !== '' && ($needle === $shelfName || strpos($needle, $shelfName) !== false)) { $chosen = $shelf; break; }
        }
    }
    if (!$chosen) {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "❌ انبار پیدا نشد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("save", "nm_shelf_delete_id", (string)$chosen['id']);
    $kb = json_encode([
        'inline_keyboard' => [
            [['text' => '✅ تایید حذف کامل', 'callback_data' => 'nm_shelf_del:confirm:' . (int)$chosen['id']]],
            [['text' => '❌ لغو',           'callback_data' => 'nm_shelf_del:cancel']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_replyOrEdit($from_id, "⚠️ آیا انبار <b>" . htmlspecialchars($chosen['name']) . "</b> به همراه همه کانفیگ‌های آن حذف شود؟", $kb, 'HTML');
    step('nm_delete_shelf_confirm', $from_id);

} elseif (isset($datain) && is_string($datain) && strpos($datain, 'nm_shelf_del:') === 0 && in_array($from_id, $admin_ids)) {
    $parts = explode(':', $datain);
    $act = $parts[1] ?? '';
    if ($act === 'cancel') {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "❎ حذف انبار لغو شد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($act === 'confirm') {
        $sid = (int)($parts[2] ?? 0);
        if ($sid > 0) {
            try {
                $pdo->prepare("UPDATE nm_config_stock SET status='disabled' WHERE shelf_id=:sid")->execute([':sid'=>$sid]);
                $pdo->prepare("DELETE FROM nm_stock_shelves WHERE id=:id")->execute([':id'=>$sid]);
            } catch (Throwable $e) { error_log('shelf delete failed: '.$e->getMessage()); }
        }
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, "✅ انبار و کانفیگ‌های آن حذف شدند.", null, 'HTML');
        }
        nm_adminInstantReply($from_id, "🗑 انبار حذف شد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }

} elseif ($text == "❌ حذف کانفیگ انبار" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $shelfKeyboard = nmStockShelfKeyboard($panel['code_panel'] ?? null);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decodedShelfKeyboard = json_decode($shelfKeyboard, true);
        if (isset($decodedShelfKeyboard['keyboard'])) $shelfKeyboard = rx_keyboardToInline($decodedShelfKeyboard);
    }
    nm_replyOrEdit($from_id, "📌 انبار موردنظر برای حذف کانفیگ را انتخاب کنید.", $shelfKeyboard, 'HTML');
    step('nm_delete_stock_select_shelf', $from_id);

} elseif ($user['step'] == "nm_delete_stock_select_shelf" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    $chosen = null;
    $needle = trim((string)$text);
    foreach (nmStockShelves($panel['code_panel'] ?? null, false) as $shelf) {
        $shelfName = trim((string)($shelf['name'] ?? ''));
        if ($shelfName !== '' && ($needle === $shelfName || strpos($needle, $shelfName) !== false)) { $chosen = $shelf; break; }
    }
    if (!$chosen) {
        // fallback to all shelves
        foreach (nmStockShelves(null, false) as $shelf) {
            $shelfName = trim((string)($shelf['name'] ?? ''));
            if ($shelfName !== '' && ($needle === $shelfName || strpos($needle, $shelfName) !== false)) { $chosen = $shelf; break; }
        }
    }
    if (!$chosen) {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "❌ انبار پیدا نشد. لطفاً مجدد از لیست انتخاب کنید.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("save", "nm_shelf_id", $chosen['id']);
    $rows = [
        [['text' => "🗑 حذف همه کانفیگ‌های فعال این انبار"]],
        [['text' => "🔢 حذف کانفیگ با آیدی"]],
        [['text' => "📋 نمایش لیست کانفیگ‌ها"]],
        [['text' => "🔙 بازگشت به انبار"]],
    ];
    $rowsKeyboardArray = ['keyboard'=>$rows,'resize_keyboard'=>true];
    $rowsKeyboard = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($rowsKeyboardArray)
        : json_encode($rowsKeyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 نوع حذف را انتخاب کنید.\n\nانبار: <b>{$chosen['name']}</b>", $rowsKeyboard, 'HTML');
    step('nm_delete_stock_action', $from_id);

} elseif ($user['step'] == "nm_delete_stock_action" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $sid = (int)($data['nm_shelf_id'] ?? 0);

    if ($text == "🗑 حذف همه کانفیگ‌های فعال این انبار" || $text == "حذف همه کانفیگ‌های فعال این انبار" || $datain == "nm_del_all_stock") {
        $stmt = $pdo->prepare("UPDATE nm_config_stock SET status='disabled' WHERE shelf_id=:sid AND status='active'");
        $stmt->execute([':sid' => $sid]);
        $count = $stmt->rowCount();
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "✅ عملیات حذف انجام شد.\nتعداد حذف‌شده: {$count}", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }

    if ($text == "🔢 حذف کانفیگ با آیدی") {
        nm_adminInstantReply($from_id, "🔢 آیدی عددی کانفیگ موردنظر را ارسال کنید.\n\nبرای مشاهده آیدی‌ها می‌توانید از «📋 نمایش لیست کانفیگ‌ها» استفاده کنید.", json_encode(['keyboard'=>[[['text'=>'🔙 بازگشت به انبار']]],'resize_keyboard'=>true], JSON_UNESCAPED_UNICODE), 'HTML');
        step('nm_stock_delete_by_id', $from_id);
        return;
    }

    if ($text == "📋 نمایش لیست کانفیگ‌ها") {
        if (!function_exists('nmStockConfigInlineList')) {
            nm_adminInstantReply($from_id, "❌ تابع لیست در دسترس نیست.", $optionManualsale, 'HTML');
            step('home', $from_id);
            return;
        }
        $list = nmStockConfigInlineList($sid, 0, 10, 'nm_cfg');
        if ((int)$list['count'] === 0) {
            $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
            nm_adminInstantReply($from_id, "ℹ️ هیچ کانفیگ فعالی در این انبار وجود ندارد.", $stockKb, 'HTML');
            step('home', $from_id);
            return;
        }
        nm_adminInstantReply($from_id, "📋 لیست کانفیگ‌های فعال — صفحه ۱:\n<i>روی هر آیتم بزنید تا جزئیات و دکمه حذف باز شود.</i>", $list['keyboard'], 'HTML');
        step('home', $from_id);
        return;
    }

    nm_adminInstantReply($from_id, "❌ گزینه نامعتبر.", $optionManualsale, 'HTML');
    step('home', $from_id);

} elseif ($user['step'] == "nm_stock_delete_by_id" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $sid = (int)($data['nm_shelf_id'] ?? 0);
    $cfgId = (int)trim((string)$text);
    if ($cfgId <= 0) {
        nm_adminInstantReply($from_id, "❌ آیدی نامعتبر است. لطفاً یک عدد صحیح ارسال کنید.", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT id, content, status, shelf_id FROM nm_config_stock WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $cfgId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "❌ کانفیگی با این آیدی پیدا نشد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($sid > 0 && (int)$row['shelf_id'] !== $sid) {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "❌ این کانفیگ متعلق به انبار انتخاب‌شده نیست.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("save", "nm_pending_cfg_delete", (string)$cfgId);
    $info = function_exists('nmStockExtractConfigInfo') ? nmStockExtractConfigInfo($row['content']) : ['username'=>'—','port'=>'—','host'=>'—'];
    $preview = mb_substr((string)$row['content'], 0, 80, 'UTF-8') . (mb_strlen((string)$row['content'], 'UTF-8') > 80 ? '…' : '');
    $msg = "🧾 جزئیات کانفیگ #<b>{$cfgId}</b>\n"
         . "👤 نام کاربری: <code>" . htmlspecialchars($info['username'] !== '' ? $info['username'] : '—') . "</code>\n"
         . "🔌 پورت: <code>" . htmlspecialchars($info['port'] !== '' ? $info['port'] : '—') . "</code>\n"
         . "🌐 هاست: <code>" . htmlspecialchars($info['host'] !== '' ? $info['host'] : '—') . "</code>\n"
         . "📋 پیش‌نمایش: <code>" . htmlspecialchars($preview) . "</code>\n\nآیا حذف شود؟";
    $kb = json_encode([
        'inline_keyboard' => [
            [['text' => '✅ تایید حذف', 'callback_data' => 'nm_cfg:del:' . $cfgId]],
            [['text' => '❌ لغو',       'callback_data' => 'nm_cfg:cancel']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, $msg, $kb, 'HTML');
    step('nm_stock_delete_confirm', $from_id);

} elseif (isset($datain) && is_string($datain) && strpos($datain, 'nm_cfg:') === 0 && in_array($from_id, $admin_ids)) {
    $parts = explode(':', $datain);
    $action = $parts[1] ?? '';
    $arg    = $parts[2] ?? '';
    if ($action === 'close' || $action === 'cancel') {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "❎ لغو شد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($action === 'page') {
        $data = json_decode($user['Processing_value'], true) ?: [];
        $sid = (int)($data['nm_shelf_id'] ?? 0);
        $page = max(0, (int)$arg);
        $list = nmStockConfigInlineList($sid, $page, 10, 'nm_cfg');
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, "📋 لیست کانفیگ‌های فعال — صفحه " . ($page + 1) . ":\n<i>روی هر آیتم بزنید تا جزئیات و دکمه حذف باز شود.</i>", $list['keyboard'], 'HTML');
        } else {
            nm_adminInstantReply($from_id, "📋 لیست کانفیگ‌های فعال — صفحه " . ($page + 1), $list['keyboard'], 'HTML');
        }
        return;
    }
    if ($action === 'view') {
        $cfgId = (int)$arg;
        $stmt = $pdo->prepare("SELECT id, content, status FROM nm_config_stock WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $cfgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { nm_adminInstantReply($from_id, "❌ کانفیگ یافت نشد.", null, 'HTML'); return; }
        $info = function_exists('nmStockExtractConfigInfo') ? nmStockExtractConfigInfo($row['content']) : ['username'=>'—','port'=>'—','host'=>'—'];
        $preview = mb_substr((string)$row['content'], 0, 100, 'UTF-8') . (mb_strlen((string)$row['content'], 'UTF-8') > 100 ? '…' : '');
        $msg = "🧾 جزئیات کانفیگ #<b>{$cfgId}</b>\n"
             . "👤 نام کاربری: <code>" . htmlspecialchars($info['username'] !== '' ? $info['username'] : '—') . "</code>\n"
             . "🔌 پورت: <code>" . htmlspecialchars($info['port'] !== '' ? $info['port'] : '—') . "</code>\n"
             . "🌐 هاست: <code>" . htmlspecialchars($info['host'] !== '' ? $info['host'] : '—') . "</code>\n"
             . "📋 پیش‌نمایش: <code>" . htmlspecialchars($preview) . "</code>";
        $kb = json_encode([
            'inline_keyboard' => [
                [['text' => '🗑 حذف این کانفیگ', 'callback_data' => 'nm_cfg:askdel:' . $cfgId]],
                [['text' => '⬅️ بازگشت به لیست', 'callback_data' => 'nm_cfg:page:0']],
                [['text' => '❌ بستن',           'callback_data' => 'nm_cfg:close']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, $msg, $kb, 'HTML');
        } else {
            nm_adminInstantReply($from_id, $msg, $kb, 'HTML');
        }
        return;
    }
    if ($action === 'askdel') {
        $cfgId = (int)$arg;
        $kb = json_encode([
            'inline_keyboard' => [
                [['text' => '✅ تایید حذف', 'callback_data' => 'nm_cfg:del:' . $cfgId]],
                [['text' => '❌ لغو',       'callback_data' => 'nm_cfg:view:' . $cfgId]],
            ],
        ], JSON_UNESCAPED_UNICODE);
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, "⚠️ حذف کانفیگ #<b>{$cfgId}</b> تایید می‌کنید؟", $kb, 'HTML');
        } else {
            nm_adminInstantReply($from_id, "⚠️ حذف کانفیگ #<b>{$cfgId}</b> تایید می‌کنید؟", $kb, 'HTML');
        }
        return;
    }
    if ($action === 'del') {
        $cfgId = (int)$arg;
        $stmt = $pdo->prepare("UPDATE nm_config_stock SET status='disabled' WHERE id=:id AND status='active'");
        $stmt->execute([':id' => $cfgId]);
        $ok = $stmt->rowCount() > 0;
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, $ok ? "✅ کانفیگ #<b>{$cfgId}</b> حذف شد." : "ℹ️ کانفیگ #<b>{$cfgId}</b> از قبل حذف بود یا یافت نشد.", null, 'HTML');
        } else {
            nm_adminInstantReply($from_id, $ok ? "✅ کانفیگ #{$cfgId} حذف شد." : "ℹ️ یافت نشد.", null, 'HTML');
        }
        return;
    }

} elseif ($text == "➕ اضافه کردن کانفیگ") {
    nm_adminInstantReply($from_id, "📌 برای اضافه کردن کانفیگ ابتدا یک نام ارسال نمایید.", $backadmin, 'HTML');
    step('getnameconfigm', $from_id);
    savedata("clear", "namepanel", $user['Processing_value']);
} elseif ($user['step'] == "getnameconfigm") {
    $exitsname = select("manualsell", "*", "namerecord", $text, "count");
    if (intval($exitsname) != 0) {
        nm_adminInstantReply($from_id, "این نام وجود دارد", null, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $product = [];
    savedata("save", "namerecord", $text);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :text or Location = '/all' ");
    $stmt->bindParam(':text', $userdata['namepanel'], PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
    nm_adminInstantReply($from_id, "📌 نام محصول خود را ارسال نمایید در صورتی که میخواهید  برای اکانت تست تنظیم کنید متن تست را ارسال کنید.", $json_list_product_list_admin, 'HTML');
    step('getnameproduct', $from_id);
    savedata("save", "namerecord", $text);
} elseif ($user['step'] == "getnameproduct") {
    if ($text != "تست") {
        $product = select("product", "*", "name_product", $text, "select");
        if ($product == false) {
            nm_adminInstantReply($from_id, "محصول در ربات وجود ندارد", $backadmin, 'HTML');
            return;
        }
        savedata("save", "codeproduct", $product['code_product']);
    } else {
        savedata("save", "codeproduct", "usertest");
    }
    nm_adminInstantReply($from_id, "📌 کانفیگ یا متن دیگر خود را ارسال نمایید", $backadmin, 'HTML');
    step('getconfigtext', $from_id);
} elseif ($user['step'] == "getconfigtext") {
    nm_adminInstantReply($from_id, "✅ کانفیگ با موفقیت ذخیره گردید.", $optionManualsale, 'HTML');
    step('home', $from_id);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    $status = "active";
    $stmt = $pdo->prepare("INSERT IGNORE INTO manualsell (codepanel,namerecord,contentrecord,status,codeproduct) VALUES (:codepanel,:namerecord,:contentrecord,:status,:codeproduct)");
    $stmt->bindParam(':codepanel', $panel['code_panel']);
    $stmt->bindParam(':namerecord', $userdata['namerecord']);
    $stmt->bindParam(':contentrecord', $text);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':codeproduct', $userdata['codeproduct']);
    $stmt->execute();
    update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
} elseif (trim($text) == "❌ حذف کانفیگ") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $listconfig = [];
    $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = '{$panel['code_panel']}'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $listconfig[] = [$row['namerecord']];
    }
    $list_configmanual = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_configmanual['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($listconfig as $button) {
        $list_configmanual['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_manualconfig_list = json_encode($list_configmanual);
    nm_adminInstantReply($from_id, "📌 نام کانفیگی که میخواهید حذف نمایید را ارسال کنید ", $json_list_manualconfig_list, 'HTML');
    step("getnameremove", $from_id);
} elseif ($user['step'] == "getnameremove") {
    nm_adminInstantReply($from_id, "✅ کانفیگ با موفقیت حذف گردید.", $optionManualsale, 'HTML');
    $userdata = json_decode($user['Processing_value'], true);
    $panelName = is_array($userdata) && isset($userdata['namepanel']) ? $userdata['namepanel'] : $user['Processing_value'];
    $panel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    if ($panel) {
        $stmt = $pdo->prepare("DELETE FROM manualsell WHERE namerecord = ? AND codepanel = ?");
        $stmt->bindParam(1, $text);
        $stmt->bindParam(2, $panel['code_panel']);
        $stmt->execute();
    }
    step("home", $from_id);
} elseif ($text == "🌍 قیمت تغییر لوکیشن" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 قیمت تغییر لوکیشن از سایر پنل‌ها به این پنل را ارسال کنید", $backadmin, 'HTML');
    step('setpricechangelocation', $from_id);
} elseif ($user['step'] == "setpricechangelocation") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], "📌قیمت تغییر لوکیشن با موفقیت تغییر کرد");
    update("marzban_panel", "priceChangeloc", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "➕ قیمت حجم اضافه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 قیمت حجم اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPriceExtra', $from_id);
} elseif ($user['step'] == "GetPriceExtra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ در صورتی که می خواهید قیمت برای تمامی گروه های کاربری تنظیم شود متن <code>all</code> را ارسال کنید", $backuser, 'HTML');
    step('gettypeextra', $from_id);
} elseif ($user['step'] == "gettypeextra") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('price', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['priceextravolume'] ?? '{}', true);
    if (!is_array($eextraprice)) $eextraprice = [];
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "priceextravolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⚙️ قیمت حجم سرویس دلخواه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 قیمت حجم اضافه دلخواه این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPricecustomvo', $from_id);
} elseif ($user['step'] == "GetPricecustomvo") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ در صورتی که می خواهید قیمت برای تمامی گروه های کاربری تنظیم شود متن <code>all</code> را ارسال کنید", $backuser, 'HTML');
    step('gettypeextracustom', $from_id);
} elseif ($user['step'] == "gettypeextracustom") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('price', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['pricecustomvolume'] ?? '{}', true);
    if (!is_array($eextraprice)) $eextraprice = [];
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "pricecustomvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⏳ قیمت زمان اضافه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 قیمت زمان اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPricetimeextra', $from_id);
} elseif ($user['step'] == "GetPricetimeextra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ در صورتی که می خواهید قیمت برای تمامی گروه های کاربری تنظیم شود متن <code>all</code> را ارسال کنید", $backuser, 'HTML');
    step('gettypeextratime', $from_id);
} elseif ($user['step'] == "gettypeextratime") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('price', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['priceextratime'] ?? '{}', true);
    if (!is_array($eextraprice)) $eextraprice = [];
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "priceextratime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⏳ قیمت زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 قیمت زمان دلخواه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPriceExtratime', $from_id);
} elseif ($user['step'] == "GetPriceExtratime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ در صورتی که می خواهید قیمت برای تمامی گروه های کاربری تنظیم شود متن <code>all</code> را ارسال کنید", $backuser, 'HTML');
    step('gettypeextratimecustom', $from_id);
} elseif ($user['step'] == "gettypeextratimecustom") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('price', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['pricecustomtime'] ?? '{}', true);
    if (!is_array($eextraprice)) $eextraprice = [];
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "pricecustomtime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔒 نمایش کارت به کارت پس از اولین پرداخت" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 با روشن کردن این قابلیت پس از اولین پرداخت کاربر درگاه کارت به کارت برای کاربر فعال می شود", $keyboardverify, 'HTML');
} elseif ($datain == "onpayverify") {
    update("PaySetting", "ValuePay", "offpayverify", "NamePay", "checkpaycartfirst");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "خاموش شد", $keyboardverify);
} elseif ($datain == "offpayverify") {
    update("PaySetting", "ValuePay", "onpayverify", "NamePay", "checkpaycartfirst");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن شد", $keyboardverify);
} elseif ($text == "✏️ ویرایش کانفیگ") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $listconfig = [];
    $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = '{$panel['code_panel']}'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $listconfig[] = [$row['namerecord']];
    }
    $list_configmanual = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_configmanual['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($listconfig as $button) {
        $list_configmanual['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_manualconfig_list = json_encode($list_configmanual);
    nm_adminInstantReply($from_id, "📌 نام کانفیگی که میخواهید ویرایش نمایید را ارسال کنید ", $json_list_manualconfig_list, 'HTML');
    step("getnameedit", $from_id);
} elseif ($user['step'] == "getnameedit") {
    nm_adminInstantReply($from_id, "یکی از گزینه های زیر را انتخاب کنید ", $configedit, 'HTML');
    step("home", $from_id);
    update("user", "Processing_value_one", $text, "id", $from_id);
} elseif ($text == "مخشصات کانفیگ") {
    nm_adminInstantReply($from_id, "محتوا جدید کانفیگ را ارسال کنید", $backadmin, 'HTML');
    step("getcontentedit", $from_id);
} elseif ($user['step'] == "getcontentedit") {
    nm_adminInstantReply($from_id, "✅ ذخیره گردید.", $optionManualsale, 'HTML');
    update("manualsell", "contentrecord", $text, "namerecord", $user['Processing_value_one']);
} elseif ($text == "⬆️ افزایش گروهی قیمت") {
    nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید افزایش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
    step("getaddpricepeoductloc", $from_id);
} elseif ($user['step'] == "getaddpricepeoductloc") {
    nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود
f,n.n2", $backadmin, 'HTML');
    savedata("clear", "namepanel", $text);
    step("getagentaddpriceproduct", $from_id);
} elseif ($user['step'] == "getagentaddpriceproduct") {
    $keyboard_type_price = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "درصدی", 'callback_data' => 'typeaddprice_percent'],
                ['text' => "ثابت", 'callback_data' => 'typeaddprice_static'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 مبلغ به صورت درصدی اضافه شود یا مبلغ ثابت", $keyboard_type_price, 'HTML');
    savedata("save", "agent", $text);
    step("home", $from_id);
} elseif (preg_match('/^typeaddprice_(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    deletemessage($from_id, $message_id);
    if ($type == "static") {
        nm_adminInstantReply($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    } else {
        nm_adminInstantReply($from_id, "📌 درصدی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    }
    savedata("save", "type_price", $type);
    step("getaddpricepeoduct", $from_id);
} elseif ($user['step'] == "getaddpricepeoduct") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
    $stmt->execute();
    $product = $stmt->fetchAll();
    if ($product == false) {
        nm_adminInstantReply($from_id, "❌ محصولی برای تغییر قیمت یافت نشد", $shopkeyboard, 'HTML');
        step("home", $from_id);
        return;
    }
    if ($userdata['type_price'] == "static") {
        $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + :price WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
        $stmt->bindParam(':price', $text, PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + (price_product * :price / 100)  WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
        $stmt->bindParam(':price', $text, PDO::PARAM_STR);
    }
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت برای تمامی محصولات اعمال شد", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "⬇️ کاهش  گروهی قیمت") {
    nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید کاهش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
    step("getlowpricepeoductloc", $from_id);
} elseif ($user['step'] == "getlowpricepeoductloc") {
    nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود
f,n.n2", $backadmin, 'HTML');
    savedata("clear", "namepanel", $text);
    step("getkampricepeoductloc", $from_id);
} elseif ($user['step'] == "getkampricepeoductloc") {
    nm_adminInstantReply($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    savedata("save", "agent", $text);
    step("getkampricepeoduct", $from_id);
} elseif ($user['step'] == "getkampricepeoduct") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
    $stmt->execute();
    $product = $stmt->fetchAll();
    if ($product == false) {
        nm_adminInstantReply($from_id, "❌ محصولی برای تغییر قیمت یافت نشد", $shopkeyboard, 'HTML');
        return;
    }
    foreach ($product as $products) {
        $result = $products['price_product'] - intval($text);
        update("product", "price_product", round($result), "code_product", $products['code_product']);
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت برای تمامی محصولات اعمال شد", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "⬇️ حداقل مبلغ کارت به کارت") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaincart", $from_id);
} elseif ($user['step'] == "getmaincart") {
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancecart");
} elseif ($text == "⬆️ حداکثر مبلغ کارت به کارت") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxcart", $from_id);
} elseif ($user['step'] == "getmaxcart") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancecart");
} elseif ($text == "⬇️ حداقل مبلغ plisio") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainplisio", $from_id);
} elseif ($user['step'] == "getmainplisio") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $NowPaymentsManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceplisio");
} elseif ($text == "⬆️ حداکثر مبلغ plisio") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxplisio", $from_id);
} elseif ($user['step'] == "getmaxplisio") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $NowPaymentsManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceplisio");
} elseif ($text == "⬇️ حداقل مبلغ رمزارز آفلاین") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaindigitaltron", $from_id);
} elseif ($user['step'] == "getmaindigitaltron") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $tronnowpayments, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancedigitaltron");
} elseif ($text == "⬆️ حداکثر مبلغ رمزارز آفلاین") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxdigitaltron", $from_id);
} elseif ($user['step'] == "getmaxdigitaltron") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $tronnowpayments, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancedigitaltron");
} elseif ($text == "⬇️ حداقل مبلغ ارزی ریالی") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainiranpay1", $from_id);
} elseif ($user['step'] == "getmainiranpay1") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay1");
} elseif ($text == "⬆️ حداکثر مبلغ ارزی ریالی") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxiranpay1", $from_id);
} elseif ($user['step'] == "getmaaxiranpay1") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay1");
} elseif ($text == "⬇️ حداقل مبلغ ارزی ریالی دوم") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainiranpay2", $from_id);
} elseif ($user['step'] == "getmainiranpay2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $trnado, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay2");
} elseif ($text == "⬆️ حداکثر مبلغ ارزی ریالی دوم") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxiranpay2", $from_id);
} elseif ($user['step'] == "getmaaxiranpay2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay2");
} elseif ($text == "⬇️ حداقل مبلغ آقای پرداخت") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqayepardakht", $from_id);
} elseif ($user['step'] == "getmainaqayepardakht") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceaqayepardakht");
} elseif ($text == "⬆️ حداکثر مبلغ آقای پرداخت") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxaqayepardakht", $from_id);
} elseif ($user['step'] == "getmaaxaqayepardakht") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceaqayepardakht");
} elseif ($text == "⬇️ حداقل مبلغ زرین پال") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqzarinpal", $from_id);
} elseif ($user['step'] == "getmainaqzarinpal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancezarinpal");
} elseif ($text == "⬆️ حداکثر مبلغ زرین پال") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxzarinpal", $from_id);
} elseif ($user['step'] == "getmaaxzarinpal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancezarinpal");
} elseif ($datain == "walletaddress" && $adminrulecheck['rule'] == "administrator") {


    if (function_exists('crypto_active_wallet')) {
        $rowTrx   = crypto_active_wallet('TRX');
        $rowUsdtT = crypto_active_wallet('USDT_TRC20');
        $rowTon   = crypto_active_wallet('TON');
        $rowUsdtN = crypto_active_wallet('USDT_TON');
        $shorten = static function ($row) {
            if (!$row || empty($row['wallet_address'])) return '—';
            $w = (string) $row['wallet_address'];
            return mb_strlen($w) > 14 ? mb_substr($w, 0, 8) . '…' . mb_substr($w, -4) : $w;
        };
        $msg = "💼 <b>آدرس کیف پول هش‌چکر</b>\n\n"
             . "شبکه‌ای که می‌خواهید آدرسش را ثبت/ویرایش کنید را انتخاب کنید:\n\n"
             . "🟥 ترون (TRX): <code>" . $shorten($rowTrx) . "</code>\n"
             . "🟢 تتر روی ترون: <code>" . $shorten($rowUsdtT) . "</code>\n"
             . "🟦 تون (TON): <code>" . $shorten($rowTon) . "</code>\n"
             . "🟢 تتر روی تون: <code>" . $shorten($rowUsdtN) . "</code>\n\n"
             . "ℹ️ بعد از ثبت آدرس، کاربر هنگام انتخاب «ارز آفلاین» یک هش پرداخت می‌فرستد و ربات هر دقیقه به‌صورت خودکار از Tronscan / TonAPI تایید می‌کند.";
        $networkPickerKb = json_encode([
            'inline_keyboard' => [
                [['text' => '🟥 ترون (TRX)',     'callback_data' => 'cryptowallet_TRX'],         ['text' => '🗑', 'callback_data' => 'cryptowallet_del_TRX']],
                [['text' => '🟢 تتر روی ترون',  'callback_data' => 'cryptowallet_USDT_TRC20'],  ['text' => '🗑', 'callback_data' => 'cryptowallet_del_USDT_TRC20']],
                [['text' => '🟦 تون (TON)',     'callback_data' => 'cryptowallet_TON'],         ['text' => '🗑', 'callback_data' => 'cryptowallet_del_TON']],
                [['text' => '🟢 تتر روی تون',   'callback_data' => 'cryptowallet_USDT_TON'],    ['text' => '🗑', 'callback_data' => 'cryptowallet_del_USDT_TON']],
                [['text' => '🔍 بررسی دستی هش (آفلاین)', 'callback_data' => 'cryptocheck_manual']],
                [['text' => '❌ بستن',          'callback_data' => 'close_stat']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, $msg, $networkPickerKb, 'HTML');
    } else {


        $PaySetting = select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
        $currentWallet = $PaySetting['ValuePay'] ?? '';
        $texttronseller = "💼 لطفاً آدرس ولت ترون (TRC20) را ارسال کنید.\n\nولت فعلی شما: " . ($currentWallet === '' ? '—' : $currentWallet);
        nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
        savedata('clear', 'walletaddress_origin', 'general');
        step('walletaddresssiranpay', $from_id);
    }
} elseif (preg_match('/^cryptomemo_(yes|no)_(TON|USDT_TON)$/', (string) $datain, $cmm) && $adminrulecheck['rule'] == "administrator") {

    $memoAction = $cmm[1];
    $memoCur    = $cmm[2];
    if ($memoAction === 'no') {
        if (function_exists('crypto_save_wallet_memo')) {
            crypto_save_wallet_memo($memoCur, '');
        }
        update("user", "Processing_value", "0", "id", $from_id);
        $doneTxt = "✅ کیف پول <b>{$memoCur}</b> بدون ممو ذخیره شد.";
        if (!empty($message_id)) {
            Editmessagetext($from_id, $message_id, $doneTxt, null);
        } else {
            sendmessage($from_id, $doneTxt, null, 'HTML');
        }
        step('home', $from_id);
    } else {
        update("user", "Processing_value", $memoCur, "id", $from_id);
        $askMemoTxt = "🏷 لطفاً <b>ممو (Memo / Comment)</b> کیف پول <b>{$memoCur}</b> را ارسال کنید.\n\n"
                    . "<i>این مقدار هنگام پرداخت توسط کاربر در فیلد Memo/Comment کیف پولش وارد می‌شود.</i>";
        if (!empty($message_id)) {
            Editmessagetext($from_id, $message_id, $askMemoTxt, null);
        } else {
            sendmessage($from_id, $askMemoTxt, $backadmin, 'HTML');
        }
        step('cryptowallet_set_memo', $from_id);
    }
} elseif ($user['step'] == "cryptowallet_set_memo" && empty($datain)) {

    $memoText = trim((string) $text);
    $looksLikeNav = (
        $memoText === ''
        || mb_strlen($memoText) > 200
    );
    $memoCur = trim((string) ($user['Processing_value'] ?? ''));
    if (!in_array($memoCur, ['TON', 'USDT_TON'], true)) {
        update("user", "Processing_value", "0", "id", $from_id);
        step('home', $from_id);
        return;
    }
    if ($looksLikeNav) {
        if ($memoText === '') {
            nm_adminInstantReply($from_id, "🏠 از حالت ثبت ممو خارج شدید.", $keyboardadmin, 'HTML');
        } else {
            nm_adminInstantReply($from_id, "❌ ممو معتبر نیست (طول بیش از ۲۰۰ کاراکتر).", null, 'HTML');
            return;
        }
        update("user", "Processing_value", "0", "id", $from_id);
        step('home', $from_id);
        return;
    }
    if (function_exists('crypto_save_wallet_memo')) {
        crypto_save_wallet_memo($memoCur, $memoText);
    }
    update("user", "Processing_value", "0", "id", $from_id);
    $successMsg = "✅ ممو برای کیف پول <b>{$memoCur}</b> ذخیره شد:\n<code>" . htmlspecialchars($memoText) . "</code>";
    nm_adminInstantReply($from_id, $successMsg, $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^cryptowallet_del_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $cwd) && $adminrulecheck['rule'] == "administrator") {

    $cur = $cwd[1];
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    $label = $supported[$cur]['label'] ?? $cur;
    $ok = function_exists('crypto_delete_wallet') ? crypto_delete_wallet($cur) : false;
    if ($ok) {
        $doneTxt = "🗑 کیف پول <b>{$label}</b> حذف و خالی شد.\nاز این پس برای این ارز هیچ آدرسی تنظیم نیست و کاربر نمی‌تواند پرداخت آفلاین انجام دهد.";
    } else {
        $doneTxt = "❌ حذف کیف پول <b>{$label}</b> ناموفق بود.";
    }
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $doneTxt, null);
    } else {
        sendmessage($from_id, $doneTxt, null, 'HTML');
    }
    if (function_exists('telegram') && !empty($callback_query_id ?? null)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $ok ? '✅ حذف شد' : '❌ خطا',
            'cache_time' => 1,
        ]);
    }
    step('home', $from_id);
} elseif ($datain === "cryptocheck_manual" && $adminrulecheck['rule'] == "administrator") {

    $msg  = "🔍 <b>بررسی دستی هش تراکنش</b>\n\n";
    $msg .= "هش تراکنش (TX Hash) را در یک پیام ارسال کنید.\n\n";
    $msg .= "ربات از روی هش، ارز را روی کیف پول‌های ثبت‌شده‌ی شما تشخیص می‌دهد و وضعیت تایید را گزارش می‌کند. اگر هش هنوز در بلاکچین نباشد، خطای «tx-not-found» می‌گیرید.";
    update("user", "Processing_value", "", "id", $from_id);
    nm_adminInstantReply($from_id, $msg, $backadmin, 'HTML');
    step('cryptocheck_manual_wait', $from_id);
} elseif ($user['step'] == "cryptocheck_manual_wait" && empty($datain)) {

    $hash = trim((string) $text);
    if ($hash === '' || mb_strlen($hash) < 20 || preg_match('/[\x{0600}-\x{06FF}\s]/u', $hash)) {
        nm_adminInstantReply($from_id, "🏠 از حالت بررسی دستی خارج شدید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $hash = preg_replace('/^0x/i', '', $hash);
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    $usdtTrc20Jetton = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    $usdtTonJetton   = 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs';
    $results = [];
    foreach (array_keys($supported) as $cur) {
        $wallet = function_exists('crypto_active_wallet') ? crypto_active_wallet($cur) : null;
        $addr = is_array($wallet) ? trim((string)($wallet['wallet_address'] ?? '')) : '';
        if ($addr === '') {
            $results[$cur] = ['ok' => false, 'reason' => 'no-wallet-configured'];
            continue;
        }

        $expectedAmount = 0.0;
        if ($cur === 'TRX' && function_exists('crypto_check_tron_tx')) {
            $r = crypto_check_tron_tx($hash, $addr, $expectedAmount, null, false);
        } elseif ($cur === 'USDT_TRC20' && function_exists('crypto_check_tron_tx')) {
            $r = crypto_check_tron_tx($hash, $addr, $expectedAmount, $usdtTrc20Jetton, false);
        } elseif ($cur === 'TON' && function_exists('crypto_check_ton_tx')) {
            $r = crypto_check_ton_tx($hash, $addr, $expectedAmount, null, false, '');
        } elseif ($cur === 'USDT_TON' && function_exists('crypto_check_ton_tx')) {
            $r = crypto_check_ton_tx($hash, $addr, $expectedAmount, $usdtTonJetton, false, '');
        } else {
            $r = ['ok' => false, 'reason' => 'helper-missing'];
        }
        $results[$cur] = is_array($r) ? $r : ['ok' => false, 'reason' => 'unknown'];
    }
    $lines = ["📋 <b>نتیجه بررسی دستی</b>\n", "🔗 هش: <code>" . htmlspecialchars($hash) . "</code>\n"];
    $foundAny = false;
    foreach ($results as $cur => $r) {
        $label = $supported[$cur]['label'] ?? $cur;
        $reason = (string)($r['reason'] ?? '');
        if (!empty($r['ok']) || $reason === 'amount-mismatch') {
            $foundAny = true;
            $amt = $r['detail']['amount'] ?? '?';
            $sender = $r['detail']['sender'] ?? '';
            $statusEmoji = !empty($r['ok']) ? '✅' : '⚠️';
            $statusTxt = !empty($r['ok']) ? 'تایید (بدون مقایسه مبلغ)' : 'گیرنده درست، مبلغ متفاوت';
            $lines[] = "{$statusEmoji} <b>{$label}</b>: {$statusTxt}";
            $lines[] = "   • مقدار: <code>{$amt}</code>";
            if ($sender !== '') $lines[] = "   • فرستنده: <code>" . htmlspecialchars((string)$sender) . "</code>";
            $explorer = function_exists('crypto_explorer_url') ? crypto_explorer_url($cur, $hash) : '';
            if ($explorer !== '') $lines[] = "   🔎 <a href=\"" . htmlspecialchars($explorer, ENT_QUOTES) . "\">مشاهده در اکسپلورر</a>";
        } else {
            $lines[] = "❌ <b>{$label}</b>: " . htmlspecialchars($reason);
        }
    }
    if (!$foundAny) {
        $lines[] = "\nℹ️ هیچ‌کدام از کیف‌پول‌های شما این تراکنش را در شبکه‌ی متناظر دریافت نکرده‌اند، یا هش روی شبکه‌ها معتبر نیست.";
    }
    nm_adminInstantReply($from_id, implode("\n", $lines), $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^cryptowallet_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $cwm) && $adminrulecheck['rule'] == "administrator") {

    $cur = $cwm[1];
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    $label = $supported[$cur]['label'] ?? $cur;
    $row = function_exists('crypto_active_wallet') ? crypto_active_wallet($cur) : null;
    $cur_now = $row['wallet_address'] ?? '';
    $hintNet = $supported[$cur]['network'] ?? '';
    $hint = $hintNet === 'TRON'
        ? "ℹ️ آدرس باید با حرف <code>T</code> شروع شود و ۳۴ کاراکتر باشد (TRC20)."
        : "ℹ️ آدرس TON معمولاً با <code>EQ</code>، <code>UQ</code> یا <code>kQ</code> شروع می‌شود و ۴۸ کاراکتر است.";
    $msg = "💼 شبکه انتخاب‌شده: <b>{$label}</b>\n\n"
         . "آدرس فعلی: <code>" . ($cur_now === '' ? '—' : htmlspecialchars($cur_now)) . "</code>\n\n"
         . "آدرس کیف پول جدید را ارسال کنید:\n\n{$hint}";

    update("user", "Processing_value", $cur, "id", $from_id);
    nm_adminInstantReply($from_id, $msg, $backadmin, 'HTML');
    step('cryptowallet_set', $from_id);
} elseif ($user['step'] == "cryptowallet_set" && empty($datain)) {


    $trimmed = trim((string) $text);
    $looksLikeNav = (
        $trimmed === ''
        || mb_strlen($trimmed) < 30
        || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $trimmed)
        || strpos($trimmed, ' ') !== false
    );
    if ($looksLikeNav) {
        update("user", "Processing_value", "0", "id", $from_id);
        step('home', $from_id);
        if ($trimmed !== '') {
            nm_adminInstantReply($from_id, "🏠 از حالت ثبت آدرس خارج شدید.", $keyboardadmin, 'HTML');
        }
        return;
    }
    $cur = trim((string) ($user['Processing_value'] ?? ''));
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    if ($cur === '' || !isset($supported[$cur])) {
        nm_adminInstantReply($from_id, "❌ خطای داخلی: ارز نامشخص است. مجدداً از منوی آدرس ولت اقدام کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $address = trim((string) $text);
    $network = $supported[$cur]['network'];
    if (!function_exists('crypto_validate_address') || !crypto_validate_address($address, $network)) {
        $exHint = $network === 'TRON'
            ? "آدرس TRC20 معتبر مثل <code>TR7N…</code>."
            : "آدرس TON معتبر مثل <code>EQ…</code> یا <code>UQ…</code>.";
        nm_adminInstantReply($from_id, "❌ آدرس وارد شده برای شبکه <b>{$network}</b> معتبر نیست.\n\n{$exHint}", null, 'HTML');
        return;
    }
    $ok = function_exists('crypto_save_wallet') ? crypto_save_wallet($cur, $address) : false;
    if (!$ok) {
        nm_adminInstantReply($from_id, "❌ ذخیره‌سازی با خطا مواجه شد. لاگ سرور را بررسی کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }


    $label = $supported[$cur]['label'];
    $okMsg = "✅ آدرس کیف پول <b>{$label}</b> با موفقیت ثبت شد.\n\n<code>" . htmlspecialchars($address) . "</code>";
    if ($cur === 'TRX') {
        $okMsg .= "\n\nℹ️ آدرس تتر روی ترون (USDT-TRC20) جداگانه است؛ در صورت نیاز از منوی «🟢 تتر روی ترون» آن را تنظیم کنید.";
    } elseif ($cur === 'TON') {
        $okMsg .= "\n\nℹ️ آدرس تتر روی تون (USDT-TON) جداگانه است؛ در صورت نیاز از منوی «🟢 تتر روی تون» آن را تنظیم کنید.";
    }
    nm_adminInstantReply($from_id, $okMsg, $keyboardadmin, 'HTML');

    if ($cur === 'TON' || $cur === 'USDT_TON') {
        update("user", "Processing_value", $cur, "id", $from_id);
        $memoAskMsg = "🏷 <b>آیا این کیف پول ممو (Memo / Comment) دارد؟</b>\n\n"
                    . "<i>اگر آدرس از یک صرافی است (مثل نوبیتکس / MEXC)، معمولاً ممو لازم دارد.\n"
                    . "آدرس‌های شخصی Tonkeeper معمولاً نیاز ندارند.</i>";
        $memoAskKb = json_encode([
            'inline_keyboard' => [
                [['text' => '✅ دارم — وارد می‌کنم', 'callback_data' => 'cryptomemo_yes_' . $cur]],
                [['text' => '❌ ندارم',              'callback_data' => 'cryptomemo_no_'  . $cur]],
            ],
        ], JSON_UNESCAPED_UNICODE);
        sendmessage($from_id, $memoAskMsg, $memoAskKb, 'HTML');
        step('home', $from_id);
        return;
    }

    update("user", "Processing_value", "0", "id", $from_id);
    step('home', $from_id);
} elseif ($user['step'] == "walletaddresssiranpay") {
    $walletInput = trim((string) $text);


    if ($walletInput === ''
        || mb_strlen($walletInput) < 30
        || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $walletInput)
        || strpos($walletInput, ' ') !== false
    ) {
        step('home', $from_id);
        if ($walletInput !== '') {
            nm_adminInstantReply($from_id, "🏠 از حالت ثبت آدرس ولت خارج شدید.", $keyboardadmin, 'HTML');
        }
        return;
    }

    $userRecord = select("user", "*", "id", $from_id, "select");
    $processingData = [];
    if ($userRecord && isset($userRecord['Processing_value'])) {
        $decodedProcessing = json_decode($userRecord['Processing_value'], true);
        if (is_array($decodedProcessing)) {
            $processingData = $decodedProcessing;
        }
    }

    $walletOrigin = $processingData['walletaddress_origin'] ?? 'general';
    $invalidKeyboard = $walletOrigin === 'trnado' ? $trnado : $backadmin;

    if ($walletInput === '' || !preg_match('/^T[a-zA-Z0-9]{33}$/', $walletInput)) {
        nm_adminInstantReply($from_id, "❌ آدرس ولت وارد شده نامعتبر است. لطفاً آدرس TRC20 معتبر ارسال کنید.", $invalidKeyboard, 'HTML');
        return;
    }

    $standardizedWallet = strtoupper($walletInput);

    $successKeyboard = $walletOrigin === 'trnado' ? $trnado : $keyboardadmin;

    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $successKeyboard, 'HTML');
    update("PaySetting", "ValuePay", $standardizedWallet, "NamePay", "walletaddress");
    update("user", "Processing_value", '{}', "id", $from_id);
    step('home', $from_id);
} elseif ($text == "💼 ثبت آدرس ولت ترون (TRC20)" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
    $currentWallet = $PaySetting['ValuePay'] ?? '';
    $texttronseller = "💼 لطفاً آدرس ولت ترون (TRC20) مرتبط با درگاه ترنادو را ارسال کنید.\n\nولت فعلی شما: {$currentWallet}";
    nm_adminInstantReply($from_id, $texttronseller, $trnado, 'HTML');
    savedata('clear', 'walletaddress_origin', 'trnado');
    step('walletaddresssiranpay', $from_id);
} elseif ($text == "api  درگاه ارزی ریالی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiiranpay", "select")['ValuePay'];
    $texttronseller = "📌 کد api خود را ارسال نمایید.

        مرچنت فعلی شما : $PaySetting";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('apiiranpay', $from_id);
} elseif ($user['step'] == "apiiranpay") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $iranpaykeyboard, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiiranpay");
    step('home', $from_id);
} elseif ($text == "⬇️ حداقل مبلغ ارزی ریالی سوم") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("minbalanceiranpay", $from_id);
} elseif ($user['step'] == "minbalanceiranpay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $iranpaykeyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay");
} elseif ($text == "⬆️ حداکثر مبلغ ارزی ریالی سوم") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("maxbalanceiranpay", $from_id);
} elseif ($user['step'] == "maxbalanceiranpay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $iranpaykeyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay");
} elseif ($text == "📍 حداقل حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 حداقل حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetmaineExtra', $from_id);
} elseif ($user['step'] == "GetmaineExtra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "mainvalume", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramain', $from_id);
} elseif ($user['step'] == "gettypeextramain") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['mainvolume'], true);
    $eextraprice[$text] = $userdata['mainvalume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "mainvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 حداکثر حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 حداکثر حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetmaxeExtra', $from_id);
} elseif ($user['step'] == "GetmaxeExtra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "maxvolume", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramax', $from_id);
} elseif ($user['step'] == "gettypeextramax") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxvolume'], true);
    $eextraprice[$text] = $userdata['maxvolume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 حداقل زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 حداقل زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('Getmaintime', $from_id);
} elseif ($user['step'] == "Getmaintime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "maintime", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramaintime', $from_id);
} elseif ($user['step'] == "gettypeextramaintime") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maintime'], true);
    $eextraprice[$text] = $userdata['maintime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maintime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 حداکثر زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 حداکثر زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('Getmaxtime', $from_id);
} elseif ($user['step'] == "Getmaxtime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "maxtime", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramaxtime', $from_id);
} elseif ($user['step'] == "gettypeextramaxtime") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxtime'], true);
    $eextraprice[$text] = $userdata['maxtime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxtime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔼 اضافه کردن دپارتمان") {
    nm_adminInstantReply($from_id, "📌 ایدی عددی ادمینی که میخواهید پیام ها به آن ادمین ارسال شود را بفرستید", $backadmin, 'HTML');
    step("getidadmindep", $from_id);
} elseif ($user['step'] == "getidadmindep") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata('clear', 'idadmin', $text);
    nm_adminInstantReply($from_id, "📌 نام دپارتمان را ارسال نمایید", $backadmin, 'HTML');
    step("getdeparteman", $from_id);
} elseif ($user['step'] == "getdeparteman") {
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT IGNORE INTO departman (idsupport,name_departman) VALUES (:idsupport,:name_departman)");
    $stmt->bindParam(':idsupport', $userdata['idadmin']);
    $stmt->bindParam(':name_departman', $text);
    $stmt->execute();
    step("home", $from_id);
    nm_adminInstantReply($from_id, "📌 دپارتمان با موفقیت اضافه گردید.", $supportcenter, 'HTML');
} elseif ($text == "🔽 حذف کردن دپارتمان") {
    $countdeparteman = select("departman", "*", null, null, "count");
    if ($countdeparteman == 0) {
        nm_adminInstantReply($from_id, "❌ دپارتمانی برای حذف وجود ندارد.", $departemanslist, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "📌 نوع دپارتمان را برای حذف ارسال کنید.", $departemanslist, 'HTML');
    step("getremovedep", $from_id);
} elseif ($user['step'] == "getremovedep") {
    $stmt = $pdo->prepare("DELETE FROM departman WHERE name_departman = ?");
    $stmt->bindParam(1, $text);
    $stmt->execute();
    nm_adminInstantReply($from_id, "📌 بخش مورد نظر حذف گردید.", $supportcenter, 'HTML');
    step("home", $from_id);
} elseif ($text == "⚙️ تنظیمات سرویس" && $adminrulecheck['rule'] == "administrator") {
    $textsetservice = "📌 برای تنظیم سرویس یک کانفیگ در پنل خود ساخته و  سرویس هایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کانفیگ را ارسال نمایید";
    nm_adminInstantReply($from_id, $textsetservice, $backadmin, 'HTML');
    step('getservceid', $from_id);
} elseif ($user['step'] == "getservceid") {
    $userdata = json_decode(getuserm($text, $user['Processing_value'])['body'], true);
    if (isset($userdata['detail']) and $userdata['detail'] == "User not found") {
        nm_adminInstantReply($from_id, "کاربر در پنل وجود ندارد", null, 'HTML');
        return;
    }
    update("marzban_panel", "proxies", json_encode($userdata['service_ids']), "name_panel", $user['Processing_value']);
    step("home", $from_id);
    nm_adminInstantReply($from_id, "✅ اطلاعات با موفقیت تنظیم گردید", $optionmarzneshin, 'HTML');
} elseif ($text == "👤 تنظیم آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    $textcart = "📌 نام کاربری خود را بدون @ برای پشتیبانی  ارسال کنید\n\n{$setting['id_support']}";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('idsupportset', $from_id);
} elseif ($user['step'] == "idsupportset") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $supportcenter, 'HTML');
    update("setting", "id_support", $text, null, null);
    step('home', $from_id);
} elseif ($text == "📚 تنظیم آموزش کارت به کارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpcart", $from_id);
} elseif ($user['step'] == "gethelpcart") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "2", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش nowpayment" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpnowpayment", $from_id);
} elseif ($user['step'] == "gethelpnowpayment") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "2", "NamePay", "helpnowpayment");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $nowpayment_setting_keyboard, 'HTML');
} elseif ($text == "📚 تنظیم آموزش پرفکت مانی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpperfect", $from_id);
} elseif ($user['step'] == "gethelpperfect") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpperfectmony");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش plisio" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpplisio", $from_id);
} elseif ($user['step'] == "gethelpplisio") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpplisio");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش ارزی ریالی اول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpiranpay1", $from_id);
} elseif ($user['step'] == "gethelpiranpay1") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش ارزی ریالی  دوم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpiranpay2", $from_id);
} elseif ($user['step'] == "helpiranpay2") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay2");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش ارزی ریالی سوم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpiranpay3", $from_id);
} elseif ($user['step'] == "helpiranpay3") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay3");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش درگاه اقای پرداخت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpaqayepardakht", $from_id);
} elseif ($user['step'] == "helpaqayepardakht") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش زرین پال" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpzarinpal", $from_id);
} elseif ($user['step'] == "helpzarinpal") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش  ارزی افلاین" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpofflinearze", $from_id);
} elseif ($user['step'] == "helpofflinearze") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpofflinearze");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "💰 مبلغ عضویت نمایندگی") {
    nm_adminInstantReply($from_id, "📌 قیمت درخواست  عضویت  برای نمایندگی را ارسال کنید.", $backadmin, 'HTML');
    step("getpricereqagent", $from_id);
} elseif ($user['step'] == "getpricereqagent") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ تغییرات با موفقیت ذخیره گردید", $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "agentreqprice", $text, null, null);
} elseif ($text == "🤖 تایید رسید  بدون بررسی" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
    if ($paymentverify == "onautoconfirm") {
        nm_adminInstantReply($from_id, "❌ ابتدا تایید خودکار را خاموش کنید.", null, 'HTML');
        return;
    }
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 با فعال کردن این قابلیت  در زمان هایی که آنلاین نیستید ربات بصورت خودکار تمامی تراکنش های کارت به کارت را تایید می کند سپس بعد از آنلاین شدن شما رسید ها را بررسی میکنید سپس اگر رسید فیک  ارسال شده تراکنش را کنسل میکنید", $keyboardverify, 'HTML');
} elseif ($datain == "onauto") {
    update("PaySetting", "ValuePay", "offauto", "NamePay", "autoconfirmcart");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "خاموش شد", $keyboardverify);
} elseif ($datain == "offauto") {
    update("PaySetting", "ValuePay", "onauto", "NamePay", "autoconfirmcart");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن شد", $keyboardverify);
} elseif (preg_match('/transferaccount_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    nm_adminInstantReply($from_id, "آیدی عددی کاربری که میخواهید تمامی اطلاعات به آن کاربر منتقل شود را ارسال نمایید
    توجه داشتید باشید در کاربر مقصد در صورت داشتن موجودی حذف خواهد شد", $backadmin, 'HTML');
    step("getidfortransfers", $from_id);
} elseif ($user['step'] == "getidfortransfers") {
    if (!userExists($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
        return;
    }
    if ($text == $user['Processing_value']) {
        nm_adminInstantReply($from_id, "❌ شما نمی توانید اطلاعات به کاربر فعلی منتقل کنید", $keyboardadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "اطلاعات با موفقیت به حساب کاربری جدید منتقل گردید", $keyboardadmin, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id_user");
    $stmt->bindParam(':id_user', $text, PDO::PARAM_STR);
    $stmt->execute();
    update("user", "id", $text, "id", $user['Processing_value']);
    update("Payment_report", "id_user", $text, "id_user", $user['Processing_value']);
    update("invoice", "id_user", $text, "id_user", $user['Processing_value']);
    update("support_message", "iduser", $text, "iduser", $user['Processing_value']);
    update("service_other", "id_user", $text, "id_user", $user['Processing_value']);
    update("Giftcodeconsumed", "id_user", $text, "id_user", $user['Processing_value']);
    step("home", $from_id);
} elseif ($text == "🖼 پس زمینه کیوآرکد") {
    nm_adminInstantReply($from_id, "تصویر خود را برای پس زمینه ارسال کنید", $backadmin, 'HTML');
    step("getimagebackgroundqr", $from_id);
} elseif ($user['step'] == "getimagebackgroundqr") {
    if (!$photo) {
        nm_adminInstantReply($from_id, "تصویر نامعتبر است", $backadmin, 'HTML');
        return;
    }
    $response = getFileddire($photoid);
    if ($response['ok']) {
        $filePath = $response['result']['file_path'];
        $fileUrl = "https://api.telegram.org/file/bot$APIKEY/$filePath";
        $fileContent = file_get_contents($fileUrl);

        $projectRoot = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
        $written = 0;
        $written += (int) @file_put_contents($projectRoot . '/custom.jpg',    $fileContent);
        $written += (int) @file_put_contents($projectRoot . '/images.jpg',    $fileContent);
        $written += (int) @file_put_contents($projectRoot . '/images.jpeg',   $fileContent);
        if ($written > 0) {
            nm_adminInstantReply($from_id, "🖼 پس زمینه با موفقیت تنظیم گردید (همه‌جا اعمال شد: ربات، مینی‌اپ، کیف‌پول‌های ارز)", $setting_panel, 'HTML');
        } else {
            nm_adminInstantReply($from_id, "❌ ذخیره‌سازی فایل ناموفق بود — دسترسی نوشتن روی پوشه‌ی روت پروژه را بررسی کنید.", $setting_panel, 'HTML');
        }
        step("home", $from_id);
    }
} elseif ($text == "⚙️ تنظیم پروتکل و اینباند" || $text == "🎛 تنظیم نام گروه" || $text == "⚙️ تنظیم نود") {
    if ($text == "🎛 تنظیم نام گروه") {
        $textsetprotocol = "📌 نام گروهی که بصورت پیشفرض می خواهید از آن ساخته شود را ارسال نمایید.";
    } elseif ($text == "⚙️ تنظیم نود") {
        $textsetprotocol = "📌 برای تنظیم نود یک کاربر در پنل خود ساخته و  نودهایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کاربر را ارسال نمایید";
    } else {
        $textsetprotocol = "📌 برای تنظیم اینباند  و پروتکل باید یک کانفیگ در پنل خود ساخته و  پروتکل و اینباند هایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کانفیگ را ارسال نمایید";
    }
    nm_adminInstantReply($from_id, $textsetprotocol, $backadmin, 'HTML');
    step("setinboundandprotocol", $from_id);
} elseif ($user['step'] == "setinboundandprotocol") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($panel['type'] == "marzban") {
        if ((string)($panel['version_panel'] ?? '0') === '1') {
            $DataUserOut = getuser($text, $user['Processing_value']);
            if (!empty($DataUserOut['error'])) {
                nm_adminInstantReply($from_id, $DataUserOut['error'], null, 'HTML');
                return;
            }
            if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                return;
            }
            $DataUserOut = json_decode($DataUserOut['body'], true);
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxy_settings'])) {
                nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            foreach ($DataUserOut['proxy_settings'] as $key => &$value) {
                if ($key == "shadowsocks") {
                    unset($DataUserOut['proxy_settings'][$key]['password']);
                } elseif ($key == "trojan") {
                    unset($DataUserOut['proxy_settings'][$key]['password']);
                } else {
                    unset($DataUserOut['proxy_settings'][$key]['id']);
                }
                if (count($DataUserOut['proxy_settings'][$key]) == 0) {
                    $DataUserOut['proxy_settings'][$key] = new stdClass();
                }
            }
            update("marzban_panel", "inbounds", json_encode($DataUserOut['group_ids']), "name_panel", $user['Processing_value']);
            update("marzban_panel", "proxies", json_encode($DataUserOut['proxy_settings'], true), "name_panel", $user['Processing_value']);
        } else {
            $DataUserOut = getuser($text, $user['Processing_value']);
            if (!empty($DataUserOut['error'])) {
                nm_adminInstantReply($from_id, $DataUserOut['error'], null, 'HTML');
                return;
            }
            if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                return;
            }
            $DataUserOut = json_decode($DataUserOut['body'], true);
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            foreach ($DataUserOut['proxies'] as $key => &$value) {
                if ($key == "shadowsocks") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } elseif ($key == "trojan") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } else {
                    unset($DataUserOut['proxies'][$key]['id']);
                }
                if (count($DataUserOut['proxies'][$key]) == 0) {
                    $DataUserOut['proxies'][$key] = new stdClass();
                }
            }
            update("marzban_panel", "inbounds", json_encode($DataUserOut['inbounds']), "name_panel", $user['Processing_value']);
            update("marzban_panel", "proxies", json_encode($DataUserOut['proxies'], true), "name_panel", $user['Processing_value']);
        }
    } elseif ($panel['type'] == "s_ui") {
        $data = GetClientsS_UI($text, $panel['name_panel']); {
            if (count($data) == 0) {
                nm_adminInstantReply($from_id, "❌ یوزر در پنل وجود ندارد.", $options_ui, 'HTML');
                return;
            }
            $servies = [];
            foreach ($data['inbounds'] as $service) {
                $servies[] = $service;
            }
            update("marzban_panel", "proxies", json_encode($servies, true), "name_panel", $user['Processing_value']);
            syncSuiInboundsWithProxies($panel['id'] ?? null);
        }
    } elseif ($panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
        update("marzban_panel", "proxies", $text, "name_panel", $user['Processing_value']);
    }
    if ($panel['type'] == "ibsng") {
        nm_adminInstantReply($from_id, "✅ نام گروه با موفقیت تنظیم گردید.", $optionibsng, 'HTML');
    } elseif ($panel['type'] == "mikrotik") {
        nm_adminInstantReply($from_id, "✅ نام گروه با موفقیت تنظیم گردید.", $option_mikrotik, 'HTML');
    } else {
        nm_adminInstantReply($from_id, "✅ اینباند و پروتکل های شما با موفقیت تنظیم گردیدند.", $optionMarzban, 'HTML');
    }
    step("home", $from_id);
} elseif ($text == "🔋 وضعیت تمدید" && $adminrulecheck['rule'] == "administrator") {
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['activepanel'], $keyboardstatus, 'HTML');
} elseif ($datain == "on_extend") {
    update("marzban_panel", "status_extend", "off_extend", "name_panel", $user['Processing_value']);
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepanelStatusOff'], $keyboardstatus);
} elseif ($datain == "off_extend") {
    update("marzban_panel", "status_extend", "on_extend", "name_panel", $user['Processing_value']);
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepaneltatuson'], $keyboardstatus);
} elseif ((preg_match('/confirmchannel-(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['joinchannel'] == "active") {
        nm_adminInstantReply($from_id, "✍️ کاربر از قبل تایید شده است", null, 'HTML');
        return;
    }
    update("user", "joinchannel", "active", "id", $iduser);
    nm_adminInstantReply($from_id, "📌 کاربر از این پس بدون عضویت در کانال می تواند در ربات فعالیت داشته باشد", $keyboardadmin, 'HTML');
} elseif ((preg_match('/zerobalance-(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    update("user", "Balance", "0", "id", $iduser);
    nm_adminInstantReply($from_id, "موجودی کاربر به مبلغ {$userdata['Balance']} صفر گردید", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeadmin_(\w+)/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $idadmin = trim($dataget[1]);
    $mainAdminId = trim((string) $adminnumber);
    if ($idadmin === $mainAdminId) {
        nm_adminInstantReply($from_id, "❌ امکان حذف ادمین اصلی وجود ندارد", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM admin WHERE TRIM(id_admin) = :id_admin");
    $stmt->bindParam(':id_admin', $idadmin, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        nm_adminInstantReply($from_id, "⚠️ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ ادمین با موفقیت حذف گردید", null, 'HTML');
}


elseif ($text == "🫣 مخفی کردن پنل برای یک کاربر" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آیدی عددی کاربر را برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('getuserhide', $from_id);
} elseif ($user['step'] == "getuserhide") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "✅ پنل با موفقیت برای کاربر مخفی گردید");
    if ($typepanel['hide_user'] == null) {
        $hideuserid = [];
    } else {
        $hideuserid = json_decode($typepanel['hide_user'], true);
    }
    $hideuserid[] = $text;
    $hideuserid = json_encode($hideuserid);
    update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "❌  حذف کاربر از لیست مخفی شدگان" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آیدی عددی کاربر را برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('getuserhideforremove', $from_id);
} elseif ($user['step'] == "getuserhideforremove") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    step("home", $from_id);
    if ($typepanel['hide_user'] == null) {
        outtypepanel($typepanel['type'], "❌ هیچ کاربری در لیست مخفی شدگان وجود ندارد");
        return;
    }
    $hideuserid = json_decode($typepanel['hide_user'], true);
    if (count($hideuserid) == 0) {
        outtypepanel($typepanel['type'], "❌  کاربر در لیست وجود ندارد");
        return;
    }
    if (!in_array($text, $hideuserid)) {
        outtypepanel($typepanel['type'], "❌ کاربر در لیست وجود ندارد.");
        return;
    }
    $key = array_search($text, $hideuserid);
    if ($key !== false) {
        unset($hideuserid[$key]);
        $hideuserid = array_values($hideuserid);
    }
    $hideuserid = json_encode($hideuserid);
    update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
    outtypepanel($typepanel['type'], "✅  کاربر با موفقیت از لیست حذف گردید.");
} elseif ($datain == "scoresetting") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $lottery, 'HTML');
} elseif ($text == "1️⃣ تنظیم جایزه نفر اول") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary", $from_id);
} elseif ($user['step'] == "getonelotary") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['one'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($text == "2️⃣ تنظیم جایزه نفر دوم") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary2", $from_id);
} elseif ($user['step'] == "getonelotary2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['tow'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($text == "3️⃣ تنظیم جایزه نفر سوم") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary3", $from_id);
} elseif ($user['step'] == "getonelotary3") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['theree'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($datain == "gradonhshans") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $wheelkeyboard, 'HTML');
} elseif ($text == "🎲 مبلغ برنده شدن کاربر") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricewheel", $from_id);
} elseif ($user['step'] == "getpricewheel") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $wheelkeyboard, 'HTML');
    step("home", $from_id);
    update("setting", "wheelـluck_price", $text, null, null);
} elseif ($text == "💵 رسید های تایید نشده") {
    $sql = "SELECT * FROM Payment_report WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $list_payment = $stmt->fetchAll();
    $list_payment_count = $stmt->rowCount();
    if ($list_payment_count == 0) {
        nm_adminInstantReply($from_id, "❌ هیچ پرداخت تایید نشده ای ندارید.", null, 'HTML');
        return;
    }
    $list_pay = ['inline_keyboard' => []];
    foreach ($list_payment as $payment) {
        $list_pay['inline_keyboard'][] = [
            ['text' => $payment['id_user'], 'callback_data' => "checkpay"]
        ];
        $list_pay['inline_keyboard'][] = [
            ['text' => "✅", 'callback_data' => "Confirm_pay_{$payment['id_order']}"],
            ['text' => "❌", 'callback_data' => "reject_pay_{$payment['id_order']}"],
            ['text' => "📝", 'callback_data' => "showinfopay_{$payment['id_order']}"],
            ['text' => "🗑", 'callback_data' => "removeresid_{$payment['id_order']}"],
        ];
        $list_pay['inline_keyboard'][] = [
            ['text' => "💸💸💸💸💸💸💸💸💸", 'callback_data' => "checkpay"]
        ];
    }
    $list_pay['inline_keyboard'][] = [
        ['text' => "❌ حذف همه رسید ها", 'callback_data' => "removeresid"]
    ];
    $list_pay_json = json_encode($list_pay, JSON_UNESCAPED_UNICODE);
    if ($list_pay_json === false) {
        error_log('Failed to encode pending receipts keyboard: ' . json_last_error_msg());
        $list_pay_json = json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE);
    }
    nm_adminInstantReply($from_id, "📌 پرداخت های تایید نشده کارت به کارت
در این بخش میتوانید پرداخت های تایید نشده مشاهده و تایید یا رد نمایید.
❌ : رد کردن پرداخت
✅ : تایید پرداخت
📝 مشخصات پرداخت
🗑 : حذف رسید بدون اطلاع کاربر", $list_pay_json, 'HTML');
} elseif ($datain == "removeresid") {
    deletemessage($from_id, $message_id);
    nm_adminInstantReply($from_id, "✅  تمامی رسید ها با موفقیت حذف شدند ", null, 'HTML');
    $sql = "UPDATE Payment_report SET payment_Status = 'reject',dec_not_confirmed = 'remove_all' WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} elseif (preg_match('/showinfopay_(\w+)/', $datain, $dataget)) {
    $idorder = $dataget[1];
    $paymentUser = select("Payment_report", "*", "id_order", $idorder, "select");
    if ($paymentUser == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $text_order = "🛒 شماره پرداخت  :  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ شناسه کاربر : <code>{$paymentUser['id_user']}</code>
💰 مبلغ پرداختی : {$paymentUser['price']} تومان
⚜️ وضعیت پرداخت : {$paymentUser['payment_Status']}
⭕️ روش پرداخت : {$paymentUser['Payment_Method']}
📆 تاریخ خرید :  {$paymentUser['time']}";
    nm_adminInstantReply($from_id, $text_order, null, 'HTML');
} elseif ($text == "🎛 تنظیم اینباند") {
    nm_adminInstantReply($from_id, "📌 در صورتی که پنل مرزبان  یا مرزنشین هستید یک نام کاربری کانفیگ از پنل کپی و ارسال نمایید در غیراینصورت برای پنل های ثنایی و علیرضا شناسه اینباند را ارسال نمایید", $backadmin, 'HTML');
    step("getdatainboundproduct", $from_id);
} elseif ($user['step'] == "getdatainboundproduct") {
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
    $datainbound = "";
    if ($marzban_list_get['type'] == "marzban") {
        $DataUserOut = getuser($text, $marzban_list_get['name_panel']);
        if (!empty($DataUserOut['error'])) {
            nm_adminInstantReply($from_id, $DataUserOut['error'], null, 'HTML');
            return;
        }
        if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
            nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
            return;
        }
        $DataUserOut = json_decode($DataUserOut['body'], true);
        if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
            nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
            return;
        }
        foreach ($DataUserOut['proxies'] as $key => &$value) {
            if ($key == "shadowsocks") {
                unset($DataUserOut['proxies'][$key]['password']);
            } elseif ($key == "trojan") {
                unset($DataUserOut['proxies'][$key]['password']);
            } else {
                unset($DataUserOut['proxies'][$key]['id']);
            }
            if (count($DataUserOut['proxies'][$key]) == 0) {
                $DataUserOut['proxies'][$key] = new stdClass();
            }
        }
        $stmt = $pdo->prepare("UPDATE product SET proxies = :proxies WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
        $proxies_json = json_encode($DataUserOut['proxies']);
        $stmt->bindParam(':proxies', $proxies_json);
        $stmt->bindParam(':name_product', $user['Processing_value']);
        $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
        $stmt->bindParam(':agent', $user['Processing_value_tow']);
        $stmt->execute();
        $datainbound = json_encode($DataUserOut['inbounds']);
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $userdata = json_decode(getuserm($text, $marzban_list_get['name_panel'])['body'], true);
        if (isset($userdata['detail']) and $userdata['detail'] == "User not found") {
            nm_adminInstantReply($from_id, "کاربر در پنل وجود ندارد", null, 'HTML');
            return;
        }
        $datainbound = json_encode($userdata['service_ids'], true);
    } elseif ($marzban_list_get['type'] == "x-ui_single" || $marzban_list_get['type'] == "alireza_single") {
        $datainbound = $text;
    } elseif ($marzban_list_get['type'] == "s_ui") {
        $data = GetClientsS_UI($text, $panel['name_panel']);
        if (count($data) == 0) {
            nm_adminInstantReply($from_id, "❌ یوزر در پنل وجود ندارد.", $options_ui, 'HTML');
            return;
        }
        $servies = [];
        foreach ($data['inbounds'] as $service) {
            $servies[] = $service;
        }
        $datainbound = json_encode($servies);
    } elseif ($marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $datainbound = $text;
    } else {
        nm_adminInstantReply($from_id, "❌ برای این پنل قابلیت تعریف اینباند وجود ندارد", $shopkeyboard, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("UPDATE product SET inbounds = :inbounds WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':inbounds', $datainbound);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "iploginset") {
    $setting_row = select("setting", "*", null, null, "select");
    $raw_ip = $setting_row['iplogin'] ?? '';
    $ip_list = [];
    $iplogin_unlimited = false;
    if ($raw_ip === '*' || $raw_ip === 'all' || $raw_ip === 'unlimited') {
        $iplogin_unlimited = true;
    } elseif (!empty($raw_ip) && $raw_ip !== '0') {
        $decoded = json_decode($raw_ip, true);
        if (is_array($decoded)) {
            if (in_array('*', $decoded, true) || in_array('all', $decoded, true) || in_array('unlimited', $decoded, true)) {
                $iplogin_unlimited = true;
            } else {
                $ip_list = $decoded;
            }
        } elseif (filter_var($raw_ip, FILTER_VALIDATE_IP)) {
            $ip_list = [$raw_ip];
        }
    }

    $msg = "🛡 <b>تنظیم آیپی ورود</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    if ($iplogin_unlimited) {
        $msg .= "♾️ <b>حالت نامحدود فعال است.</b>\n";
        $msg .= "ورود به پنل وب از هر آیپی‌ای آزاد است.\n";
    } elseif (empty($ip_list)) {
        $msg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n";
        $msg .= "در این حالت <b>ورود به پنل وب برای همه مسدود است.</b>\n";
    } else {
        $msg .= "📋 آیپی‌های مجاز ورود:\n";
        foreach ($ip_list as $i => $ip) {
            $msg .= ($i + 1) . ". <code>" . htmlspecialchars($ip) . "</code>\n";
        }
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "➕ برای افزودن آیپی جدید، دکمه <b>افزودن آیپی</b> را بزنید.\n";
    $msg .= "♾️ برای دسترسی نامحدود، دکمه <b>حالت نامحدود</b> را بزنید.";

    $ip_keyboard = ['inline_keyboard' => []];
    foreach ($ip_list as $i => $ip) {
        $ip_keyboard['inline_keyboard'][] = [
            ['text' => "🔸 " . $ip, 'callback_data' => "noop"],
            ['text' => "🗑 حذف",     'callback_data' => "deliplogin_" . $i],
        ];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "➕ افزودن آیپی", 'callback_data' => "addiplogin"]];
    if ($iplogin_unlimited) {
        $ip_keyboard['inline_keyboard'][] = [['text' => "🔒 غیرفعال‌سازی حالت نامحدود", 'callback_data' => "iploginunlim_off"]];
    } else {
        $ip_keyboard['inline_keyboard'][] = [['text' => "♾️ فعال‌سازی حالت نامحدود", 'callback_data' => "iploginunlim_on"]];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backadmin"]];
    $ip_keyboard_json = json_encode($ip_keyboard);

    if ($message_id) {
        Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
    } else {
        nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
    }

} elseif ($datain == "iploginunlim_on" || $datain == "iploginunlim_off") {
    if ($datain == "iploginunlim_on") {
        update("setting", "iplogin", "*", null, null);
        $toggle_msg = "♾️ <b>حالت نامحدود فعال شد.</b>\nورود به پنل از هر آیپی‌ای ممکن است.";
        $ip_list = [];
        $iplogin_unlimited = true;
    } else {
        update("setting", "iplogin", json_encode([]), null, null);
        $toggle_msg = "🔒 <b>حالت نامحدود غیرفعال شد.</b>\nبرای ورود، آیپی مجاز را تنظیم کنید.";
        $ip_list = [];
        $iplogin_unlimited = false;
    }
    $msg = $toggle_msg . "\n━━━━━━━━━━━━━━━━━━━━\n";
    if ($iplogin_unlimited) {
        $msg .= "♾️ <b>حالت نامحدود فعال است.</b>\n";
    } elseif (empty($ip_list)) {
        $msg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n";
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━";
    $ip_keyboard = ['inline_keyboard' => []];
    $ip_keyboard['inline_keyboard'][] = [['text' => "➕ افزودن آیپی", 'callback_data' => "addiplogin"]];
    if ($iplogin_unlimited) {
        $ip_keyboard['inline_keyboard'][] = [['text' => "🔒 غیرفعال‌سازی حالت نامحدود", 'callback_data' => "iploginunlim_off"]];
    } else {
        $ip_keyboard['inline_keyboard'][] = [['text' => "♾️ فعال‌سازی حالت نامحدود", 'callback_data' => "iploginunlim_on"]];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "🛡 مدیریت آیپی‌ها", 'callback_data' => "iploginset"]];
    $ip_keyboard['inline_keyboard'][] = [['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backadmin"]];
    $ip_keyboard_json = json_encode($ip_keyboard);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
    } else {
        nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
    }
} elseif ($datain == "addiplogin") {
    nm_adminInstantReply($from_id, "📌 آیپی جدید خود را ارسال کنید.\n<i>مثال: 1.2.3.4</i>", null, 'HTML');
    step("getiplogin", $from_id);

} elseif ($user['step'] == "getiplogin") {
    $new_ip = trim($text);
    if (!filter_var($new_ip, FILTER_VALIDATE_IP)) {
        nm_adminInstantReply($from_id, "❌ آیپی وارد شده معتبر نیست. لطفاً یک آیپی صحیح ارسال کنید.\nمثال: <code>1.2.3.4</code>", null, 'HTML');
        return;
    }
    $setting_row = select("setting", "*", null, null, "select");
    $raw_ip = $setting_row['iplogin'] ?? '';
    $ip_list = [];
    if (!empty($raw_ip) && $raw_ip !== '0') {
        $decoded = json_decode($raw_ip, true);
        if (is_array($decoded)) {
            $ip_list = $decoded;
        } elseif (filter_var($raw_ip, FILTER_VALIDATE_IP)) {
            $ip_list = [$raw_ip];
        }
    }
    if (in_array($new_ip, $ip_list)) {
        nm_adminInstantReply($from_id, "⚠️ این آیپی قبلاً در لیست وجود دارد.", null, 'HTML');
        step("home", $from_id);
        return;
    }
    $ip_list[] = $new_ip;
    update("setting", "iplogin", json_encode(array_values($ip_list)), null, null);
    step("home", $from_id);
    nm_adminInstantReply($from_id, "✅ آیپی <code>" . htmlspecialchars($new_ip) . "</code> با موفقیت اضافه شد.", $shopkeyboard, 'HTML');

} elseif (preg_match('/^deliplogin_(\d+)$/', $datain, $ipdel_match)) {
    $del_index = (int)$ipdel_match[1];
    $setting_row = select("setting", "*", null, null, "select");
    $raw_ip = $setting_row['iplogin'] ?? '';
    $ip_list = [];
    if (!empty($raw_ip) && $raw_ip !== '0') {
        $decoded = json_decode($raw_ip, true);
        if (is_array($decoded)) {
            $ip_list = $decoded;
        } elseif (filter_var($raw_ip, FILTER_VALIDATE_IP)) {
            $ip_list = [$raw_ip];
        }
    }
    if (!isset($ip_list[$del_index])) {
        nm_adminInstantReply($from_id, "❌ آیپی مورد نظر یافت نشد.", $shopkeyboard, 'HTML');
        return;
    }
    $deleted_ip = $ip_list[$del_index];
    array_splice($ip_list, $del_index, 1);
    update("setting", "iplogin", json_encode(array_values($ip_list)), null, null);

    $msg = "🛡 <b>تنظیم آیپی ورود</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "✅ آیپی <code>" . htmlspecialchars($deleted_ip) . "</code> حذف شد.\n\n";
    if (empty($ip_list)) {
        $msg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n";
        $msg .= "در این حالت <b>ورود به پنل وب برای همه مسدود است.</b>\n";
    } else {
        $msg .= "📋 آیپی‌های مجاز ورود:\n";
        foreach ($ip_list as $i => $ip) {
            $msg .= ($i + 1) . ". <code>" . htmlspecialchars($ip) . "</code>\n";
        }
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━";

    $ip_keyboard = ['inline_keyboard' => []];
    foreach ($ip_list as $i => $ip) {
        $ip_keyboard['inline_keyboard'][] = [
            ['text' => "🔸 " . $ip, 'callback_data' => "noop"],
            ['text' => "🗑 حذف",     'callback_data' => "deliplogin_" . $i],
        ];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "➕ افزودن آیپی", 'callback_data' => "addiplogin"]];
    $ip_keyboard['inline_keyboard'][] = [['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backadmin"]];
    $ip_keyboard_json = json_encode($ip_keyboard);

    if ($message_id) {
        Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
    } else {
        nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
    }
} elseif (preg_match('/extendadmin_(\w+)/', $datain, $dataget) || strpos($text, "/extend ") !== false) {
    if ($text[0] == "/") {
        $usernameconfig = explode(" ", $text)[1];
        $id_invoice = select("invoice", "id_invoice", "username", $usernameconfig, 'select');
        if ($id_invoice == false) {
            nm_adminInstantReply($from_id, "❌ کاربر وجو ندارد.", null, 'HTML');
            return;
        }
        $id_invoice = $id_invoice['id_invoice'];
    } else {
        $id_invoice = $dataget[1];
    }
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        nm_adminInstantReply($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    update("user", "Processing_value_one", $nameloc['id_invoice'], "id", $from_id);
    savedata("clear", "id_invoice", $nameloc['id_invoice']);
    $textcustom = "📌 حجم درخواستی خود را ارسال کنید.";
    nm_adminInstantReply($from_id, $textcustom, $backuser, 'html');
    step('gettimecustomvolomforextendadmin', $from_id);
} elseif ($user['step'] == "gettimecustomvolomforextendadmin") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("save", "volume", $text);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید ";
    nm_adminInstantReply($from_id, $textcustom, $backuser, 'html');
    step('getvolumecustomuserforextendadmin', $from_id);
} elseif ($user['step'] == "getvolumecustomuserforextendadmin") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
        return;
    }
    $prodcut['name_product'] = $nameloc['name_product'];
    $prodcut['note'] = "";
    $prodcut['price_product'] = 0;
    $prodcut['Service_time'] = $text;
    $prodcut['Volume_constraint'] = $userdate['volume'];
    update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $userdate['id_invoice']);
    step("home", $from_id);
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivceadmin-" . $nameloc['id_invoice']],
            ],
            [
                ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"]
            ]
        ]
    ]);
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.

🛍 نام محصول :{$prodcut['name_product']}
⏱ مدت زمان تمدید :{$prodcut['Service_time']} روز
🔋 حجم تمدید :{$prodcut['Volume_constraint']} گیگ
✍️ توضیحات : {$prodcut['note']}
✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید";
    if ($user['step'] == "getvolumecustomuserforextendadmin") {
        nm_adminInstantReply($from_id, $textextend, $keyboardextend, 'HTML');
    } else {
        Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
    }
} elseif (preg_match('/^confirmserivceadmin-(.*)/', $datain, $dataget)) {
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $prodcut['code_product'] = "custom_volume";
    $prodcut['name_product'] = $nameloc['name_product'];
    $prodcut['price_product'] = 0;
    $prodcut['Service_time'] = $nameloc['Service_time'];
    $prodcut['Volume_constraint'] = $nameloc['Volume'];
    if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        nm_adminInstantReply($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
    if ($extend['status'] == false) {
        if (nmStockCompleteExtendFallback($from_id, $user, $nameloc, $prodcut, 0, 'maintenance_extend_panel_fallback')) {
            return;
        }
        $extend['msg'] = json_encode($extend['msg']);
        $textreports = "
        خطای تمدید سرویس
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extend['msg']}";
        nm_adminInstantReply($from_id, "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $dateacc = date('Y/m/d H:i:s');
    $value = $prodcut['Volume_constraint'] . "_" . $prodcut['Service_time'];
    $type = "extend_user_by_admin";
    $stmt->bindParam(':id_user', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':username', $nameloc['username'], PDO::PARAM_STR);
    $stmt->bindParam(':value', $value, PDO::PARAM_STR);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->bindParam(':time', $dateacc, PDO::PARAM_STR);
    $stmt->bindParam(':price', $prodcut['price_product'], PDO::PARAM_STR);
    $output_json = json_encode($extend);
    $stmt->bindParam(':output', $output_json, PDO::PARAM_STR);
    $stmt->execute();
    update("invoice", "Status", "active", "id_invoice", $id_invoice);
    nm_adminInstantReply($from_id, $textbotlang['users']['extend']['thanks'], null, 'HTML');
    $text_report = "⭕️ ادمین سرویس کاربر را تمدید کرد.

اطلاعات کاربر :

🪪 آیدی عددی ادمین : <code>$from_id</code>
🪪 آیدی عددی : <code>{$nameloc['id_user']}</code>
🛍 نام محصول :  {$prodcut['name_product']}
👤 نام کاربری مشتری در پنل  : {$nameloc['username']}
موقعیت سرویس سرویس کاربر : {$nameloc['Service_location']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/removeresid_(\w+)/', $datain, $dataget)) {
    $idorder = $dataget[1];
    $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :id_order");
    $stmt->bindParam(':id_order', $idorder, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ رسید با موفقیت حذف شد.", null, 'HTML');
}