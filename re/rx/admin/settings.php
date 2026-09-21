<?php

if (!function_exists('rx_iranpay_label')) {
    function rx_iranpay_label($datatextbot, $key, $fallback)
    {
        $name = (is_array($datatextbot) && isset($datatextbot[$key])) ? trim((string)$datatextbot[$key]) : '';
        if ($name !== '') {
            return "📌 " . $name;
        }
        return $fallback;
    }
}

if ($datain == "settimecornremove" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['setdayremove'] . $setting['removedayc'] . "روز", $backadmin, 'HTML');
    step("getdaycron", $from_id);
} elseif ($user['step'] == "getdaycron") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "removedayc", $text);
} elseif ($text == "✏️ ویرایش آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
    step("getnameforedite", $from_id);
} elseif ($user['step'] == "getnameforedite") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $helpedit, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step("help_edit", $from_id);
} elseif ($text == "ویرایش نام آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نام جدید آموزش را ارسال کنید", $backadmin, 'HTML');
    step('changenamehelp', $from_id);
} elseif ($user['step'] == "changenamehelp") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (strlen($text) >= 150) {
        nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    update("help", "name_os", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ نام آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('help_edit', $from_id);
} elseif ($text == "ویرایش دسته بندی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "دسته بندی جدید خود را ارسال کنید", $backadmin, 'HTML');
    step('changecategoryhelp', $from_id);
} elseif ($user['step'] == "changecategoryhelp") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (strlen($text) >= 150) {
        nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    update("help", "category", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ نام دسته آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('help_edit', $from_id);
} elseif ($text == "ویرایش توضیحات" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "توضیحات جدید را ارسال کنید", $backadmin, 'HTML');
    step('changedeshelp', $from_id);
} elseif ($user['step'] == "changedeshelp") {
    if (!isset($update['message']) && empty($text)) { return; }
    update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ توضیحات  آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('help_edit', $from_id);
} elseif ($text == "ویرایش رسانه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "تصویر یا فیلم جدید را ارسال کنید", $backadmin, 'HTML');
    step('changemedia', $from_id);
} elseif ($user['step'] == "changemedia") {
    if (!isset($update['message']) && empty($text)) { return; }
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
    step('help_edit', $from_id);
} elseif ($text == "ویرایش لینک برنامه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 متن دکمه لینک دانلود برنامه را ارسال کنید", $backadmin, 'HTML');
    step('changeapptitlehelp', $from_id);
} elseif ($user['step'] == "changeapptitlehelp") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (strlen($text) > 200) {
        nm_adminInstantReply($from_id, "📌 نام باید کمتر از ۲۰۰ کاراکتر باشد.", $backadmin, 'HTML');
        return;
    }
    update("help", "app_title", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "📌 لینک دانلود اپ را ارسال نمایید", $backadmin, 'HTML');
    step('changeapplinkhelp', $from_id);
} elseif ($user['step'] == "changeapplinkhelp") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    update("help", "app_link", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ لینک برنامه با موفقیت بروزرسانی گردید.", $helpedit, 'HTML');
    step('help_edit', $from_id);
} elseif ($text == "💰 غیرفعال کارت" || $datain == "cart_hide_num") {
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
    step('home', $from_id);
    if (isset($CartManage)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت', $CartManage, 'HTML');
    }
} elseif ($text == "💰 فعال شماره کارت" || $datain == "cart_show_num") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['activeshowcardstatus'], null, 'HTML');
    update("user", "cardpayment", "1");
    update("setting", "showcard", "1");
} elseif ($text == "🔋 روش تمدید سرویس" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $Methodextend, 'HTML');
    step('updateextendmethod', $from_id);
} elseif ($user['step'] == "updateextendmethod") {
    if (!isset($update['message']) && empty($text)) { return; }
    $aarayvalid = array(
        'ریست حجم و زمان',
        'اضافه شدن زمان و حجم به ماه بعد',
        'ریست زمان و اضافه کردن حجم قبلی',
        'ریست شدن حجم و اضافه شدن زمان',
        'اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده',
        'رزرو اشتراک'
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
    step('PanelMenu', $from_id);
} elseif ($text == "/token") {
    $secret_key = select("admin", "*", "id_admin", $from_id, "select");
    $secret_key = base64_encode($secret_key['password']);
    nm_adminInstantReply($from_id, "<code>$secret_key</code>", null, 'HTML');
} elseif ($text == "/token2") {
    $token = bin2hex(random_bytes(16));
    require_once REFACTORED_LEGACY_ROOT . '/lib/ApiCredential.php';
    FaoximaApiCredential::write($token);
    nm_adminInstantReply($from_id, "توکن api شما : <code>$token</code>", null, 'HTML');
    sendDocument($from_id, 'api/documents.txt', "📌 داکیومنت api ربات
نکات :
۱ - در صورتی که به endpoint خاصی نیاز داشتید به اکانت پشتیبانی پیام دهید تا بررسی شود.");
} elseif (($text == "✅ پنل تحت وب" || $datain == "webpanel_mgr_list") && $adminrulecheck['rule'] == "administrator") {
    $webpanelListAdmins = select("admin", "*", null, null, "fetchAll");
    $webpanelListKeyboard = ['inline_keyboard' => []];
    foreach ($webpanelListAdmins as $webpanelListRow) {
        $webpanelListId = isset($webpanelListRow['id_admin']) ? trim($webpanelListRow['id_admin']) : '';
        if ($webpanelListId === '') {
            continue;
        }
        $webpanelListUsername = trim((string)($webpanelListRow['username'] ?? '')) !== '' ? trim((string)($webpanelListRow['username'] ?? '')) : $webpanelListId;
        $webpanelListKeyboard['inline_keyboard'][] = [
            ['text' => "👤 {$webpanelListUsername} — {$webpanelListId}", 'callback_data' => "webpanel_mgr_view_" . $webpanelListId],
        ];
    }
    $webpanelListKeyboard['inline_keyboard'][] = [
        ['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => "set_backadmin"],
        ['text' => "🏠 منوی مدیریت", 'callback_data' => "adm_hub_main"],
    ];
    $webpanelListKeyboardJson = json_encode($webpanelListKeyboard);
    $webpanelListMsg = "✅ <b>پنل تحت وب</b>\n";
    $webpanelListMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $webpanelListMsg .= "برای مدیریت حساب پنل هر ادمین (نام کاربری، رمز عبور، آیپی ورود)، روی نام او بزنید.";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $webpanelListMsg, $webpanelListKeyboardJson);
    } else {
        nm_adminInstantReply($from_id, $webpanelListMsg, $webpanelListKeyboardJson, 'HTML');
    }
} elseif (preg_match('/^webpanel_mgr_view_(\w+)$/', $datain, $webpanelMgrViewMatch) && $adminrulecheck['rule'] == "administrator") {
    $viewAdminId = trim($webpanelMgrViewMatch[1]);
    $viewAdminRow = select("admin", "*", "id_admin", $viewAdminId, "select");
    if (!$viewAdminRow) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    [$viewIpList, $viewIpUnlimited] = getAdminIpLoginState($viewAdminId);
    $viewIsMain = trim($viewAdminId) === trim((string) $adminnumber);
    $viewIpStatus = $viewIpUnlimited ? "♾️ نامحدود" : (empty($viewIpList) ? "🔒 مسدود (هیچ آیپی تنظیم نشده)" : "📋 " . count($viewIpList) . " آیپی مجاز");
    $viewMsg = "👤 <b>مدیریت حساب پنل تحت وب</b>\n";
    $viewMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $viewMsg .= "🪪 شناسه عددی: <code>{$viewAdminId}</code>\n";
    $viewMsg .= "👤 نام کاربری: <code>" . htmlspecialchars((string)($viewAdminRow['username'] ?? '')) . "</code>\n";
    $viewMsg .= "🛡 نقش: " . htmlspecialchars((string)($viewAdminRow['rule'] ?? '')) . ($viewIsMain ? " (ادمین اصلی)" : "") . "\n";
    $viewMsg .= "🌐 آیپی ورود: {$viewIpStatus}\n";
    $viewMsg .= "━━━━━━━━━━━━━━━━━━━━";
    $viewKeyboard = ['inline_keyboard' => []];
    $viewKeyboard['inline_keyboard'][] = [
        ['text' => "🔐 اطلاعات ورود پنل", 'callback_data' => "webpanel_mgr_creds_" . $viewAdminId],
    ];
    $viewKeyboard['inline_keyboard'][] = [
        ['text' => "✏️ تغییر نام کاربری", 'callback_data' => "webpanel_mgr_uname_" . $viewAdminId],
    ];
    $viewKeyboard['inline_keyboard'][] = [
        ['text' => "🔑 ریست رمز عبور", 'callback_data' => "webpanel_mgr_resetpass_" . $viewAdminId],
    ];
    $viewKeyboard['inline_keyboard'][] = [
        ['text' => "🌐 تنظیم آیپی ورود اختصاصی", 'callback_data' => "webpanel_mgr_ip_" . $viewAdminId],
    ];
    $viewKeyboard['inline_keyboard'][] = [
        ['text' => "◀️ بازگشت به لیست ادمین‌ها", 'callback_data' => "webpanel_mgr_list"],
    ];
    $viewKeyboardJson = json_encode($viewKeyboard);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $viewMsg, $viewKeyboardJson);
    } else {
        nm_adminInstantReply($from_id, $viewMsg, $viewKeyboardJson, 'HTML');
    }
} elseif (preg_match('/^webpanel_mgr_creds_(\w+)$/', $datain, $webpanelMgrCredsMatch) && $adminrulecheck['rule'] == "administrator") {
    $credsAdminId = trim($webpanelMgrCredsMatch[1]);
    $credsAdminRow = select("admin", "*", "id_admin", $credsAdminId, "select");
    if (!$credsAdminRow) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    $credsPassword = $credsAdminRow['password'] ?? null;
    if ($credsPassword === null || $credsPassword === '') {
        $credsPassword = bin2hex(random_bytes(6));
        update("admin", "password", $credsPassword, "id_admin", $credsAdminId);
        update("admin", "password_hash", password_hash($credsPassword, PASSWORD_DEFAULT), "id_admin", $credsAdminId);
    }
    $credsUsername = trim((string)($credsAdminRow['username'] ?? ''));
    if ($credsUsername === '') {
        $credsUsername = $credsAdminId;
        update("admin", "username", $credsUsername, "id_admin", $credsAdminId);
    }
    $credsBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ بازگشت", 'callback_data' => "webpanel_mgr_view_" . $credsAdminId]]]]);
    nm_adminInstantReply($from_id, "🔐 <b>اطلاعات ورود پنل تحت وب</b>

🔗آدرس ورود : https://$domainhosts/panel
👤نام کاربری :  <code>" . htmlspecialchars($credsUsername) . "</code>
🔑رمز عبور :  <code>{$credsPassword}</code>", $credsBackKb, 'HTML');
} elseif (preg_match('/^webpanel_mgr_uname_(\w+)$/', $datain, $webpanelMgrUnameMatch) && $adminrulecheck['rule'] == "administrator") {
    $targetAdminId = trim($webpanelMgrUnameMatch[1]);
    if (!select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    update("user", "Processing_value", $targetAdminId, "id", $from_id);
    $unameBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ انصراف", 'callback_data' => "webpanel_mgr_view_" . $targetAdminId]]]]);
    nm_adminInstantReply($from_id, "📌 نام کاربری جدید را ارسال کنید.", $unameBackKb, 'HTML');
    step("webpanel_mgr_get_uname", $from_id);
} elseif ($user['step'] == "webpanel_mgr_get_uname" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    $newUsername = trim($text);
    $targetAdminId = trim((string)($user['Processing_value'] ?? ''));
    if ($newUsername === '' || $targetAdminId === '') {
        nm_adminInstantReply($from_id, "❌ نام کاربری نامعتبر است.", null, 'HTML');
        return;
    }
    if (!select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    update("admin", "username", $newUsername, "id_admin", $targetAdminId);
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ نام کاربری با موفقیت به «" . htmlspecialchars($newUsername) . "» تغییر یافت.", $keyboardadmin, 'HTML');
} elseif (preg_match('/^webpanel_mgr_resetpass_(\w+)$/', $datain, $webpanelMgrResetMatch) && $adminrulecheck['rule'] == "administrator") {
    $targetAdminId = trim($webpanelMgrResetMatch[1]);
    if (!select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    $newAdminPassword = bin2hex(random_bytes(6));
    update("admin", "password", $newAdminPassword, "id_admin", $targetAdminId);
    update("admin", "password_hash", password_hash($newAdminPassword, PASSWORD_DEFAULT), "id_admin", $targetAdminId);
    $resetBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ بازگشت", 'callback_data' => "webpanel_mgr_view_" . $targetAdminId]]]]);
    nm_adminInstantReply($from_id, "✅ رمز عبور با موفقیت ریست شد.\n\n🔑 رمز عبور جدید: <code>{$newAdminPassword}</code>", $resetBackKb, 'HTML');
    if ($targetAdminId !== $from_id) {
        @sendmessage($targetAdminId, "🔑 رمز عبور پنل تحت وب شما توسط یک ادمین دیگر تغییر یافت.\n\nرمز عبور جدید: <code>{$newAdminPassword}</code>", null, 'HTML');
    }
} elseif (preg_match('/^webpanel_mgr_ip_(\w+)$/', $datain, $webpanelMgrIpMatch) && $adminrulecheck['rule'] == "administrator") {
    $targetAdminId = trim($webpanelMgrIpMatch[1]);
    if (!select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    [$mgrIpList, $mgrIpUnlimited] = getAdminIpLoginState($targetAdminId);
    $mgrIpMsg = "🌐 <b>آیپی ورود اختصاصی</b>\n";
    $mgrIpMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
    if ($mgrIpUnlimited) {
        $mgrIpMsg .= "♾️ <b>حالت نامحدود فعال است.</b>\nورود شما از هر آیپی‌ای آزاد است.\n";
    } elseif (empty($mgrIpList)) {
        $mgrIpMsg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\nدر این حالت ورود شما از پنل وب مسدود است.\n";
    } else {
        $mgrIpMsg .= "📋 آیپی‌های مجاز ورود:\n";
        foreach ($mgrIpList as $i => $ip) {
            $mgrIpMsg .= ($i + 1) . ". <code>" . htmlspecialchars($ip) . "</code>\n";
        }
    }
    $mgrIpMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $mgrIpMsg .= "➕ برای افزودن آیپی جدید، دکمه <b>افزودن آیپی</b> را بزنید.";
    $mgrIpKeyboardJson = buildWebpanelIpLoginKeyboard($targetAdminId, $mgrIpList, $mgrIpUnlimited);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $mgrIpMsg, $mgrIpKeyboardJson);
    } else {
        nm_adminInstantReply($from_id, $mgrIpMsg, $mgrIpKeyboardJson, 'HTML');
    }
} elseif (preg_match('/^webpanel_mgr_ipunlim_(on|off)_(\w+)$/', $datain, $webpanelMgrIpUnlimMatch) && $adminrulecheck['rule'] == "administrator") {
    $targetAdminId = trim($webpanelMgrIpUnlimMatch[2]);
    if (!select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    if ($webpanelMgrIpUnlimMatch[1] === 'on') {
        update("admin", "iplogin", "*", "id_admin", $targetAdminId);
    } else {
        update("admin", "iplogin", json_encode([]), "id_admin", $targetAdminId);
    }
    [$mgrIpList, $mgrIpUnlimited] = getAdminIpLoginState($targetAdminId);
    $mgrIpMsg = "🌐 <b>آیپی ورود اختصاصی</b>\n━━━━━━━━━━━━━━━━━━━━\n";
    $mgrIpMsg .= $mgrIpUnlimited ? "♾️ <b>حالت نامحدود فعال است.</b>\n" : (empty($mgrIpList) ? "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n" : "📋 " . count($mgrIpList) . " آیپی مجاز تنظیم شده است.\n");
    $mgrIpMsg .= "━━━━━━━━━━━━━━━━━━━━";
    $mgrIpKeyboardJson = buildWebpanelIpLoginKeyboard($targetAdminId, $mgrIpList, $mgrIpUnlimited);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $mgrIpMsg, $mgrIpKeyboardJson);
    } else {
        nm_adminInstantReply($from_id, $mgrIpMsg, $mgrIpKeyboardJson, 'HTML');
    }
} elseif (preg_match('/^webpanel_mgr_ipadd_(\w+)$/', $datain, $webpanelMgrIpAddMatch) && $adminrulecheck['rule'] == "administrator") {
    $targetAdminId = trim($webpanelMgrIpAddMatch[1]);
    if (!select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    update("user", "Processing_value", $targetAdminId, "id", $from_id);
    $ipAddBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ انصراف", 'callback_data' => "webpanel_mgr_ip_" . $targetAdminId]]]]);
    nm_adminInstantReply($from_id, "📌 آیپی جدید را ارسال کنید.\n<i>مثال: 1.2.3.4</i>", $ipAddBackKb, 'HTML');
    step("webpanel_mgr_get_ip", $from_id);
} elseif ($user['step'] == "webpanel_mgr_get_ip" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    $newIp = trim($text);
    $targetAdminId = trim((string)($user['Processing_value'] ?? ''));
    if ($targetAdminId === '' || !select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!filter_var($newIp, FILTER_VALIDATE_IP)) {
        nm_adminInstantReply($from_id, "❌ آیپی وارد شده معتبر نیست. لطفاً یک آیپی صحیح ارسال کنید.\nمثال: <code>1.2.3.4</code>", null, 'HTML');
        return;
    }
    [$curIpList, $curIpUnlimited] = getAdminIpLoginState($targetAdminId);
    if ($curIpUnlimited) {
        $curIpList = [];
    }
    if (in_array($newIp, $curIpList, true)) {
        nm_adminInstantReply($from_id, "⚠️ این آیپی قبلاً در لیست وجود دارد.", null, 'HTML');
        step("home", $from_id);
        return;
    }
    $curIpList[] = $newIp;
    update("admin", "iplogin", json_encode(array_values($curIpList)), "id_admin", $targetAdminId);
    step("home", $from_id);
    nm_adminInstantReply($from_id, "✅ آیپی <code>" . htmlspecialchars($newIp) . "</code> با موفقیت اضافه شد.", $keyboardadmin, 'HTML');
} elseif (preg_match('/^webpanel_mgr_ipdel_(\w+)_(\d+)$/', $datain, $webpanelMgrIpDelMatch) && $adminrulecheck['rule'] == "administrator") {
    $targetAdminId = trim($webpanelMgrIpDelMatch[1]);
    $delIndex = (int) $webpanelMgrIpDelMatch[2];
    if (!select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
        nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    [$curIpList, $curIpUnlimited] = getAdminIpLoginState($targetAdminId);
    if ($curIpUnlimited || !isset($curIpList[$delIndex])) {
        nm_adminInstantReply($from_id, "❌ آیپی مورد نظر یافت نشد.", null, 'HTML');
        return;
    }
    array_splice($curIpList, $delIndex, 1);
    update("admin", "iplogin", json_encode(array_values($curIpList)), "id_admin", $targetAdminId);
    [$mgrIpList, $mgrIpUnlimited] = getAdminIpLoginState($targetAdminId);
    $mgrIpMsg = "🌐 <b>آیپی ورود اختصاصی</b>\n━━━━━━━━━━━━━━━━━━━━\n✅ آیپی حذف شد.\n━━━━━━━━━━━━━━━━━━━━";
    $mgrIpKeyboardJson = buildWebpanelIpLoginKeyboard($targetAdminId, $mgrIpList, $mgrIpUnlimited);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $mgrIpMsg, $mgrIpKeyboardJson);
    } else {
        nm_adminInstantReply($from_id, $mgrIpMsg, $mgrIpKeyboardJson, 'HTML');
    }
} elseif (preg_match('/addordermanualـ(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['towstep'], $backadmin, 'HTML');
    step('getusernameconfig', $from_id);
} elseif ($user['step'] == "getusernameconfig") {
    $text = trim($text);
    if ($text === '' || preg_match('/\s/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['Invalidusername'], $backuser, 'html');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['threestep'], $json_list_marzban_panel, 'HTML');
    step('getnamepanelconfig', $from_id);
} elseif ($user['step'] == "getnamepanelconfig") {
    $panelRow = function_exists('rx_resolvePanelFromInput') ? rx_resolvePanelFromInput($text, $pdo) : null;
    $canonicalLoc = is_array($panelRow) && !empty($panelRow['name_panel']) ? $panelRow['name_panel'] : $text;
    update("user", "Processing_value_tow", $canonicalLoc, "id", $from_id);
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
        nm_adminInstantReply($from_id, "❌ این نام کاربری روی پنل انتخابی وجود ندارد. لطفا نام کاربری را بررسی و دوباره ارسال کنید یا ابتدا آن را روی پنل بسازید.", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $dupStmt = $pdo->prepare("SELECT 1 FROM invoice WHERE Service_location = :location AND username = CONVERT(:username USING utf8mb4) COLLATE utf8mb4_bin LIMIT 1");
    $dupStmt->bindParam(':location', $user['Processing_value_tow'], PDO::PARAM_STR);
    $dupStmt->bindParam(':username', $user['Processing_value_one'], PDO::PARAM_STR);
    $dupStmt->execute();
    if ($dupStmt->fetch(PDO::FETCH_ASSOC) !== false) {
        nm_adminInstantReply($from_id, "❌ این سرویس از قبل داخل ربات ثبت شده است.", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $DataUserOut['configs'] = $DataUserOut['links'];
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
    $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status,notifctions,ip_limit) VALUES (:id_user, :id_invoice, :username, :time_sell, :Service_location, :name_product, :price_product, :Volume, :Service_time, :Status,:notifctions,:ip_limit)");
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
    $stmt->bindParam(':ip_limit', $invoiceIpLimit, PDO::PARAM_STR);
    $stmt->bindParam(':Status', $Status, PDO::PARAM_STR);
    $stmt->bindParam(':notifctions', $notifctions, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        nm_adminInstantReply($from_id, "❌ سرویس روی پنل پیدا شد، اما ثبت آن در ربات ناموفق بود؛ احتمالا این سرویس قبلا در ربات ثبت شده است.", null, 'HTML');
        $texterros = "خطا در ثبت سفارش محلی برای سرویس موجود روی پنل (احتمالا تداخل نام کاربری)
<blockquote>نام کاربری: {$user['Processing_value_one']}</blockquote>
<blockquote>آیدی ادمین: $from_id</blockquote>
<blockquote>نام پنل: {$marzban_list_get['name_panel']}</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ]);
        }
        step("home", $from_id);
        return;
    }
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? rxResolveConnectionLink($marzban_list_get, $DataUserOut['subscription_url'], $DataUserOut['file_ext'] ?? null) : "";
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
    if ($marzban_list_get['type'] == "Manualsale") {
        $textcreatuser = str_replace('{password}', $DataUserOut['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $DataUserOut['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $DataUserOut['configs'], $output_config_link, $DataUserOut['username'], $Shoppinginfo, $textcreatuser, $randomString, $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['fivestep'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬇️ کف خرید عمده" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("shopSetting", "value", "Namevalue", "minbalancebuybulk", "select")['value'];
    $textmin = "📌 حداقل مبلغی که می خواهید کاربر  خرید انبوه کند را ارسال کنید.

مبلغ فعلی : $PaySetting";
    nm_adminInstantReply($from_id, $textmin, $backadmin, 'HTML');
    step('minbalancebulk', $from_id);
} elseif ($user['step'] == "minbalancebulk") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $shopkeyboard, 'HTML');
    update("shopSetting", "value", $text, "Namevalue", "minbalancebuybulk");
    step('home', $from_id);
} elseif ($text == "❌ حذف شماره کارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 شماره کارتی که می خواهید حذف کنید را ارسال نمایید.", $list_card_remove, 'HTML');
    step('getcardremove', $from_id);
} elseif ($user['step'] == "getcardremove") {
    $stmt = $pdo->prepare("DELETE FROM card_number WHERE cardnumber = :cardnumber");
    $stmt->bindParam(':cardnumber', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ شماره کارت با موفقیت حذف گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/^rejectrequesta_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
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
            'cache_time' => 0,
        ));
        return;
    }
    $confirmKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ بله، رد کن", 'callback_data' => "cfmreja_" . $id_user],
                ['text' => "🔙 لغو", 'callback_data' => "cnclagentreq_" . $id_user],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textConfirm = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textConfirm .= "\n\n⚠️ آیا از <b>رد</b> این درخواست اطمینان دارید؟";
    Editmessagetext($from_id, $message_id, $textConfirm, $confirmKeyboard);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "برای تایید نهایی روی «بله، رد کن» بزنید.",
        'show_alert' => false,
        'cache_time' => 0,
    ));
} elseif (preg_match('/^cfmreja_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);

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
            'cache_time' => 0,
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
                'cache_time' => 0,
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
} elseif (preg_match('/^addagentrequest_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
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
            'cache_time' => 0,
        ));
        return;
    }
    $confirmKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ بله، تایید کن", 'callback_data' => "cfmacea_" . $id_user],
                ['text' => "🔙 لغو", 'callback_data' => "cnclagentreq_" . $id_user],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textConfirm = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textConfirm .= "\n\n⚠️ آیا از <b>تایید</b> این درخواست اطمینان دارید؟";
    Editmessagetext($from_id, $message_id, $textConfirm, $confirmKeyboard);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "برای تایید نهایی روی «بله، تایید کن» بزنید.",
        'show_alert' => false,
        'cache_time' => 0,
    ));
} elseif (preg_match('/^cnclagentreq_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
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
            'text' => "این درخواست قبلاً بررسی شده است",
            'show_alert' => true,
            'cache_time' => 0,
        ));
        return;
    }
    $keyboardmanage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['agenttext']['acceptrequest'], 'callback_data' => "addagentrequest_" . $id_user],
                ['text' => $textbotlang['users']['agenttext']['rejectrequest'], 'callback_data' => "rejectrequesta_" . $id_user],
            ],
            [
                ['text' => $textbotlang['users']['SendMessage'], 'callback_data' => 'Response_' . $id_user],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardmanage);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "عملیات لغو شد.",
        'show_alert' => false,
        'cache_time' => 0,
    ));
} elseif (preg_match('/^cfmacea_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
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
            'cache_time' => 0,
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
                'cache_time' => 0,
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
            'cache_time' => 0,
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
        'cache_time' => 0,
    ));
} elseif ($datain == "iranpay2setting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $trnado, 'HTML');
} elseif ($datain == "tonpaysetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $tonpay, 'HTML');
} elseif ($datain == "cubepaysetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $cubepay, 'HTML');
} elseif ($datain == "blupalsetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $blupal, 'HTML');
} elseif ($datain == "varizasetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $variza, 'HTML');
} elseif ($datain == "atlaspaysetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $atlaspay, 'HTML');
} elseif ($datain == "tetrapaysetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $tetrapay, 'HTML');
} elseif ($text == "📊 موجودی و اطلاعات حساب" && $adminrulecheck['rule'] == "administrator") {
    $balanceData = function_exists('atlaspayBalance') ? atlaspayBalance() : null;
    $accountData = function_exists('atlaspayAccount') ? atlaspayAccount() : null;
    $availableTrx = is_array($balanceData) ? ($balanceData['data']['availableTrx'] ?? null) : null;
    $accountName = is_array($accountData) ? ($accountData['data']['name'] ?? null) : null;
    $accountStatus = is_array($accountData) ? ($accountData['data']['status'] ?? null) : null;
    $markupPct = is_array($accountData) ? ($accountData['data']['markupPct'] ?? null) : null;
    $textAtlasAccount = "📊 اطلاعات حساب اطلس‌پی\n\n";
    $textAtlasAccount .= "💰 موجودی قابل‌برداشت: " . ($availableTrx !== null ? number_format((float)$availableTrx, 4) . ' TRX' : 'دریافت نشد') . "\n";
    $textAtlasAccount .= "🏪 نام فروشگاه: " . ($accountName !== null ? htmlspecialchars((string)$accountName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'دریافت نشد') . "\n";
    $textAtlasAccount .= "📌 وضعیت حساب: " . ($accountStatus !== null ? htmlspecialchars((string)$accountStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'دریافت نشد') . "\n";
    $textAtlasAccount .= "📈 درصد مارک‌آپ: " . ($markupPct !== null ? (string)$markupPct : 'ندارد');
    nm_adminInstantReply($from_id, $textAtlasAccount, $atlaspay, 'HTML');
} elseif ($text == "وضعیت  درگاه ترونادو" && $adminrulecheck['rule'] == "administrator") {
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'],
            ],
        ]
    ]);
    $textternado = "در این بخش می توانید درگاه ترونادو را خاموش یا روشن کنید";
    nm_adminInstantReply($from_id, $textternado, $statusternadoo, 'HTML');
} elseif ($datain == "onternado") {
    update("PaySetting", "ValuePay", "offternado", "NamePay", "statustarnado");
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'],
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
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن گردید", $statusternadoo);
} elseif ($text == "🔑 ثبت API Key ترونادو" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiternado", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $texttronseller = "🔑 کلید API ترونادو خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('apiternado', $from_id);
} elseif ($user['step'] == "apiternado") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiternado");
    step('home', $from_id);
} elseif ($text == "🔏 ثبت کلید امضای IPN ترونادو" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "ipnsigningkeytronado", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $texttronseller = "🔏 کلید امضای IPN (IpnSigningKey) ترونادو را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('ipnsigningkeytronado', $from_id);
} elseif ($user['step'] == "ipnsigningkeytronado") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "ipnsigningkeytronado");
    step('home', $from_id);
} elseif ($text == "💼 آدرس کیف پول ترونادو" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
    $currentWallet = trim((string) ($PaySetting['ValuePay'] ?? ''));
    $texttronseller = "💼 آدرس کیف پول ترون (TRC20) که ترونادو مبالغ را به آن واریز می‌کند را ارسال کنید.\n\n"
        . "⚠️ آدرس را دقیقاً همان‌طور که در کیف پول نمایش داده می‌شود و با همان حروف کوچک و بزرگ ارسال کنید.\n\n"
        . "آدرس فعلی: " . ($currentWallet === '' ? 'ثبت نشده' : "<code>" . htmlspecialchars($currentWallet) . "</code>");
    if ($currentWallet !== '' && function_exists('tronadoIsValidTronAddress') && !tronadoIsValidTronAddress($currentWallet)) {
        $texttronseller .= "\n\n❌ آدرس فعلی معتبر نیست و ترونادو آن را رد می‌کند؛ لطفاً آدرس صحیح را دوباره ثبت کنید.";
    }
    $offlineTrx = function_exists('crypto_active_wallet') ? crypto_active_wallet('TRX') : null;
    if (function_exists('tronadoWalletsCollide') && is_array($offlineTrx)
        && tronadoWalletsCollide($currentWallet, $offlineTrx['wallet_address'] ?? '')) {
        $texttronseller .= "\n\n⚠️ این آدرس با کیف پول «🟥 ترون (TRX)» بخش ارز آفلاین یکی است؛ برای جلوگیری از تایید دوباره‌ی یک پرداخت، یک آدرس جداگانه برای ترونادو ثبت کنید.";
    }
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    savedata('clear', 'walletaddress_origin', 'trnado');
    step('walletaddresssiranpay', $from_id);
} elseif ($text == "⚖️ درصد کارمزد کسب‌وکار" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "wageFromBusinessPercentageTronado", "select");
    $currentValue = $PaySetting['ValuePay'] ?? '0';
    $texttronseller = "⚖️ درصد کارمزد ترونادو که کسب‌وکار شما پرداخت می‌کند\n\n"
        . "کارمزد پیش‌فرض ترونادو ۲۰٪ است. این عدد مشخص می‌کند این کارمزد بین شما و کاربر چطور تقسیم شود:\n\n"
        . "🔹 ۰ (پیش‌فرض): کل کارمزد را کاربر پرداخت می‌کند. شما دقیقاً مبلغ فاکتور خودتان را دریافت می‌کنید، اما کاربر مبلغی بیشتر از قیمت واقعی پرداخت می‌کند.\n\n"
        . "🔹 ۱۰۰: کل کارمزد را شما پرداخت می‌کنید. کاربر تقریباً همان مبلغ فاکتور را پرداخت می‌کند، اما مقدار ترونی که به کیف پول شما واریز می‌شود کمتر از درخواستی است.\n\n"
        . "🔹 بین ۰ تا ۱۰۰: کارمزد بین شما و کاربر به‌نسبت تقسیم می‌شود.\n\n"
        . "⚠️ توجه: تغییر این عدد فقط نحوه تقسیم کارمزد را مشخص می‌کند و باعث تغییر در فاکتور شما نمی‌شود؛ اگر مطمئن نیستید، آن را روی ۰ نگه دارید.\n\n"
        . "لطفاً عددی بین ۰ تا ۱۰۰ ارسال کنید.\n\nمقدار فعلی: {$currentValue}";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('wageFromBusinessPercentageTronado', $from_id);
} elseif ($user['step'] == "wageFromBusinessPercentageTronado") {
    if (!ctype_digit($text) || (int) $text < 0 || (int) $text > 100) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر است. عددی بین 0 تا 100 ارسال کنید.", $trnado, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "wageFromBusinessPercentageTronado");
    step('home', $from_id);
} elseif ($text == "🔑 ثبت API Key تون‌پی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apitonpay", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $texttonpay = "🔑 کلید API تون‌پی خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
    nm_adminInstantReply($from_id, $texttonpay, $backadmin, 'HTML');
    step('apitonpay', $from_id);
} elseif ($user['step'] == "apitonpay") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $tonpay, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apitonpay");
    step('home', $from_id);
} elseif ($text == "🔑 ثبت API Key اطلس‌پی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiatlaspay", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $textatlaspay = "🔑 کلید API اطلس‌پی خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
    nm_adminInstantReply($from_id, $textatlaspay, $backadmin, 'HTML');
    step('apiatlaspay', $from_id);
} elseif ($user['step'] == "apiatlaspay") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $atlaspay, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiatlaspay");
    step('home', $from_id);
} elseif ($text == "🔑 ثبت API Key تتراپی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apitetrapay", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $texttetrapay = "🔑 کلید API تتراپی خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
    nm_adminInstantReply($from_id, $texttetrapay, $backadmin, 'HTML');
    step('apitetrapay', $from_id);
} elseif ($user['step'] == "apitetrapay") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $tetrapay, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apitetrapay");
    step('home', $from_id);
} elseif ($text == "🌍 ثبت آدرس سرور API تتراپی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiurltetrapay", "select");
    $currentUrl = $PaySetting['ValuePay'] ?? '';
    $currentUrl = $currentUrl !== '' ? $currentUrl : 'ثبت نشده';
    $texttetrapayUrl = "🌍 آدرس سرور API تتراپی خود را اینجا وارد کنید (مثال: https://xxx.xxx.xxx.xxx).\n\nآدرس فعلی شما: {$currentUrl}";
    nm_adminInstantReply($from_id, $texttetrapayUrl, $backadmin, 'HTML');
    step('apiurltetrapay', $from_id);
} elseif ($user['step'] == "apiurltetrapay") {
    $cleanUrl = rtrim(trim($text), '/');
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $tetrapay, 'HTML');
    update("PaySetting", "ValuePay", $cleanUrl, "NamePay", "apiurltetrapay");
    step('home', $from_id);
} elseif ($text == "🔑 ثبت API Key بلوپال" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiblupal", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $blupalWebhookUrl = 'https://' . $domainhosts . '/payment/blupal.php';
    $blupalCallbackUrl = 'https://' . $domainhosts . '/payment/blupal_return.php';
    $textblupal = "🔑 کلید API بلوپال خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}\n\n";
    $textblupal .= "⚠️ پیش از ادامه، آدرس‌های زیر را در داشبورد بلوپال (بخش مدیریت API Key) ثبت کنید تا تأیید خودکار پرداخت‌ها کار کند:\n\n";
    $textblupal .= "🔗 درگاه Webhook:\n<code>{$blupalWebhookUrl}</code>\n\n";
    $textblupal .= "🔙 Callback page:\n<code>{$blupalCallbackUrl}</code>";
    nm_adminInstantReply($from_id, $textblupal, $backadmin, 'HTML');
    step('apiblupal', $from_id);
} elseif ($user['step'] == "apiblupal") {
    $blupalWebhookUrl = 'https://' . $domainhosts . '/payment/blupal.php';
    $blupalCallbackUrl = 'https://' . $domainhosts . '/payment/blupal_return.php';
    update("PaySetting", "ValuePay", $text, "NamePay", "apiblupal");
    $textblupalSaved = $textbotlang['Admin']['SettingnowPayment']['Savaapi'] . "\n\n";
    $textblupalSaved .= "⚠️ فراموش نکنید آدرس‌های زیر را در داشبورد بلوپال (بخش مدیریت API Key) ثبت کنید:\n\n";
    $textblupalSaved .= "🔗 درگاه Webhook:\n<code>{$blupalWebhookUrl}</code>\n\n";
    $textblupalSaved .= "🔙 Callback page:\n<code>{$blupalCallbackUrl}</code>";
    nm_adminInstantReply($from_id, $textblupalSaved, $blupal, 'HTML');
    step('home', $from_id);
} elseif ($text == "🔑 ثبت توکن API کیوب‌پی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apicubepay", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $textcubepay = "🔑 توکن API کیوب‌پی خود را اینجا وارد کنید.\n\nتوکن فعلی شما: {$currentKey}";
    nm_adminInstantReply($from_id, $textcubepay, $backadmin, 'HTML');
    step('apicubepay', $from_id);
} elseif ($user['step'] == "apicubepay") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $cubepay, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apicubepay");
    step('home', $from_id);
} elseif ($text == "🔑 ثبت توکن API واریزا" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apivariza", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $varizaWebhookUrl = 'https://' . $domainhosts . '/payment/variza_webhook.php';
    $textvariza = "🔑 توکن API واریزا خود را اینجا وارد کنید.\n\nتوکن فعلی شما: {$currentKey}\n\n";
    $textvariza .= "⚠️ پیش از ادامه، آدرس زیر را در پنل واریزا (بخش وب‌هوک پروفایل) ثبت کنید تا تأیید خودکار پرداخت‌ها کار کند:\n\n";
    $textvariza .= "🔗 درگاه Webhook:\n<code>{$varizaWebhookUrl}</code>";
    nm_adminInstantReply($from_id, $textvariza, $backadmin, 'HTML');
    step('apivariza', $from_id);
} elseif ($user['step'] == "apivariza") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $variza, 'HTML');
    update("PaySetting", "ValuePay", trim($text), "NamePay", "apivariza");
    step('home', $from_id);
} elseif ($text == "🔐 ثبت کلید وب‌هوک واریزا" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "variza_webhook_secret", "select");
    $currentSecret = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $textvariza = "🔐 کلید وب‌هوک واریزا خود را اینجا وارد کنید (همان کلیدی که در پنل واریزا، بخش وب‌هوک به شما نمایش داده شد).\n\nکلید فعلی شما: {$currentSecret}";
    nm_adminInstantReply($from_id, $textvariza, $backadmin, 'HTML');
    step('variza_webhook_secret', $from_id);
} elseif ($user['step'] == "variza_webhook_secret") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $variza, 'HTML');
    update("PaySetting", "ValuePay", trim($text), "NamePay", "variza_webhook_secret");
    step('home', $from_id);
} elseif ($datain == "affilnecurrencysetting") {
    nm_adminInstantReply($from_id, "یک گزینه را انتخاب کنید", $tronnowpayments, 'HTML');
} elseif ($text == "🗂 نام درگاه کارت به کارت" || $text == "🏷️ نام نمایشی درگاه کارت به کارت") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه کارت به کارت را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("getnamecarttocart", $from_id);
} elseif ($user['step'] == "getnamecarttocart") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $CartManage, 'HTML');
    update("textbot", "text", $text, "id_text", "carttocart");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه nowpayment" || $text == "🏷️ نام نمایشی درگاه nowpayment") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه nowpayment را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
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
} elseif ($text == "🗂 نام درگاه   plisio" || $text == "🏷️ نام نمایشی درگاه plisio") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه plisio را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextnowpayment", $from_id);
} elseif ($user['step'] == "gettextnowpayment") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $NowPaymentsManage, 'HTML');
    update("textbot", "text", $text, "id_text", "textnowpayment");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه رمز ارز آفلاین" || $text == "🏷️ نام نمایشی درگاه رمز ارز آفلاین") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه رمز ارز آفلاین را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextnowpaymentTRON", $from_id);
} elseif ($user['step'] == "gettextnowpaymentTRON") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $tronnowpayments, 'HTML');
    update("textbot", "text", $text, "id_text", "textnowpaymenttron");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه استار" || $text == "🏷️ نام نمایشی درگاه استار") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه استار را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextstartelegram", $from_id);
} elseif ($user['step'] == "gettextstartelegram") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $Startelegram, 'HTML');
    update("textbot", "text", $text, "id_text", "text_star_telegram");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه ترونادو") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه ترونادو را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextiranpay3", $from_id);
} elseif ($user['step'] == "gettextiranpay3") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $trnado, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay3");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه تون‌پی") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه تون‌پی را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettexttonpay", $from_id);
} elseif ($user['step'] == "gettexttonpay") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $tonpay, 'HTML');
    update("textbot", "text", $text, "id_text", "tonpay");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه کیوب‌پی") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه کیوب‌پی را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextcubepay", $from_id);
} elseif ($user['step'] == "gettextcubepay") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $cubepay, 'HTML');
    update("textbot", "text", $text, "id_text", "cubepay");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه واریزا") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه واریزا را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextvariza", $from_id);
} elseif ($user['step'] == "gettextvariza") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $variza, 'HTML');
    update("textbot", "text", $text, "id_text", "variza");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه بلوپال") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه بلوپال را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextblupal", $from_id);
} elseif ($user['step'] == "gettextblupal") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $blupal, 'HTML');
    update("textbot", "text", $text, "id_text", "blupal");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه اطلس‌پی") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه اطلس‌پی را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextatlaspay", $from_id);
} elseif ($user['step'] == "gettextatlaspay") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $atlaspay, 'HTML');
    update("textbot", "text", $text, "id_text", "atlaspay");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه تتراپی") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه تتراپی را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettexttetrapay", $from_id);
} elseif ($user['step'] == "gettexttetrapay") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $tetrapay, 'HTML');
    update("textbot", "text", $text, "id_text", "tetrapay");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ریالی سوم") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextiranpay1", $from_id);
} elseif ($user['step'] == "gettextiranpay1") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $iranpaykeyboard, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay1");
    step("home", $from_id);
} elseif ($text == "🗂 درگاه زرین پال" || $text == "🏷️ نام نمایشی درگاه زرین پال") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه زرین پال را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextzarinpal", $from_id);
} elseif ($user['step'] == "gettextzarinpal") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $keyboardzarinpal, 'HTML');
    update("textbot", "text", $text, "id_text", "zarinpal");
    step("home", $from_id);
} elseif ($text == "⚙️  اینباند اکانت غیرفعال" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocoldisable', $from_id);
} elseif ($user['step'] == "getprotocoldisable") {
    if (!isset($update['message']) && empty($text)) { return; }
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

1 - سفارش های غیرفعال (بیش از ۳۰ روز) حذف خواهند شد
2 - سفارش های پرداخت نشده (بیش از ۳۰ روز) حذف خواهند شد
3 - سفارش های حذف شده توسط ادمین (بیش از ۳۰ روز)
4 - حذف سرویس های تست غیرفعال (بیش از ۳۰ روز)
5 - سفارش های حذف شده توسط کاربر (بیش از ۳۰ روز)
6 - سفارشاتی که زمان یا حجم شان تمام شده باشد (بیش از ۳۰ روز)
7 - فاکتورهای قدیمی پرداخت نشده و ناموفق (بیش از ۳۰ روز)
8 - تراکنش‌های کیف پول قدیمی‌تر از ۳۰ روز
9 - کدهای تخفیف منقضی‌شده (بیش از ۹۰ روز)
10 - درخواست‌های کنسلی و نمایندگی بررسی‌شده (بیش از ۶۰ روز)
11 - کدهای هدیه مصرف‌شده و لاگ‌های کریپتو (بیش از ۹۰ روز)
12 - تیکت‌های قدیمی‌تر از ۳۰ روز
13 - سرویس‌های متصل به پنل حذف‌شده شناسایی و علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز)
14 - تمام سرویس‌های فعال با پنل استعلام می‌شوند و سرویس‌های نامعتبر (کاربر یافت نشد) علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز) — این مرحله ممکن است طول بکشد
15 - سرویس‌هایی که بیش از ۳۰ روز از خریدشان گذشته و هیچ مصرفی نداشته‌اند (۰ مصرف) شناسایی و علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز دیگر) — سرویس‌هایی که حتی مقدار کمی مصرف داشته‌اند دست‌نخورده می‌مانند
16 - سرویس‌هایی که هنگام استعلام، پنل‌شان قابل‌دسترس نبود (خطای اتصال) علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز دیگر، مگر اینکه پنل در همین بازه دوباره در دسترس قرار گیرد)
17 - سرویس‌هایی که حجم یا زمانشان تمام شده و تمدید نشده‌اند علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز دیگر از لحظه‌ی تمام‌شدن)

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
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
    $sixtyDaysAgo = time() - (60 * 24 * 60 * 60);
    $ninetyDaysAgo = time() - (90 * 24 * 60 * 60);

    $chk = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'invalidated_at'");
    if ($chk && $chk->rowCount() !== 1) {
        $pdo->exec("ALTER TABLE invoice ADD invalidated_at INT UNSIGNED NULL DEFAULT NULL");
    }

    $nowStamp = time();
    $stmt = $pdo->prepare(
        "UPDATE invoice SET Status = 'Unsuccessful', invalidated_at = :now
         WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
         AND Service_location IS NOT NULL AND Service_location <> ''
         AND Service_location NOT IN (SELECT name_panel FROM marzban_panel WHERE name_panel IS NOT NULL)"
    );
    $stmt->execute([':now' => $nowStamp]);
    $countorphanedpanel = $stmt->rowCount();

    $countorphanedservice = 0;
    $countunusedservice = 0;
    $countunreachablepanel = 0;
    $countexpiredservice = 0;
    $stmt = $pdo->prepare(
        "SELECT id_invoice, Service_location, username, time_sell, Status, invalidated_at FROM invoice
         WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
         AND Service_location IS NOT NULL AND Service_location <> ''
         AND username IS NOT NULL AND username <> ''"
    );
    $stmt->execute();
    $liveInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($liveInvoices as $liveInvoice) {
        try {
            $scanResult = $ManagePanel->DataUser($liveInvoice['Service_location'], $liveInvoice['username']);
        } catch (Throwable $e) {
            if (empty($liveInvoice['invalidated_at'])) {
                update("invoice", "invalidated_at", $nowStamp, "id_invoice", $liveInvoice['id_invoice']);
                $countunreachablepanel++;
            }
            continue;
        }
        if (isset($scanResult['msg']) && $scanResult['msg'] == "User not found") {
            update("invoice", "invalidated_at", $nowStamp, "id_invoice", $liveInvoice['id_invoice']);
            update("invoice", "Status", "disabledn", "id_invoice", $liveInvoice['id_invoice']);
            $countorphanedservice++;
        } elseif (isset($scanResult['status']) && $scanResult['status'] == "Unsuccessful" && isset($scanResult['msg']) && $scanResult['msg'] == "Panel Not Found") {
            update("invoice", "invalidated_at", $nowStamp, "id_invoice", $liveInvoice['id_invoice']);
            update("invoice", "Status", "Unsuccessful", "id_invoice", $liveInvoice['id_invoice']);
            $countorphanedservice++;
        } elseif ((int)($scanResult['used_traffic'] ?? 0) === 0) {
            $purchasedAt = is_numeric($liveInvoice['time_sell']) ? (int)$liveInvoice['time_sell'] : null;
            if ($purchasedAt !== null && $purchasedAt < $thirtyDaysAgo) {
                update("invoice", "invalidated_at", $nowStamp, "id_invoice", $liveInvoice['id_invoice']);
                update("invoice", "Status", "unusedservice", "id_invoice", $liveInvoice['id_invoice']);
                $countunusedservice++;
            }
        } elseif (in_array($liveInvoice['Status'], ['end_of_time', 'end_of_volume'], true)) {
            if (empty($liveInvoice['invalidated_at'])) {
                update("invoice", "invalidated_at", $nowStamp, "id_invoice", $liveInvoice['id_invoice']);
                $countexpiredservice++;
            }
        } elseif (!empty($liveInvoice['invalidated_at'])) {
            update("invoice", "invalidated_at", null, "id_invoice", $liveInvoice['id_invoice']);
        }
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countunpiadorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countdisableorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE (Status = 'removebyadmin' OR Status = 'removedbyadmin') AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countremoveadminorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countdisableordtester = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid' AND name_product = 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countoldunpaid = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE (Status = 'removeTime' OR Status = 'removevolume' OR Status = 'removebyuser') AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countremovedservices = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE (Status = 'disabledn' OR Status = 'Unsuccessful') AND COALESCE(invalidated_at, CAST(time_sell AS UNSIGNED)) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countfailedorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'unusedservice' AND invalidated_at IS NOT NULL AND invalidated_at < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countunusedexpired = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM invoice
         WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
         AND invalidated_at IS NOT NULL AND invalidated_at < :cutoff"
    );
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countstalelive = (int)$stmt->fetchColumn();

    $paymentCutoff = date('Y/m/d H:i:s', $thirtyDaysAgo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE payment_Status IN ('expire','reject') AND time < :cutoff");
    $stmt->execute([':cutoff' => $paymentCutoff]);
    $countpayexpired = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_ledger WHERE created_at < FROM_UNIXTIME(:cutoff)");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $countledgerexpired = (int)$stmt->fetchColumn();

    $countdiscountexpired = 0;
    $chk = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'status'");
    if ($chk && $chk->rowCount() === 1) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM DiscountSell WHERE status = 'expired' AND time IS NOT NULL AND time <> '' AND CAST(time AS UNSIGNED) < :cutoff");
        $stmt->execute([':cutoff' => $ninetyDaysAgo]);
        $countdiscountexpired += (int)$stmt->fetchColumn();
    }
    $chk = $pdo->query("SHOW COLUMNS FROM Discount LIKE 'expire_at'");
    if ($chk && $chk->rowCount() === 1) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Discount WHERE status = 'expired' AND expire_at IS NOT NULL AND expire_at <> '' AND CAST(expire_at AS UNSIGNED) < :cutoff");
        $stmt->execute([':cutoff' => $ninetyDaysAgo]);
        $countdiscountexpired += (int)$stmt->fetchColumn();
    }

    $countcancelrequests = 0;
    $chk = $pdo->query("SHOW COLUMNS FROM cancel_service LIKE 'resolved_at'");
    if ($chk && $chk->rowCount() === 1) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cancel_service WHERE status IN ('accept','reject') AND resolved_at IS NOT NULL AND resolved_at <> '' AND CAST(resolved_at AS UNSIGNED) < :cutoff");
        $stmt->execute([':cutoff' => $sixtyDaysAgo]);
        $countcancelrequests = (int)$stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Requestagent WHERE status IN ('accept','reject') AND time IS NOT NULL AND time <> '' AND CAST(time AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $sixtyDaysAgo]);
    $countagentrequests = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Giftcodeconsumed WHERE consumed_at IS NOT NULL AND consumed_at <> '' AND CAST(consumed_at AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $ninetyDaysAgo]);
    $countgiftcodes = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crypto_verified_hashes WHERE verified_at < FROM_UNIXTIME(:cutoff)");
    $stmt->execute([':cutoff' => $ninetyDaysAgo]);
    $countcryptologs = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crypto_sender_locks WHERE COALESCE(last_used_at, first_seen_at) < FROM_UNIXTIME(:cutoff)");
    $stmt->execute([':cutoff' => $ninetyDaysAgo]);
    $countcryptologs += (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyadmin' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removedbyadmin' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removeTime' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removevolume' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyuser' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'unpaid' AND name_product = 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE (Status = 'disabledn' OR Status = 'Unsuccessful') AND COALESCE(invalidated_at, CAST(time_sell AS UNSIGNED)) < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'unusedservice' AND invalidated_at IS NOT NULL AND invalidated_at < :cutoff");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare(
        "DELETE FROM invoice
         WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
         AND invalidated_at IS NOT NULL AND invalidated_at < :cutoff"
    );
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);
    $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE payment_Status IN ('expire','reject') AND time < :cutoff");
    $stmt->execute([':cutoff' => $paymentCutoff]);

    $stmt = $pdo->prepare("DELETE FROM wallet_ledger WHERE created_at < FROM_UNIXTIME(:cutoff)");
    $stmt->execute([':cutoff' => $thirtyDaysAgo]);

    $chk = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'status'");
    if ($chk && $chk->rowCount() === 1) {
        $stmt = $pdo->prepare("DELETE FROM DiscountSell WHERE status = 'expired' AND time IS NOT NULL AND time <> '' AND CAST(time AS UNSIGNED) < :cutoff");
        $stmt->execute([':cutoff' => $ninetyDaysAgo]);
    }
    $chk = $pdo->query("SHOW COLUMNS FROM Discount LIKE 'expire_at'");
    if ($chk && $chk->rowCount() === 1) {
        $stmt = $pdo->prepare("DELETE FROM Discount WHERE status = 'expired' AND expire_at IS NOT NULL AND expire_at <> '' AND CAST(expire_at AS UNSIGNED) < :cutoff");
        $stmt->execute([':cutoff' => $ninetyDaysAgo]);
    }

    $chk = $pdo->query("SHOW COLUMNS FROM cancel_service LIKE 'resolved_at'");
    if ($chk && $chk->rowCount() === 1) {
        $stmt = $pdo->prepare("DELETE FROM cancel_service WHERE status IN ('accept','reject') AND resolved_at IS NOT NULL AND resolved_at <> '' AND CAST(resolved_at AS UNSIGNED) < :cutoff");
        $stmt->execute([':cutoff' => $sixtyDaysAgo]);
    }

    $stmt = $pdo->prepare("DELETE FROM Requestagent WHERE status IN ('accept','reject') AND time IS NOT NULL AND time <> '' AND CAST(time AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $sixtyDaysAgo]);

    $stmt = $pdo->prepare("DELETE FROM Giftcodeconsumed WHERE consumed_at IS NOT NULL AND consumed_at <> '' AND CAST(consumed_at AS UNSIGNED) < :cutoff");
    $stmt->execute([':cutoff' => $ninetyDaysAgo]);

    $stmt = $pdo->prepare("DELETE FROM crypto_verified_hashes WHERE verified_at < FROM_UNIXTIME(:cutoff)");
    $stmt->execute([':cutoff' => $ninetyDaysAgo]);

    $stmt = $pdo->prepare("DELETE FROM crypto_sender_locks WHERE COALESCE(last_used_at, first_seen_at) < FROM_UNIXTIME(:cutoff)");
    $stmt->execute([':cutoff' => $ninetyDaysAgo]);

    $ticketCutoff = date('Y/m/d H:i:s', $thirtyDaysAgo);
    $stmt = $pdo->prepare("SELECT Tracking FROM support_message GROUP BY Tracking HAVING MIN(time) < :cutoff");
    $stmt->execute([':cutoff' => $ticketCutoff]);
    $oldTrackings = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $countoldtickets = 0;
    if (!empty($oldTrackings)) {
        $delReact = $pdo->prepare(
            "DELETE r FROM support_message_reaction r
             INNER JOIN support_message m ON m.id = r.message_id
             WHERE m.Tracking = :t"
        );
        $delMsg = $pdo->prepare("DELETE FROM support_message WHERE Tracking = :t");
        foreach ($oldTrackings as $tracking) {
            $delReact->execute([':t' => $tracking]);
            $delMsg->execute([':t' => $tracking]);
            $countoldtickets++;
        }
    }

    $optimizebot = "✅ بهینه سازی با موفقیت انجام شد

📊 خلاصه عملیات:
✅ {$countunpiadorder} سفارش پرداخت نشده (بیش از ۳۰ روز) حذف گردید
✅ {$countdisableorder} سفارش غیرفعال (بیش از ۳۰ روز) حذف گردید
✅ {$countremoveadminorder} سفارش حذف شده توسط ادمین (بیش از ۳۰ روز) پاک گردید
✅ {$countdisableordtester} سرویس تست غیرفعال (بیش از ۳۰ روز) حذف گردید
✅ {$countoldunpaid} فاکتور تست پرداخت نشده (بیش از ۳۰ روز) پاک گردید
✅ {$countremovedservices} سفارشی که زمان یا حجم یا توسط کاربر حذف شده (بیش از ۳۰ روز) پاک گردید
✅ {$countfailedorder} سفارش ناموفق/نامعتبر (بیش از ۳۰ روز) پاک گردید
✅ {$countpayexpired} گزارش تراکنش منقضی/رد شده (بیش از ۳۰ روز) پاک گردید
✅ {$countledgerexpired} تراکنش کیف پول قدیمی‌تر از ۳۰ روز پاک گردید
✅ {$countdiscountexpired} کد تخفیف منقضی‌شده (بیش از ۹۰ روز) پاک گردید
✅ {$countcancelrequests} درخواست کنسلی بررسی‌شده (بیش از ۶۰ روز) پاک گردید
✅ {$countagentrequests} درخواست نمایندگی بررسی‌شده (بیش از ۶۰ روز) پاک گردید
✅ {$countgiftcodes} کد هدیه مصرف‌شده (بیش از ۹۰ روز) پاک گردید
✅ {$countcryptologs} لاگ کریپتوی قدیمی‌تر از ۹۰ روز پاک گردید
✅ {$countoldtickets} تیکت قدیمی‌تر از ۳۰ روز پاک گردید
✅ {$countorphanedpanel} سرویس متصل به پنل حذف‌شده شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countorphanedservice} سرویس نامعتبر (کاربر یافت نشد روی پنل) شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countunusedservice} سرویس بدون مصرف (۰ مصرف، بیش از ۳۰ روز از خرید) شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countunusedexpired} سرویس بدون مصرف که مهلتش تمام شده حذف گردید
✅ {$countunreachablepanel} سرویس با پنل غیرقابل‌دسترس شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countexpiredservice} سرویس با حجم/زمان تمام‌شده که تمدید نشده شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countstalelive} سرویس علامت‌خورده (پنل غیرقابل‌دسترس یا حجم/زمان تمام‌شده) که مهلتش تمام شده حذف گردید";

    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $optimizebot, null);
    }
    nm_adminInstantReply($from_id, $optimizebot, $setting_panel, 'HTML');

    $time = time();
    $logss = "optimize_{$countunpiadorder}_{$countdisableorder}_{$countremoveadminorder}_{$countdisableordtester}_{$countdiscountexpired}_{$countcancelrequests}_{$countagentrequests}_{$countgiftcodes}_{$countcryptologs}_{$countorphanedpanel}_{$countorphanedservice}_{$countunusedservice}_{$countunusedexpired}_{$countunreachablepanel}_{$countexpiredservice}_{$countstalelive}_$time";
    file_put_contents('log.txt', "\n" . $logss, FILE_APPEND);
} elseif ($datain == "settimecornvolume") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تنظیم کنید که اگر حجم کاربر به x رسید پیام اخطار ارسال شود. حجم را براساس گیگ ارسال نمایید.", $backadmin, 'HTML');
    step("getvolumewarn", $from_id);
} elseif ($user['step'] == "getvolumewarn") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    update("setting", "volumewarn", $text);
    nm_adminInstantReply($from_id, "✅ تغییرات با موفقیت ذخیره شد", $setting_panel, 'HTML');
    step("home", $from_id);
} elseif ($text == "🔧 کانفیگ دستی") {
    savedata("clear", "idpanel", $user['Processing_value']);
    nm_adminInstantReply($from_id, "📌در این بخش میتوانید یک سفارش را بطور دستی ایجاد و دریافت کنید
⚠️ در صورتی که می خواهید  کانفیگ به حساب کاربر اضافه شود و کاربر مدیریت کند باید از گزینه افزودن سفارش  استفاده نمایید.
- برای اضافه کردن کانفیگ ابتدا نام کاربری را ارسال نمایید.", $backadmin, 'HTML');
    step('getusernameconfigcr', $from_id);
} elseif ($user['step'] == "getusernameconfigcr") {
    $text = str_replace('_', '-', $text);
    if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
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
        $usernameconfig = str_replace('_', '-', $user['Processing_value_one']) . "-" . $i;
        $dataoutput = $ManagePanel->createUser($userdata['idpanel'], "usertest", $usernameconfig, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            nm_adminInstantReply($from_id, $textbotlang['users']['sell']['ErrorConfig'], null, 'HTML');
            $texterros = "
⭕️ یک کاربر قصد دریافت اکانت داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
<blockquote>✍️ دلیل خطا : {$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$panel['name_panel']}</blockquote>";
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
        $output_config_link = $panel['sublink'] == "onsublink" ? rxResolveConnectionLink($panel, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
        $config = "";
        if ($panel['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $link) {
                $config .= "\n" . $link;
            }
        }
        $datatextbot['textafterpay'] = $panel['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $panel['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
        if (intval($text) == 0)
            $text = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', "پلن دلخواه", $textcreatuser);
        $textcreatuser = str_replace('{location}', $panel['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $text, $textcreatuser);
        $textcreatuser = str_replace('{volume}', $user['Processing_value_tow'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        if ($panel['type'] == "Manualsale") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
        }
        sendMessageService($panel, $dataoutput['configs'], $output_config_link, $dataoutput['username'], null, $textcreatuser, $randomString);
    }
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
    $text_report = "";
    if (strlen($setting['Channel_Report']) > 0) {
        $text_report = " 🛍 ساخت کانفیگ توسط ادمین

<blockquote>نام کاربری کانفیگ : {$user['Processing_value_one']}</blockquote>
<blockquote>حجم کانفیگ  : {$user['Processing_value_tow']} گیگ</blockquote>
<blockquote>زمان کانفیگ : $text روز</blockquote>
<blockquote>آیدی عددی ادمین : $from_id</blockquote>
<blockquote>نام کاربری ادمین : $username</blockquote>
<blockquote>تعداد ساخت : {$userdata['count']}</blockquote>";
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
    $panelRow = function_exists('rx_resolvePanelFromInput') ? rx_resolvePanelFromInput($text, $pdo) : select("marzban_panel", "*", "name_panel", $text, "select");
    if (is_array($panelRow) && !empty($panelRow)) {
        $canonicalName = $panelRow['name_panel'];
        update("user", "Processing_value", $canonicalName, "id", $from_id);
        $typepanel = $panelRow['type'] ?? '';
        if ($typepanel == "marzban") {
            nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
        } elseif ($typepanel == "x-ui_single") {
            nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
        } elseif ($typepanel == "WGDashboard") {
            nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
        } elseif ($typepanel == "remnawave") {
            nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $option_remnawave, 'HTML');
        } elseif ($typepanel == "rebecca") {
            nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionRebecca, 'HTML');
        } elseif ($typepanel == "guard") {
            nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionGuard, 'HTML');
        } elseif ($typepanel == "pasarguard") {
            nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionPasarGuard, 'HTML');
        } elseif ($typepanel == "Manualsale") {
            nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionManualsale, 'HTML');
        }
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
                ['text' => "🗂 نام نود", 'callback_data' => "changenamenode"],
                ['text' => "🔄 ضریب مصرف نود", 'callback_data' => "changecoefficient"],
            ],
            [
                ['text' => "🌍 آدرس IP نود", 'callback_data' => "changeipnode"],
                ['text' => "♻️ اتصال مجدد", 'callback_data' => "reconnectnode"],
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
    if (!isset($update['message']) && empty($text)) { return; }
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
    if (!isset($update['message']) && empty($text)) { return; }
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
    if (!isset($update['message']) && empty($text)) { return; }
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
} elseif ($text == "💎 مالی و گزارشات" && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_finance', $from_id);
    $Bot_Status = buildPaymentGatewayKeyboard($textbotlang);
    nm_adminInstantReply($from_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم فاکسیما هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status, 'HTML');
} elseif ($text == "🎁 کش بک تمدید" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 مقدار درصدی که می خواهید حساب کاربر بعد از تمدید به عنوان هدیه شارژ شود را ارسال کنید.
⚠️ در صورتی که میخواهید غیرفعال باشد عدد 0 را ارسال کنید", $backadmin, 'HTML');
    step('getpricecashback', $from_id);
} elseif ($user['step'] == "getpricecashback") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidtime'] ?? '❌ زمان نامعتبر است', $backadmin, 'HTML');
        return;
    }
    savedata("clear", "price_cashback", $text);
    nm_adminInstantReply($from_id, "📌 نوع کاربری را انتخاب نمایید", rx_agentGroupKeyboard(false), 'HTML');
    step('getagent', $from_id);
} elseif ($user['step'] == "getagent") {
    $text = rx_resolveAgentGroupFromReplyButton($text, ['f', 'n', 'n2']);
    if ($text === null) {
        nm_adminInstantReply($from_id, "❌ گروه کاربری نامعتبر است", rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    savedata("save", "cashback_agent_group", $text);
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step('getrenewcashtarget', $from_id);
} elseif ($user['step'] == "getrenewcashtarget") {
    $resolvedTarget = rx_resolveCashbackTargetFromReplyButton($text);
    if ($resolvedTarget === null) {
        nm_adminInstantReply($from_id, "❌ گزینه نامعتبر است، لطفا از دکمه های زیر انتخاب کنید", rx_cashbackTargetKeyboard(), 'HTML');
        return;
    }
    if ($resolvedTarget === 'custom') {
        savedata("save", "cashback_target", $resolvedTarget);
        nm_adminInstantReply($from_id, "📌 تعداد روز مورد نظر را به صورت عدد وارد کنید (بین 1 تا 365)", $backadmin, 'HTML');
        step("getrenewcashtargetdays", $from_id);
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $agentGroup = $userdata['cashback_agent_group'] ?? 'f';
    if ($agentGroup == "f") {
        update("shopSetting", "value", $userdata['price_cashback'], "Namevalue", "chashbackextend");
    } else {
        $shop_cashbackagent = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent")['value'], true);
        $shop_cashbackagent[$agentGroup] = $userdata['price_cashback'];
        update("shopSetting", "value", json_encode($shop_cashbackagent), "Namevalue", "chashbackextend_agent");
    }
    update("shopSetting", "value", $resolvedTarget, "Namevalue", "chashbackextend_target");
    nm_adminInstantReply($from_id, "✅ مبلغ و جامعه هدف با موفقیت تنظیم شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "getrenewcashtargetdays") {
    if (!ctype_digit($text) || (int)$text < 1 || (int)$text > 365) {
        nm_adminInstantReply($from_id, "❌ لطفا یک عدد صحیح بین 1 تا 365 ارسال کنید", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $agentGroup = $userdata['cashback_agent_group'] ?? 'f';
    if ($agentGroup == "f") {
        update("shopSetting", "value", $userdata['price_cashback'], "Namevalue", "chashbackextend");
    } else {
        $shop_cashbackagent = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent")['value'], true);
        $shop_cashbackagent[$agentGroup] = $userdata['price_cashback'];
        update("shopSetting", "value", json_encode($shop_cashbackagent), "Namevalue", "chashbackextend_agent");
    }
    update("shopSetting", "value", "custom:" . (int)$text, "Namevalue", "chashbackextend_target");
    nm_adminInstantReply($from_id, "✅ مبلغ و جامعه هدف با موفقیت تنظیم شد", $shopkeyboard, 'HTML');
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
    } elseif ($type == "arzireyali2") {
        if ($value == "onternado") {
            $valuenew = "offternado";
        } else {
            $valuenew = "onternado";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustarnado");
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
    } elseif ($type == "tonpay") {
        if ($value == "ontonpay") {
            $valuenew = "offtonpay";
        } else {
            $valuenew = "ontonpay";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustonpay");
    } elseif ($type == "cubepay") {
        if ($value == "oncubepay") {
            $valuenew = "offcubepay";
        } else {
            $valuenew = "oncubepay";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statuscubepay");
    } elseif ($type == "variza") {
        if ($value == "onvariza") {
            $valuenew = "offvariza";
        } else {
            $valuenew = "onvariza";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusvariza");
    } elseif ($type == "blupal") {
        if ($value == "onblupal") {
            $valuenew = "offblupal";
        } else {
            $valuenew = "onblupal";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusblupal");
    } elseif ($type == "atlaspay") {
        if ($value == "onatlaspay") {
            $valuenew = "offatlaspay";
        } else {
            $valuenew = "onatlaspay";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusatlaspay");
    } elseif ($type == "tetrapay") {
        if ($value == "ontetrapay") {
            $valuenew = "offtetrapay";
        } else {
            $valuenew = "ontetrapay";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustetrapay");
    }
    $Bot_Status = buildPaymentGatewayKeyboard($textbotlang);
    Editmessagetext($from_id, $message_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم فاکسیما هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status);
} elseif ($text == "💰 کش‌بک کارت") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashcart", $from_id);
} elseif ($user['step'] == "getcashcart") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbackcart");
    savedata("save", "cashback_menu", "CartManage");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "💰 کش بک ترونادو") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay2", $from_id);
} elseif ($user['step'] == "getcashiranpay2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbackiranpay2");
    savedata("save", "cashback_menu", "trnado");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "💰 کش بک تون‌پی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashtonpay", $from_id);
} elseif ($user['step'] == "getcashtonpay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbacktonpay");
    savedata("save", "cashback_menu", "tonpay");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "💰 کش بک بلوپال") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashblupal", $from_id);
} elseif ($user['step'] == "getcashblupal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbackblupal");
    savedata("save", "cashback_menu", "blupal");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "💰 کش بک واریزا") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashvariza", $from_id);
} elseif ($user['step'] == "getcashvariza") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbackvariza");
    savedata("save", "cashback_menu", "variza");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "💰 کش بک اطلس‌پی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashatlaspay", $from_id);
} elseif ($user['step'] == "getcashatlaspay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbackatlaspay");
    savedata("save", "cashback_menu", "atlaspay");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "💰 کش بک تتراپی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashtetrapay", $from_id);
} elseif ($user['step'] == "getcashtetrapay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbacktetrapay");
    savedata("save", "cashback_menu", "tetrapay");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "💰 کش بک کیوب‌پی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashcubepay", $from_id);
} elseif ($user['step'] == "getcashcubepay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbackcubepay");
    savedata("save", "cashback_menu", "cubepay");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "⚖️ کارمزد کیوب‌پی") {
    $currentfeecubepay = getPaySettingValue('feecubepay', '0');
    $textfeecubepay = "⚖️ کارمزد کیوب‌پی را چه کسی بپردازد؟\n\n"
        . "کیوب‌پی بابت هر تراکنش کارمزدی از کیف پول شما کم می‌کند. اینجا می‌توانید آن هزینه را روی فاکتور بگذارید تا کاربر پرداختش کند:\n\n"
        . "🔹 صفر (پیش‌فرض): غیرفعال. کارمزد را خودتان می‌پردازید.\n"
        . "🔹 عدد ۱ تا ۱۰۰: همان درصد به مبلغ فاکتور اضافه می‌شود (اعشار مجاز است، مثلاً 9.9).\n"
        . "🔹 عدد بالای ۱۰۰: همان مبلغ به تومان به فاکتور اضافه می‌شود.\n\n"
        . "⚠️ فقط مبلغ پرداختی بزرگ‌تر می‌شود؛ اعتباری که به کاربر داده می‌شود همان مبلغ درخواستی خودش است.\n\n"
        . "📌 مقدار فعلی: <code>" . htmlspecialchars((string) $currentfeecubepay, ENT_QUOTES, 'UTF-8') . "</code>";
    nm_adminInstantReply($from_id, $textfeecubepay, $backadmin, 'HTML');
    step("getfeecubepay", $from_id);
} elseif ($user['step'] == "getfeecubepay") {
    $feevalue = str_replace([',', '،'], '', trim($text));
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $feevalue)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ کارمزد کیوب‌پی تنظیم گردید.", $cubepay, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $feevalue, "NamePay", "feecubepay");
} elseif ($text == "💰 کش بک plisio") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashplisio", $from_id);
} elseif ($user['step'] == "getcashplisio") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbackplisio");
    savedata("save", "cashback_menu", "NowPaymentsManage");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "💰 کش بک nowpayment") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashnowpayment", $from_id);
} elseif ($user['step'] == "getcashnowpayment") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "cashbacknowpayment");
    savedata("save", "cashback_menu", "nowpayment_setting_keyboard");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($text == "💰 کش بک زرین پال") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashzarinpal", $from_id);
} elseif ($user['step'] == "getcashzarinpal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "cashback_percent", $text);
    savedata("save", "cashback_key", "chashbackzarinpal");
    savedata("save", "cashback_menu", "keyboardzarinpal");
    nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", rx_cashbackTargetKeyboard(), 'HTML');
    step("getcashtarget", $from_id);
} elseif ($user['step'] == "getcashtarget") {
    $resolvedTarget = rx_resolveCashbackTargetFromReplyButton($text);
    if ($resolvedTarget === null) {
        nm_adminInstantReply($from_id, "❌ گزینه نامعتبر است، لطفا از دکمه های زیر انتخاب کنید", rx_cashbackTargetKeyboard(), 'HTML');
        return;
    }
    if ($resolvedTarget === 'custom') {
        savedata("save", "cashback_target", $resolvedTarget);
        nm_adminInstantReply($from_id, "📌 تعداد روز مورد نظر را به صورت عدد وارد کنید (بین 1 تا 365)", $backadmin, 'HTML');
        step("getcashtargetdays", $from_id);
        return;
    }
    savedata("save", "cashback_target", $resolvedTarget);
    savedata("save", "cashback_scope", rx_cashbackScopeDefault());
    nm_adminInstantReply($from_id, "📌 این کش‌بک روی کدام بخش‌ها اعمال شود؟", json_encode(['inline_keyboard' => rx_cashbackScopeKeyboardRows('cbscope_', rx_cashbackScopeDefault())], JSON_UNESCAPED_UNICODE), 'HTML');
    step("getcashscope", $from_id);
} elseif ($user['step'] == "getcashtargetdays") {
    if (!ctype_digit($text) || (int)$text < 1 || (int)$text > 365) {
        nm_adminInstantReply($from_id, "❌ لطفا یک عدد صحیح بین 1 تا 365 ارسال کنید", $backadmin, 'HTML');
        return;
    }
    savedata("save", "cashback_target", "custom:" . (int)$text);
    savedata("save", "cashback_scope", rx_cashbackScopeDefault());
    nm_adminInstantReply($from_id, "📌 این کش‌بک روی کدام بخش‌ها اعمال شود؟", json_encode(['inline_keyboard' => rx_cashbackScopeKeyboardRows('cbscope_', rx_cashbackScopeDefault())], JSON_UNESCAPED_UNICODE), 'HTML');
    step("getcashscope", $from_id);
} elseif (preg_match('/^cbscope_toggle#(\w+)/', $datain, $dataget) && $user['step'] == "getcashscope") {
    $scopeKey = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    $selected = is_array($userdata['cashback_scope'] ?? null) ? $userdata['cashback_scope'] : rx_cashbackScopeDefault();
    if (in_array($scopeKey, $selected, true)) {
        $selected = array_values(array_diff($selected, [$scopeKey]));
    } else {
        $selected[] = $scopeKey;
        $selected = array_values($selected);
    }
    savedata("save", "cashback_scope", $selected);
    Editmessagetext($from_id, $message_id, "📌 این کش‌بک روی کدام بخش‌ها اعمال شود؟", json_encode(['inline_keyboard' => rx_cashbackScopeKeyboardRows('cbscope_', $selected)], JSON_UNESCAPED_UNICODE), 'HTML');
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
} elseif ($datain == "cbscope_all" && $user['step'] == "getcashscope") {
    $userdata = json_decode($user['Processing_value'], true);
    $selected = is_array($userdata['cashback_scope'] ?? null) ? $userdata['cashback_scope'] : [];
    $allDefault = rx_cashbackScopeDefault();
    $isAllSelected = count(array_diff($allDefault, $selected)) === 0 && count($selected) === count($allDefault);
    $selected = $isAllSelected ? [] : $allDefault;
    savedata("save", "cashback_scope", $selected);
    Editmessagetext($from_id, $message_id, "📌 این کش‌بک روی کدام بخش‌ها اعمال شود؟", json_encode(['inline_keyboard' => rx_cashbackScopeKeyboardRows('cbscope_', $selected)], JSON_UNESCAPED_UNICODE), 'HTML');
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
} elseif ($datain == "cbscope_confirm" && $user['step'] == "getcashscope") {
    $cashbackMenus = [
        'CartManage' => $CartManage,
        'trnado' => $trnado,
        'tonpay' => $tonpay,
        'blupal' => $blupal,
        'cubepay' => $cubepay,
        'NowPaymentsManage' => $NowPaymentsManage,
        'nowpayment_setting_keyboard' => $nowpayment_setting_keyboard,
        'keyboardzarinpal' => $keyboardzarinpal,
    ];
    $userdata = json_decode($user['Processing_value'], true);
    $cashbackKey = $userdata['cashback_key'] ?? '';
    $cashbackPercent = $userdata['cashback_percent'] ?? '0';
    $cashbackMenuKey = $userdata['cashback_menu'] ?? '';
    $cashbackTarget = $userdata['cashback_target'] ?? 'all';
    $selectedScope = is_array($userdata['cashback_scope'] ?? null) ? $userdata['cashback_scope'] : rx_cashbackScopeDefault();
    if ($cashbackKey === '') {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $textbotlang['Admin']['agent']['invalidvlue'], 'show_alert' => true]);
        step("home", $from_id);
        return;
    }
    if (empty($selectedScope)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ حداقل یک بخش را انتخاب کنید یا گزینه «همه موارد» را بزنید.', 'show_alert' => true]);
        return;
    }
    $allDefault = rx_cashbackScopeDefault();
    $isAllSelected = count(array_diff($allDefault, $selectedScope)) === 0 && count($selectedScope) === count($allDefault);
    $scopeToSave = $isAllSelected ? 'all' : implode(',', $selectedScope);
    update("PaySetting", "ValuePay", $cashbackPercent, "NamePay", $cashbackKey);
    update("PaySetting", "ValuePay", $cashbackTarget, "NamePay", $cashbackKey . "_target");
    update("PaySetting", "ValuePay", $scopeToSave, "NamePay", $cashbackKey . "_scope");
    $backKeyboard = $cashbackMenus[$cashbackMenuKey] ?? $backadmin;
    if (!empty($message_id)) {
        deletemessage($from_id, $message_id);
    }
    nm_adminInstantReply($from_id, "✅ مبلغ، جامعه هدف و بخش‌های اعمال با موفقیت ذخیره گردید.", $backKeyboard, 'HTML');
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
    step("home", $from_id);
} elseif ($text == "🌐 وضعیت نت ملی" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $newStatus = (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel)) ? 'off_national_net' : 'on_national_net';
    update("marzban_panel", "national_net_status", $newStatus, "code_panel", $panel['code_panel']);
    if ($newStatus === 'on_national_net' && empty($panel['stock_source_panel'])) {
        update("marzban_panel", "stock_source_panel", $panel['code_panel'], "code_panel", $panel['code_panel']);
    }
    if ($newStatus === 'on_national_net') {
        nm_replyOrEdit($from_id, "✅ وضعیت نت ملی برای این پنل روشن شد.\n\n📦 حالا کانفیگ‌های انبار و بقیه تنظیمات (افزودن پلن و ...) را از «پنل وب» انجام دهید.\n\nℹ️ از این پس خرید کاربر از این پنل، از انبار تحویل داده می‌شود.", $keyboardadmin, 'HTML');
    } else {
        nm_replyOrEdit($from_id, "❌ وضعیت نت ملی برای این پنل خاموش شد. خرید دوباره مستقیم روی پنل انجام می‌شود.", $keyboardadmin, 'HTML');
    }
    step('home', $from_id);
} elseif ($text == "🔄 تغییر نوع پنل" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : null;
    if (!$panel) {
        nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "code_panel", $panel['code_panel']);
    savedata("save", "old_type", $panel['type']);
    savedata("save", "name_panel", $panel['name_panel']);
    nm_replyOrEdit($from_id, $textbotlang['Admin']['managepanel']['Inbound']['gettypepanel'], $keyboardswitchtype, 'HTML');
    step("switchtype_pick", $from_id);
} elseif ($text == "➕ افزودن کانفیگ") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, "📌 برای اضافه کردن کانفیگ ابتدا یک نام ارسال نمایید.", $backadmin, 'HTML');
    step('getnameconfigm', $from_id);
    savedata("clear", "namepanel", $panelName);
} elseif ($user['step'] == "getnameconfigm") {
    if (!isset($update['message']) && empty($text)) { return; }
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
    if (!isset($update['message']) && empty($text)) { return; }
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
    if (!isset($update['message']) && empty($text)) { return; }
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
    if (!isset($update['message']) && empty($text)) { return; }
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
} elseif ($text == "🌍 قیمت تغییر مکان" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 قیمت تغییر لوکیشن از سایر پنل‌ها به این پنل را ارسال کنید", $backadmin, 'HTML');
    step('setpricechangelocation', $from_id);
} elseif ($user['step'] == "setpricechangelocation") {
    if (!isset($update['message']) && empty($text)) { return; }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], "📌قیمت تغییر لوکیشن با موفقیت تغییر کرد");
    update("marzban_panel", "priceChangeloc", $text, "name_panel", $user['Processing_value']);
    step('PanelMenu', $from_id);
} elseif ($text == "➕ قیمت حجم اضافه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 قیمت حجم اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPriceExtra', $from_id);
} elseif ($user['step'] == "GetPriceExtra") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypeextra', $from_id);
} elseif ($user['step'] == "gettypeextra") {
    if (!isset($update['message']) && empty($text)) {
        return;
    }
    $agentst = ["n", "n2", "f", "all"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(true), 'HTML');
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
    step('PanelMenu', $from_id);
} elseif ($text == "⚙️ قیمت حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 قیمت حجم اضافه دلخواه این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPricecustomvo', $from_id);
} elseif ($user['step'] == "GetPricecustomvo") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypeextracustom', $from_id);
} elseif ($user['step'] == "gettypeextracustom") {
    if (!isset($update['message']) && empty($text)) {
        return;
    }
    $agentst = ["n", "n2", "f", "all"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(true), 'HTML');
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
    step('PanelMenu', $from_id);
} elseif ($text == "⏳ قیمت زمان اضافه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 قیمت زمان اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPricetimeextra', $from_id);
} elseif ($user['step'] == "GetPricetimeextra") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypeextratime', $from_id);
} elseif ($user['step'] == "gettypeextratime") {

    if (!isset($update['message']) && empty($text)) {
        return;
    }

    $agentst = ["n", "n2", "f", "all"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agentst);


    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(true), 'HTML');
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
    step('PanelMenu', $from_id);

} elseif ($text == "⏳ قیمت زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 قیمت زمان دلخواه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPriceExtratime', $from_id);
} elseif ($user['step'] == "GetPriceExtratime") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypeextratimecustom', $from_id);
} elseif ($user['step'] == "gettypeextratimecustom") {
    if (!isset($update['message']) && empty($text)) {
        return;
    }
    $agentst = ["n", "n2", "f", "all"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(true), 'HTML');
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
    step('PanelMenu', $from_id);
} elseif ($text == "🔒 کارت پس از اولین پرداخت" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
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
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
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
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
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
    if (!isset($update['message']) && empty($text)) { return; }
    nm_adminInstantReply($from_id, "یکی از گزینه های زیر را انتخاب کنید ", $configedit, 'HTML');
    step("home", $from_id);
    update("user", "Processing_value_one", $text, "id", $from_id);
} elseif ($text == "مخشصات کانفیگ") {
    nm_adminInstantReply($from_id, "محتوا جدید کانفیگ را ارسال کنید", $backadmin, 'HTML');
    step("getcontentedit", $from_id);
} elseif ($user['step'] == "getcontentedit") {
    if (!isset($update['message']) && empty($text)) { return; }
    nm_adminInstantReply($from_id, "✅ ذخیره گردید.", $optionManualsale, 'HTML');
    update("manualsell", "contentrecord", $text, "namerecord", $user['Processing_value_one']);
} elseif ($text == "⬆️ افزایش قیمت") {
    nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید افزایش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
    step("getaddpricepeoductloc", $from_id);
} elseif ($user['step'] == "getaddpricepeoductloc") {
    nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود؟
یکی از گزینه‌های زیر را انتخاب یا ارسال کنید:
👤 کاربر عادی (f)
🤝 نماینده عادی (n)
💎 نماینده پیشرفته (n2)", rx_agentGroupKeyboard(false), 'HTML');
    savedata("clear", "namepanel", $text);
    step("getagentaddpriceproduct", $from_id);
} elseif ($user['step'] == "getagentaddpriceproduct") {
    $grp = function_exists('rx_resolveAgentGroupFromReplyButton') ? rx_resolveAgentGroupFromReplyButton($text, ['f', 'n', 'n2']) : (in_array($text, ['f', 'n', 'n2'], true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $text = $grp;
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
} elseif ($text == "⬇️ کاهش گروهی قیمت") {
    nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید کاهش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
    step("getlowpricepeoductloc", $from_id);
} elseif ($user['step'] == "getlowpricepeoductloc") {
    nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود؟
یکی از گزینه‌های زیر را انتخاب یا ارسال کنید:
👤 کاربر عادی (f)
🤝 نماینده عادی (n)
💎 نماینده پیشرفته (n2)", rx_agentGroupKeyboard(false), 'HTML');
    savedata("clear", "namepanel", $text);
    step("getkampricepeoductloc", $from_id);
} elseif ($user['step'] == "getkampricepeoductloc") {
    $grp = function_exists('rx_resolveAgentGroupFromReplyButton') ? rx_resolveAgentGroupFromReplyButton($text, ['f', 'n', 'n2']) : (in_array($text, ['f', 'n', 'n2'], true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    savedata("save", "agent", $grp);
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
} elseif ($text == "⬇️ کف کارت به کارت") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaincart", $from_id);
} elseif ($user['step'] == "getmaincart") {
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancecart");
} elseif ($text == "⬆️ سقف کارت به کارت") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxcart", $from_id);
} elseif ($text == "🔑 حداقل مبلغ احراز کارت") {
    $currentMin = (string)($setting['card_verify_min_amount'] ?? '0');
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ فاکتور برای فعال‌شدن احراز کارت را وارد کنید (تومان).\n\nمقدار فعلی: <b>{$currentMin}</b> تومان\n\nعدد صفر = برای همه مبالغ اعمال می‌شود.", $backadmin, 'HTML');
    step("get_cvmin_cart", $from_id);
} elseif ($user['step'] == "get_cvmin_cart") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ احراز کارت تنظیم شد: <b>{$text}</b> تومان", $CartManage, 'HTML');
    step("home", $from_id);
    update("setting", "card_verify_min_amount", $text);
} elseif ($user['step'] == "getmaxcart") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancecart");
} elseif ($text == "⬇️ کف plisio") {
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
} elseif ($text == "⬆️ سقف plisio") {
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
} elseif ($text == "⬇️ کف رمزارز آفلاین") {
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
} elseif ($text == "⬆️ سقف رمزارز آفلاین") {
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
} elseif ($text == "⬇️ کف ترونادو") {
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
} elseif ($text == "⬆️ سقف ترونادو") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxiranpay2", $from_id);
} elseif ($user['step'] == "getmaaxiranpay2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $trnado, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay2");
} elseif ($text == "⬇️ کف تون‌پی") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaintonpay", $from_id);
} elseif ($user['step'] == "getmaintonpay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $tonpay, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancetonpay");
} elseif ($text == "⬆️ سقف تون‌پی") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxtonpay", $from_id);
} elseif ($user['step'] == "getmaxtonpay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $tonpay, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancetonpay");
} elseif ($text == "⬇️ کف بلوپال") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainblupal", $from_id);
} elseif ($user['step'] == "getmainblupal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $blupal, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceblupal");
} elseif ($text == "⬆️ سقف بلوپال") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxblupal", $from_id);
} elseif ($user['step'] == "getmaxblupal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $blupal, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceblupal");
} elseif ($text == "⬇️ کف واریزا") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainvariza", $from_id);
} elseif ($user['step'] == "getmainvariza") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $variza, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancevariza");
} elseif ($text == "⬆️ سقف واریزا") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxvariza", $from_id);
} elseif ($user['step'] == "getmaxvariza") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $variza, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancevariza");
} elseif ($text == "⬇️ کف اطلس‌پی") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainatlaspay", $from_id);
} elseif ($user['step'] == "getmainatlaspay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $atlaspay, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceatlaspay");
} elseif ($text == "⬆️ سقف اطلس‌پی") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxatlaspay", $from_id);
} elseif ($user['step'] == "getmaxatlaspay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $atlaspay, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceatlaspay");
} elseif ($text == "⬇️ کف تتراپی") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaintetrapay", $from_id);
} elseif ($user['step'] == "getmaintetrapay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $tetrapay, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancetetrapay");
} elseif ($text == "⬆️ سقف تتراپی") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxtetrapay", $from_id);
} elseif ($user['step'] == "getmaxtetrapay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $tetrapay, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancetetrapay");
} elseif ($text == "⬇️ کف کیوب‌پی") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaincubepay", $from_id);
} elseif ($user['step'] == "getmaincubepay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $cubepay, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancecubepay");
} elseif ($text == "⬆️ سقف کیوب‌پی") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxcubepay", $from_id);
} elseif ($user['step'] == "getmaxcubepay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $cubepay, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancecubepay");
} elseif ($text == "⬇️ کف زرین پال") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqzarinpal", $from_id);
} elseif ($user['step'] == "getmainaqzarinpal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $keyboardzarinpal, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancezarinpal");
} elseif ($text == "⬆️ سقف زرین پال") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxzarinpal", $from_id);
} elseif ($user['step'] == "getmaaxzarinpal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $keyboardzarinpal, 'HTML');
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
        $manualCurrencies = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
        $msg = "💼 <b>آدرس کیف پول هش‌چکر</b>\n\n"
             . "شبکه‌ای که می‌خواهید آدرسش را ثبت/ویرایش کنید را انتخاب کنید:\n\n"
             . "🟥 ترون (TRX): <code>" . $shorten($rowTrx) . "</code>\n"
             . "🟢 تتر روی ترون: <code>" . $shorten($rowUsdtT) . "</code>\n"
             . "🟦 تون (TON): <code>" . $shorten($rowTon) . "</code>\n"
             . "🟢 تتر روی تون: <code>" . $shorten($rowUsdtN) . "</code>\n";
        $manualRows = [];
        foreach ($manualCurrencies as $mCur => $mInfo) {
            $mRow = function_exists('crypto_active_wallet') ? crypto_active_wallet($mCur) : null;
            $msg .= "🛠 " . $mInfo['label'] . ": <code>" . $shorten($mRow) . "</code> <i>(دستی)</i>\n";
            $manualRows[] = [
                ['text' => '🛠 ' . $mInfo['label'], 'callback_data' => 'cryptowalletmanual_' . $mCur],
                ['text' => '🗑', 'callback_data' => 'cryptowallet_del_' . $mCur],
            ];
        }
        $msg .= "\nℹ️ بعد از ثبت آدرس، کاربر هنگام انتخاب «ارز آفلاین» یک هش پرداخت می‌فرستد. برای ۴ شبکه بالا ربات هر دقیقه به‌صورت خودکار از Tronscan / TonAPI تایید می‌کند؛ شبکه‌های «دستی» فقط توسط ادمین بررسی و تایید می‌شوند.";
        $networkPickerKb = json_encode([
            'inline_keyboard' => array_merge([
                [['text' => '🟥 ترون (TRX)',     'callback_data' => 'cryptowallet_TRX'],         ['text' => '🗑', 'callback_data' => 'cryptowallet_del_TRX']],
                [['text' => '🟢 تتر روی ترون',  'callback_data' => 'cryptowallet_USDT_TRC20'],  ['text' => '🗑', 'callback_data' => 'cryptowallet_del_USDT_TRC20']],
                [['text' => '🟦 تون (TON)',     'callback_data' => 'cryptowallet_TON'],         ['text' => '🗑', 'callback_data' => 'cryptowallet_del_TON']],
                [['text' => '🟢 تتر روی تون',   'callback_data' => 'cryptowallet_USDT_TON'],    ['text' => '🗑', 'callback_data' => 'cryptowallet_del_USDT_TON']],
            ], $manualRows, [
                [['text' => '➕ افزودن شبکه دستی', 'callback_data' => 'cryptowallet_new']],
                [['text' => $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل', 'callback_data' => 'wallet_backmenu']],
                [['text' => '❌ بستن',          'callback_data' => 'close_stat']],
            ]),
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
} elseif (preg_match('/^cryptowallet_del_([A-Za-z0-9_]{2,20})$/', (string) $datain, $cwd) && $adminrulecheck['rule'] == "administrator") {

    $cur = $cwd[1];
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    $manual = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
    $label = $supported[$cur]['label'] ?? $manual[$cur]['label'] ?? $cur;
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
            'cache_time' => 0,
        ]);
    }
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
    if ($cur === 'TRX' && function_exists('tronadoWalletsCollide')) {
        $tronadoWalletSetting = select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
        if (tronadoWalletsCollide($address, $tronadoWalletSetting['ValuePay'] ?? '')) {
            nm_adminInstantReply($from_id, "❌ این آدرس همان کیف پول ترونادو است و ثبت نشد.\n\nاگر هر دو درگاه به یک کیف پول واریز شوند، یک پرداخت ترونادو می‌تواند دوباره به‌عنوان رسید پرداخت آفلاین ثبت و دو بار تایید شود. لطفاً یک آدرس جداگانه ارسال کنید.", null, 'HTML');
            return;
        }
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
} elseif ($datain == "cryptowallet_new" && $adminrulecheck['rule'] == "administrator") {

    update("user", "Processing_value", "0", "id", $from_id);
    $askNetworkMsg = "🛠 <b>افزودن شبکه پرداخت دستی جدید (تتر)</b>\n\n"
                . "ارز این شبکه‌ها همیشه <b>تتر (USDT)</b> است — فقط نام شبکه را ارسال کنید (مثل <code>ERC20</code> یا <code>BEP20</code> یا <code>POLYGON</code>):";
    nm_adminInstantReply($from_id, $askNetworkMsg, $backadmin, 'HTML');
    step('cryptowallet_new_network', $from_id);
} elseif ($user['step'] == "cryptowallet_new_network" && empty($datain)) {

    $networkInput = strtoupper(trim((string) $text));
    if ($networkInput === '' || mb_strlen($networkInput) > 32) {
        step('home', $from_id);
        if ($networkInput !== '') {
            nm_adminInstantReply($from_id, "🏠 از حالت افزودن شبکه خارج شدید.", $keyboardadmin, 'HTML');
        }
        return;
    }
    if (!preg_match('/^[A-Z0-9_]{2,20}$/', $networkInput)) {
        nm_adminInstantReply($from_id, "❌ نام شبکه نامعتبر است. فقط حروف انگلیسی، اعداد و آندرلاین مجاز است (حداکثر ۲۰ کاراکتر).", null, 'HTML');
        return;
    }
    $codeInput = 'USDT_' . $networkInput;
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    $manual = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
    if (isset($supported[$codeInput]) || isset($manual[$codeInput])) {
        nm_adminInstantReply($from_id, "❌ شبکه <b>{$networkInput}</b> برای تتر قبلاً ثبت شده است.", null, 'HTML');
        return;
    }
    $label = 'تتر روی شبکه ' . $networkInput . ' (USDT-' . $networkInput . ')';
    update("user", "Processing_value", json_encode(['code' => $codeInput, 'network' => $networkInput, 'label' => $label], JSON_UNESCAPED_UNICODE), "id", $from_id);
    $memoAskMsg = "🏷 <b>آیا این کیف پول ممو/تگ (Memo / Tag) دارد؟</b>\n\n"
                . "<i>اگر آدرس از یک صرافی است، معمولاً ممو لازم دارد. اگر شخصی و مستقل است، معمولاً نیاز ندارد.</i>";
    $memoAskKb = json_encode([
        'inline_keyboard' => [
            [['text' => '✅ دارم — وارد می‌کنم', 'callback_data' => 'cryptowalletnewmemo_yes']],
            [['text' => '❌ ندارم',              'callback_data' => 'cryptowalletnewmemo_no']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, $memoAskMsg, $memoAskKb, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^cryptowalletnewmemo_(yes|no)$/', (string) $datain, $cwnm) && $adminrulecheck['rule'] == "administrator") {

    $pending = json_decode((string) ($user['Processing_value'] ?? ''), true);
    if (!is_array($pending) || empty($pending['code']) || empty($pending['network']) || empty($pending['label'])) {
        nm_adminInstantReply($from_id, "❌ خطای داخلی: اطلاعات ناقص است. مجدداً از منوی آدرس ولت اقدام کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($cwnm[1] === 'no') {
        $pending['memo'] = '';
        update("user", "Processing_value", json_encode($pending, JSON_UNESCAPED_UNICODE), "id", $from_id);
        $askAddrMsg = "💼 آدرس کیف پول را برای دریافت وجه ارسال کنید:";
        if (!empty($message_id)) {
            Editmessagetext($from_id, $message_id, $askAddrMsg, null);
        } else {
            sendmessage($from_id, $askAddrMsg, $backadmin, 'HTML');
        }
        step('cryptowallet_new_address', $from_id);
    } else {
        $askMemoMsg = "🏷 لطفاً <b>ممو/تگ (Memo / Tag)</b> کیف پول را ارسال کنید.";
        if (!empty($message_id)) {
            Editmessagetext($from_id, $message_id, $askMemoMsg, null);
        } else {
            sendmessage($from_id, $askMemoMsg, $backadmin, 'HTML');
        }
        step('cryptowallet_new_memo', $from_id);
    }
} elseif ($user['step'] == "cryptowallet_new_memo" && empty($datain)) {

    $memoInput = trim((string) $text);
    if ($memoInput === '' || mb_strlen($memoInput) > 200) {
        step('home', $from_id);
        if ($memoInput !== '') {
            nm_adminInstantReply($from_id, "🏠 از حالت افزودن شبکه خارج شدید.", $keyboardadmin, 'HTML');
        }
        return;
    }
    $pending = json_decode((string) ($user['Processing_value'] ?? ''), true);
    if (!is_array($pending) || empty($pending['code']) || empty($pending['network']) || empty($pending['label'])) {
        nm_adminInstantReply($from_id, "❌ خطای داخلی: اطلاعات ناقص است. مجدداً از منوی آدرس ولت اقدام کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $pending['memo'] = $memoInput;
    update("user", "Processing_value", json_encode($pending, JSON_UNESCAPED_UNICODE), "id", $from_id);
    nm_adminInstantReply($from_id, "💼 آدرس کیف پول را برای دریافت وجه ارسال کنید:", $backadmin, 'HTML');
    step('cryptowallet_new_address', $from_id);
} elseif ($user['step'] == "cryptowallet_new_address" && empty($datain)) {

    $addressInput = trim((string) $text);
    if ($addressInput === '' || mb_strlen($addressInput) < 10 || mb_strlen($addressInput) > 255) {
        step('home', $from_id);
        if ($addressInput !== '') {
            nm_adminInstantReply($from_id, "🏠 از حالت افزودن شبکه خارج شدید.", $keyboardadmin, 'HTML');
        }
        return;
    }
    $pending = json_decode((string) ($user['Processing_value'] ?? ''), true);
    if (!is_array($pending) || empty($pending['code']) || empty($pending['network']) || empty($pending['label'])) {
        nm_adminInstantReply($from_id, "❌ خطای داخلی: اطلاعات ناقص است. مجدداً از منوی آدرس ولت اقدام کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $ok = function_exists('crypto_create_manual_wallet')
        ? crypto_create_manual_wallet($pending['code'], $pending['network'], $pending['label'], $addressInput)
        : false;
    if ($ok && !empty($pending['memo']) && function_exists('crypto_save_wallet_memo')) {
        crypto_save_wallet_memo($pending['code'], $pending['memo']);
    }
    update("user", "Processing_value", "0", "id", $from_id);
    if ($ok) {
        $okMsg = "✅ شبکه دستی <b>" . htmlspecialchars($pending['label']) . "</b> با موفقیت ثبت شد.\n\n<code>" . htmlspecialchars($addressInput) . "</code>\n\n"
               . "ℹ️ تراکنش‌های این شبکه به‌صورت خودکار تایید نمی‌شوند و باید توسط ادمین بررسی شوند.";
    } else {
        $okMsg = "❌ ذخیره‌سازی شبکه دستی با خطا مواجه شد. لاگ سرور را بررسی کنید.";
    }
    nm_adminInstantReply($from_id, $okMsg, $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^cryptowalletmanual_([A-Za-z0-9_]{2,20})$/', (string) $datain, $cwmm) && $adminrulecheck['rule'] == "administrator") {

    $cur = $cwmm[1];
    $manual = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
    if (!isset($manual[$cur])) {
        nm_adminInstantReply($from_id, "❌ این شبکه دستی یافت نشد.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $label = $manual[$cur]['label'];
    $row = function_exists('crypto_active_wallet') ? crypto_active_wallet($cur) : null;
    $cur_now = $row['wallet_address'] ?? '';
    $msg = "💼 شبکه انتخاب‌شده: <b>{$label}</b> <i>(دستی)</i>\n\n"
         . "آدرس فعلی: <code>" . ($cur_now === '' ? '—' : htmlspecialchars($cur_now)) . "</code>\n\n"
         . "آدرس کیف پول جدید را ارسال کنید:";
    update("user", "Processing_value", json_encode(['code' => $cur, 'network' => $manual[$cur]['network'], 'label' => $label], JSON_UNESCAPED_UNICODE), "id", $from_id);
    nm_adminInstantReply($from_id, $msg, $backadmin, 'HTML');
    step('cryptowallet_new_address', $from_id);
} elseif ($user['step'] == "walletaddresssiranpay" && empty($datain)) {
    $walletInput = trim((string) $text);
    $walletProcessing = json_decode((string) ($user['Processing_value'] ?? ''), true);
    $walletReturnKeyboard = (is_array($walletProcessing) && ($walletProcessing['walletaddress_origin'] ?? '') === 'trnado')
        ? $trnado
        : $keyboardadmin;

    if ($walletInput === ''
        || mb_strlen($walletInput) < 30
        || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $walletInput)
        || strpos($walletInput, ' ') !== false
    ) {
        step('home', $from_id);
        if ($walletInput !== '') {
            nm_adminInstantReply($from_id, "🏠 از حالت ثبت آدرس ولت خارج شدید.", $walletReturnKeyboard, 'HTML');
        }
        return;
    }

    if (!function_exists('tronadoIsValidTronAddress') || !tronadoIsValidTronAddress($walletInput)) {
        nm_adminInstantReply($from_id, "❌ آدرس ولت وارد شده نامعتبر است. لطفاً آدرس TRC20 معتبر را دقیقاً با همان حروف کوچک و بزرگ ارسال کنید.", $backadmin, 'HTML');
        return;
    }

    // Stored exactly as entered: Base58 is case sensitive. This used to be strtoupper(), which
    // corrupted every address saved here.
    $standardizedWallet = $walletInput;

    // Refused, not just warned: sharing the offline TRX wallet would let one payment be credited twice.
    // USDT-TRC20 is not checked - that checker needs a USDT transfer, and Tronado pays plain TRX.
    $offlineTrx = function_exists('crypto_active_wallet') ? crypto_active_wallet('TRX') : null;
    if (function_exists('tronadoWalletsCollide') && is_array($offlineTrx)
        && tronadoWalletsCollide($standardizedWallet, $offlineTrx['wallet_address'] ?? '')) {
        nm_adminInstantReply($from_id, "❌ این آدرس همان کیف پول «🟥 ترون (TRX)» بخش ارز آفلاین است و ثبت نشد.\n\nاگر هر دو درگاه به یک کیف پول واریز شوند، یک پرداخت ترونادو می‌تواند دوباره به‌عنوان رسید پرداخت آفلاین ثبت و دو بار تایید شود. لطفاً برای ترونادو یک آدرس جداگانه ارسال کنید.", $backadmin, 'HTML');
        return;
    }

    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $walletReturnKeyboard, 'HTML');
    update("PaySetting", "ValuePay", $standardizedWallet, "NamePay", "walletaddress");
    update("user", "Processing_value", '{}', "id", $from_id);
    step('home', $from_id);
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
} elseif ($text == "⬇️ کف ریالی سوم") {
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
} elseif ($text == "⬆️ سقف ریالی سوم") {
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
} elseif ($text == "📍 کف حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 حداقل حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetmaineExtra', $from_id);
} elseif ($user['step'] == "GetmaineExtra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "mainvalume", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(false), 'HTML');
    step('gettypeextramain', $from_id);
} elseif ($user['step'] == "gettypeextramain") {
    $agentst = ["n", "n2", "f"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'] ?? '{}', true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('mainvalume', $userdata)) {
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
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['mainvolume'], true);
    $eextraprice[$text] = $userdata['mainvalume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "mainvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('PanelMenu', $from_id);
} elseif ($text == "📍 سقف حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 حداکثر حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetmaxeExtra', $from_id);
} elseif ($user['step'] == "GetmaxeExtra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "maxvolume", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(false), 'HTML');
    step('gettypeextramax', $from_id);
} elseif ($user['step'] == "gettypeextramax") {
    $agentst = ["n", "n2", "f"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'] ?? '{}', true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('maxvolume', $userdata)) {
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
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxvolume'], true);
    $eextraprice[$text] = $userdata['maxvolume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('PanelMenu', $from_id);
} elseif ($text == "📍 کف زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 حداقل زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('Getmaintime', $from_id);
} elseif ($user['step'] == "Getmaintime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "maintime", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(false), 'HTML');
    step('gettypeextramaintime', $from_id);
} elseif ($user['step'] == "gettypeextramaintime") {
    $agentst = ["n", "n2", "f"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'] ?? '{}', true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('maintime', $userdata)) {
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
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maintime'], true);
    $eextraprice[$text] = $userdata['maintime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maintime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('PanelMenu', $from_id);
} elseif ($text == "📍 سقف زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 حداکثر زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('Getmaxtime', $from_id);
} elseif ($user['step'] == "Getmaxtime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "maxtime", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(false), 'HTML');
    step('gettypeextramaxtime', $from_id);
} elseif ($user['step'] == "gettypeextramaxtime") {
    $agentst = ["n", "n2", "f"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'] ?? '{}', true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('maxtime', $userdata)) {
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
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxtime'], true);
    $eextraprice[$text] = $userdata['maxtime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxtime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('PanelMenu', $from_id);
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
    nm_adminInstantReply($from_id, "✅ اطلاعات با موفقیت تنظیم گردید", $optionMarzban, 'HTML');
} elseif ($text == "👤 تنظیم آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    $textcart = "📌 نام کاربری خود را بدون @ برای پشتیبانی  ارسال کنید\n\n{$setting['id_support']}";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('idsupportset', $from_id);
} elseif ($user['step'] == "idsupportset") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $supportcenter, 'HTML');
    update("setting", "id_support", $text, null, null);
    step('home', $from_id);
} elseif ($text == "📚 آموزش کارت به کارت" && $adminrulecheck['rule'] == "administrator") {
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
} elseif ($text == "📚 آموزش nowpayment" && $adminrulecheck['rule'] == "administrator") {
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
} elseif ($text == "📚 آموزش پرفکت مانی" && $adminrulecheck['rule'] == "administrator") {
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
} elseif ($text == "📚 آموزش plisio" && $adminrulecheck['rule'] == "administrator") {
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
} elseif ($text == "📚 آموزش ترونادو" && $adminrulecheck['rule'] == "administrator") {
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
} elseif ($text == "📚 آموزش تون‌پی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helptonpay", $from_id);
} elseif ($user['step'] == "helptonpay") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helptonpay");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helptonpay");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helptonpay");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helptonpay");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $tonpay, 'HTML');
} elseif ($text == "📚 آموزش بلوپال" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpblupal", $from_id);
} elseif ($user['step'] == "helpblupal") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpblupal");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpblupal");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpblupal");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpblupal");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $blupal, 'HTML');
} elseif ($text == "📚 آموزش واریزا" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpvariza", $from_id);
} elseif ($user['step'] == "helpvariza") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpvariza");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpvariza");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpvariza");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpvariza");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $variza, 'HTML');
} elseif ($text == "📚 آموزش اطلس‌پی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpatlaspay", $from_id);
} elseif ($user['step'] == "helpatlaspay") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpatlaspay");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpatlaspay");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpatlaspay");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpatlaspay");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $atlaspay, 'HTML');
} elseif ($text == "📚 آموزش تتراپی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helptetrapay", $from_id);
} elseif ($user['step'] == "helptetrapay") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helptetrapay");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helptetrapay");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helptetrapay");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helptetrapay");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $tetrapay, 'HTML');
} elseif ($text == "📚 آموزش کیوب‌پی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpcubepay", $from_id);
} elseif ($user['step'] == "helpcubepay") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpcubepay");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpcubepay");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcubepay");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcubepay");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $cubepay, 'HTML');
} elseif ($text == "📚 آموزش زرین پال" && $adminrulecheck['rule'] == "administrator") {
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
} elseif ($text == "📚 آموزش  ارزی افلاین" && $adminrulecheck['rule'] == "administrator") {
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
} elseif ($text == "💰 عضویت نمایندگی") {
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
} elseif ($text == "🤖 تایید رسید بدون بررسی" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
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
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
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
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
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
    if (!isset($update['message']) && empty($text)) { return; }
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
} elseif ($text == "📷 تنظیمات کیو آر کد") {
    $__qrDisabledNow = function_exists('isQrDisabled') && isQrDisabled();
    $__qrToggleLabel = $__qrDisabledNow ? "✅ فعال کردن کیو آر کد در سراسر سیستم" : "🚫 غیرفعال کردن کیو آر کد در سراسر سیستم";
    $__qrStatusText  = $__qrDisabledNow ? "❌ کیو آر کد در حال حاضر <b>غیرفعال</b> است." : "✅ کیو آر کد در حال حاضر <b>فعال</b> است.";
    $__qrSettingsKb = json_encode([
        'inline_keyboard' => [
            [['text' => $__qrToggleLabel, 'callback_data' => 'set_qr_toggle']],
            [['text' => "🖼 تغییر پس‌زمینه کیو آر کد", 'callback_data' => 'set_qrbg']],
            [['text' => "🔙 بازگشت", 'callback_data' => 'admin_settings']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "📷 <b>تنظیمات کیو آر کد</b>\n\n{$__qrStatusText}", $__qrSettingsKb, 'HTML');
} elseif ($text == "🔄 وضعیت کیوآرکد") {
    $__qrDisabledNow = function_exists('isQrDisabled') && isQrDisabled();
    $__newVal = $__qrDisabledNow ? '0' : '1';
    $__existingQrRow = select("shopSetting", "*", "Namevalue", "qr_disabled", "select");
    if (is_array($__existingQrRow)) {
        update("shopSetting", "value", $__newVal, "Namevalue", "qr_disabled");
    } else {
        $__qrPdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
        if ($__qrPdo instanceof PDO) {
            $__qrPdo->prepare("INSERT INTO shopSetting (Namevalue, value) VALUES ('qr_disabled', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute([$__newVal]);
        }
    }
    $__qrNowDisabled = ($__newVal === '1');
    $__qrToggleLabel2 = $__qrNowDisabled ? "✅ فعال کردن کیو آر کد در سراسر سیستم" : "🚫 غیرفعال کردن کیو آر کد در سراسر سیستم";
    $__qrStatusText2  = $__qrNowDisabled ? "❌ کیو آر کد در حال حاضر <b>غیرفعال</b> است." : "✅ کیو آر کد در حال حاضر <b>فعال</b> است.";
    $__qrSettingsKb2 = json_encode([
        'inline_keyboard' => [
            [['text' => $__qrToggleLabel2, 'callback_data' => 'set_qr_toggle']],
            [['text' => "🖼 تغییر پس‌زمینه کیو آر کد", 'callback_data' => 'set_qrbg']],
            [['text' => "🔙 بازگشت", 'callback_data' => 'admin_settings']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "📷 <b>تنظیمات کیو آر کد</b>\n\n{$__qrStatusText2}\n\n" . ($__qrNowDisabled ? "🚫 کیو آر کد با موفقیت <b>غیرفعال</b> شد." : "✅ کیو آر کد با موفقیت <b>فعال</b> شد."), $__qrSettingsKb2, 'HTML');
} elseif ($text == "🖼 پس‌زمینه کیوآرکد") {
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
} elseif ($text == "⚙️ پروتکل اینباند" || $text == "🎛 تنظیم نام گروه" || $text == "⚙️ تنظیم نود") {
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
    if (!isset($update['message']) && empty($text)) { return; }
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
    } elseif ($panel['type'] == "pasarguard") {
        $DataUserOut = pasarguardGetUser($text, $user['Processing_value']);
        if (!empty($DataUserOut['error'])) {
            nm_adminInstantReply($from_id, $DataUserOut['error'], null, 'HTML');
            return;
        }
        if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
            nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
            return;
        }
        $DataUserOut = json_decode($DataUserOut['body'], true);
        if ((isset($DataUserOut['detail']) && !empty($DataUserOut['detail'])) or !isset($DataUserOut['proxy_settings'])) {
            nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
            return;
        }
        foreach ($DataUserOut['proxy_settings'] as $key => &$value) {
            if ($key == "shadowsocks") {
                unset($DataUserOut['proxy_settings'][$key]['password']);
            } elseif ($key == "trojan") {
                unset($DataUserOut['proxy_settings'][$key]['password']);
            } elseif ($key == "wireguard") {
                unset($DataUserOut['proxy_settings'][$key]['private_key']);
                unset($DataUserOut['proxy_settings'][$key]['public_key']);
                unset($DataUserOut['proxy_settings'][$key]['peer_ips']);
            } elseif ($key == "hysteria") {
                unset($DataUserOut['proxy_settings'][$key]['auth']);
            } else {
                unset($DataUserOut['proxy_settings'][$key]['id']);
            }
            if (count($DataUserOut['proxy_settings'][$key]) == 0) {
                $DataUserOut['proxy_settings'][$key] = new stdClass();
            }
        }
        update("marzban_panel", "inbounds", json_encode($DataUserOut['group_ids'] ?? []), "name_panel", $user['Processing_value']);
        update("marzban_panel", "proxies", json_encode($DataUserOut['proxy_settings'], true), "name_panel", $user['Processing_value']);
    }
    $rxInboundSetupKeyboard = $panel['type'] == "pasarguard" ? $optionPasarGuard : $optionMarzban;
    nm_adminInstantReply($from_id, "✅ اینباند و پروتکل های شما با موفقیت تنظیم گردیدند.", $rxInboundSetupKeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "🔋 وضعیت تمدید" && $adminrulecheck['rule'] == "administrator") {
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
            [
                ['text' => "🔙 بازگشت به منوی پنل", 'callback_data' => 'panelmenu_back'],
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
            [
                ['text' => "🔙 بازگشت به منوی پنل", 'callback_data' => 'panelmenu_back'],
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
            [
                ['text' => "🔙 بازگشت به منوی پنل", 'callback_data' => 'panelmenu_back'],
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
    nm_adminInstantReply($from_id, "موجودی کاربر به مبلغ " . rxFormatToman($userdata['Balance']) . " صفر گردید", $keyboardadmin, 'HTML');
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
    $removeAdminBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ بازگشت به لیست ادمین‌ها", 'callback_data' => "admin_mgr"]]]]);
    nm_adminInstantReply($from_id, "✅ ادمین با موفقیت حذف گردید", $removeAdminBackKb, 'HTML');
}

elseif ($text == "🫣 مخفی پنل برای کاربر" && $adminrulecheck['rule'] == "administrator") {
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
} elseif ($text == "❌ حذف از لیست مخفی" && $adminrulecheck['rule'] == "administrator") {
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
    step('featnav_lottery', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $lottery, 'HTML');
} elseif ($text == "1️⃣ تنظیم جایزه نفر اول") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary", $from_id);
} elseif ($user['step'] == "getonelotary") {
    if (!isset($update['message']) && empty($text)) { return; }
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
    if (!isset($update['message']) && empty($text)) { return; }
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
    if (!isset($update['message']) && empty($text)) { return; }
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
    step('featnav_wheel', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $wheelkeyboard, 'HTML');
} elseif ($text == "🎲 مبلغ برنده شدن کاربر") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricewheel", $from_id);
} elseif ($user['step'] == "getpricewheel") {
    if (!isset($update['message']) && empty($text)) { return; }
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
💰 مبلغ پرداختی : " . rxFormatToman($paymentUser['price']) . " تومان
⚜️ وضعیت پرداخت : {$paymentUser['payment_Status']}
⭕️ روش پرداخت : {$paymentUser['Payment_Method']}
📆 تاریخ خرید :  {$paymentUser['time']}";
    nm_adminInstantReply($from_id, $text_order, null, 'HTML');
} elseif ($text == "🎛 تنظیم اینباند") {
    nm_adminInstantReply($from_id, "📌 در صورتی که پنل مرزبان  یا مرزنشین هستید یک نام کاربری کانفیگ از پنل کپی و ارسال نمایید در غیراینصورت برای پنل های ثنایی و علیرضا شناسه اینباند را ارسال نمایید", $backadmin, 'HTML');
    step("getdatainboundproduct", $from_id);
} elseif ($user['step'] == "getdatainboundproduct") {
    if (!isset($update['message']) && empty($text)) { return; }
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
    } elseif ($marzban_list_get['type'] == "x-ui_single") {
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
    [$ip_list, $iplogin_unlimited] = getIpLoginState();

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

    $ip_keyboard_json = buildIpLoginKeyboard($ip_list, $iplogin_unlimited);

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
    $ip_keyboard_json = buildIpLoginKeyboard($ip_list, $iplogin_unlimited);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
    } else {
        nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
    }
} elseif ($datain == "addiplogin") {
    nm_adminInstantReply($from_id, "📌 آیپی جدید خود را ارسال کنید.\n<i>مثال: 1.2.3.4</i>", null, 'HTML');
    step("getiplogin", $from_id);

} elseif ($user['step'] == "getiplogin") {
    if (!isset($update['message']) && empty($text)) { return; }
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

    $ip_keyboard_json = buildIpLoginKeyboard($ip_list, false);

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
        $extend['msg'] = json_encode($extend['msg']);
        $textreports = "
        خطای تمدید سرویس
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extend['msg']}</blockquote>";
        $rxAdminExtendMsg = "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید";
        if (($extend['code'] ?? '') === 'manual_stock_empty') {
            $rxAdminExtendMsg = "❌ موجودی انبار فروش دستی برای این محصول تمام شده است.";
        } elseif (($extend['code'] ?? '') === 'queued_renewal_exists') {
            $rxAdminExtendMsg = "❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است.";
        }
        nm_adminInstantReply($from_id, $rxAdminExtendMsg, null, 'HTML');
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
    $rxAdminExtendThanks = $textbotlang['users']['extend']['thanks'];
    if (!empty($extend['queued'])) {
        $rxAdminExtendThanks .= "\n\n⏳ این بسته هم‌اکنون فعال نشد. به محض اتمام حجم یا زمان سرویس فعلی، این بسته به‌صورت خودکار فعال خواهد شد.";
    }
    nm_adminInstantReply($from_id, $rxAdminExtendThanks, null, 'HTML');
    $text_report = "⭕️ ادمین سرویس کاربر را تمدید کرد.

اطلاعات کاربر :

<blockquote>🪪 آیدی عددی ادمین : <code>$from_id</code></blockquote>
<blockquote>🪪 آیدی عددی : <code>{$nameloc['id_user']}</code></blockquote>
<blockquote>🛍 نام محصول :  {$prodcut['name_product']}</blockquote>
<blockquote>👤 نام کاربری مشتری در پنل  : {$nameloc['username']}</blockquote>
<blockquote>موقعیت سرویس سرویس کاربر : {$nameloc['Service_location']}</blockquote>";
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
