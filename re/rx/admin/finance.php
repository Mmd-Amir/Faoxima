<?php

if (function_exists('nmResolvePanelNameForUser')) {
    $rxResolvedPanelName = nmResolvePanelNameForUser($user);
    if ($rxResolvedPanelName !== '') {
        $rawProcessing = (string)($user['Processing_value'] ?? '');
        if ($rawProcessing === '' || $rawProcessing[0] === '{' || $rawProcessing[0] === '[') {
            $user['Processing_value'] = $rxResolvedPanelName;
        }
    }
    unset($rxResolvedPanelName, $rawProcessing);
}
if (false) {
} elseif ($text == "✍️ نام پنل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['GetNameNew'], $backadmin, 'HTML');
    step('GetNameNew', $from_id);
} elseif ($user['step'] == "GetNameNew") {
    if (in_array($text, $marzban_list)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedNmaePanel']);
    update("user", "Processing_value", $text, "id", $from_id);
    update("marzban_panel", "name_panel", $text, "name_panel", $user['Processing_value']);
    update("invoice", "Service_location", $text, "Service_location", $user['Processing_value']);
    update("product", "Location", $text, "Location", $user['Processing_value']);
    update("user", "Processing_value", $text, "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔗 ویرایش آدرس پنل" && $adminrulecheck['rule'] == "administrator") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($typepanel && $typepanel['type'] == "guard") {
        outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['guardfixedurl']);
        step('home', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['geturlnew'], $backadmin, 'HTML');
    step('GeturlNew', $from_id);
} elseif ($user['step'] == "GeturlNew") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "url_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "📍 تغییر گروه کاربری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 نوع کاربری را ارسال کنید
گروه های کاربری : f,n,n2
❌ در صورتی که می خواهید پنل برای تمام گروه کاربری ها نمایش داده شود متن all را ارسال کنید", $backadmin, 'HTML');
    step('getagentpanel', $from_id);
} elseif ($user['step'] == "getagentpanel") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "📌گروه کاربری با موفقیت تغییر کرد");
    update("marzban_panel", "agent", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔗 دامنه لینک ساب" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 اگر پنل ثنایی هستید یک لینک ساب کاربر را از پنل کپی کرده سپس در این بخش ارسال کنید .بقیه پنل ها باید طبق ساختارش ارسال نمایید.", $backadmin, 'HTML');
    step('GeturlNewx', $from_id);
} elseif ($user['step'] == "GeturlNewx") {
    $inputLink = trim($text);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($typepanel['type'] !== "x-ui_single" && !filter_var($inputLink, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    if ($typepanel['type'] === "x-ui_single") {
        $text = normalizeXuiSingleSubscriptionBaseUrl($inputLink);
    } else {
        $text = $inputLink;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "linksubx", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔗 uuid admin" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 uuid ادمین را ارسال کنید", $backadmin, 'HTML');
    step('getuuidadmin', $from_id);
} elseif ($user['step'] == "getuuidadmin") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "✅ uuid ادمین ذخیره گردید");
    update("marzban_panel", "secret_code", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🚨 محدودیت ساخت اکانت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['setlimit'], $backadmin, 'HTML');
    step('getlimitnew', $from_id);
} elseif ($user['step'] == "getlimitnew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['changedlimit']);
    update("marzban_panel", "limit_panel", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "⏳ زمان سرویس تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🕰 مدت زمان سرویس تست را ارسال کنید.
⚠️ زمان بر حسب ساعت است.", $backadmin, 'HTML');
    step('updatetime', $from_id);
} elseif ($user['step'] == "updatetime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "time_usertest", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "💾 حجم اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "حجم سرویس تست را ارسال کنید.
⚠️ حجم بر حسب مگابایت است.", $backadmin, 'HTML');
    step('val_usertest', $from_id);
} elseif ($user['step'] == "val_usertest") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "val_usertest", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "💎 تنظیم شناسه اینباند" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 شناسه اینباندی که می خواهید کانفیگ ازآن ساخته شود راارسال نمایید.  شناسه اینباند یک عدد چند رقمی است که در پنل  در صفحه اینباند ها ستون id  نوشته شده است

⚠️ در صورتی که پنل wgdashboard هستید باید نام کانفیگ را ارسال نمایید", $backadmin, 'HTML');
    step('getinboundiid', $from_id);
} elseif ($user['step'] == "getinboundiid") {
    nm_adminInstantReply($from_id, "✅ شناسه اینباند با موفقیت ذخیره گردید", $optionX_ui_single, 'HTML');
    update("marzban_panel", "inboundid", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔐 ویرایش کلید" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['guard']['request_api_key'], $backadmin, 'HTML');
    step('guard_edit_api_key', $from_id);
} elseif ($user['step'] == "guard_edit_api_key") {
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $baseUrl = guardGetBaseUrl($typepanel['url_panel'] ?? null);
    $currentKey = trim((string) (!empty($typepanel['api_key']) ? $typepanel['api_key'] : ($typepanel['password_panel'] ?? '')));
    if ($currentKey !== '' && hash_equals($currentKey, $apiKey)) {
        $sameKeyMessage = "ℹ️ کلید جدید با کلید قبلی یکسان است. تغییری انجام نشد.";
        $testResult = guardTestConnection($baseUrl, $apiKey);
        $statusText = guardFormatConnectionResult($testResult);
        if ($statusText !== '') {
            $sameKeyMessage .= "\n{$statusText}";
        }
        outtypepanel("guard", $sameKeyMessage);
        step('home', $from_id);
        return;
    }
    update("marzban_panel", "api_key", $apiKey, "name_panel", $user['Processing_value']);
    update("marzban_panel", "password_panel", $apiKey, "name_panel", $user['Processing_value']);
    update("marzban_panel", "url_panel", $baseUrl, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    $connectionResult = guardTestConnection($baseUrl, $apiKey);
    $statusText = guardFormatConnectionResult($connectionResult);
    $savedMessage = $textbotlang['Admin']['managepanel']['guard']['api_key_saved'] ?? "🔑 کلید Guard ذخیره شد.";
    outtypepanel("guard", "{$savedMessage}\n{$statusText}");
    step('home', $from_id);
} elseif ($text == "⁉️ وضعیت اتصال به پنل" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['errorstateuspanel'], $backadmin, 'HTML');
        return;
    }
    $apiKey = !empty($typepanel['api_key']) ? $typepanel['api_key'] : ($typepanel['password_panel'] ?? '');
    if (trim($apiKey) === '') {
        outtypepanel("guard", $textbotlang['Admin']['managepanel']['guard']['connection_missing_key']);
        step('home', $from_id);
        return;
    }
    $testResult = guardTestConnection($typepanel['url_panel'] ?? null, $apiKey);
    $statusText = guardFormatConnectionResult($testResult);
    outtypepanel("guard", $statusText);
    step('home', $from_id);
} elseif ($text == "⚙️ تنظیم سرویس ها" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $servicesResponse = guardGetServices($panel['name_panel']);
    if ($servicesResponse['status'] === false) {
        $errorMsg = $servicesResponse['msg'] ?? 'خطای ناشناخته';
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, $errorMsg);
        outtypepanel("guard", $failText);
        return;
    }
    $services = $servicesResponse['services'];
    if (empty($services)) {
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, 'لیست سرویس Guard خالی است.');
        outtypepanel("guard", $failText);
        return;
    }
    $availableIds = guardExtractServiceIdsFromList($services);
    if (empty($availableIds)) {
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, 'شناسه معتبر برای سرویس‌ها یافت نشد.');
        outtypepanel("guard", $failText);
        return;
    }
    $currentServices = guardParseServiceIds($panel['guard_service_ids'] ?? null);
    $currentSelection = guardNormalizeSelectedServiceIds($currentServices, $availableIds, true);
    $selectAll = in_array('all', $currentServices, true) || in_array(0, $currentServices, true) || count($currentSelection) === count($availableIds);
    if ($selectAll) {
        $currentSelection = $availableIds;
    }
    $manualSelection = guardNormalizeSelectedServiceIds($currentServices, $availableIds, false);
    $selectionState = [
        'mode' => 'edit',
        'panel' => $panel['name_panel'],
        'services' => $services,
        'selected_ids' => $currentSelection,
        'select_all' => $selectAll,
        'manual_selected_ids' => $manualSelection,
    ];
    savedata("save", "guard_service_selection", $selectionState);
    $message = guardBuildServiceSelectionMessage($services, $currentSelection, $selectAll);
    $keyboard = guardBuildServiceSelectionKeyboard($services, $currentSelection, 'edit', $selectAll);
    $messageResponse = sendmessage($from_id, $message, $keyboard, 'HTML');
    if (isset($messageResponse['result']['message_id'])) {
        $selectionState['message_id'] = $messageResponse['result']['message_id'];
        savedata("save", "guard_service_selection", $selectionState);
    }
    step('guard_service_selection_edit', $from_id);
} elseif ($text == "🎛️ تنظیمات سرویس" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['saveddata'], $backadmin, 'HTML');
        return;
    }
    $state = guardBuildGuardSettingsState($panel);
    $messageId = guardRenderGuardSettingsSummary($from_id, $state, false);
    $state['message_id'] = $messageId;
    guardPersistGuardSettingsState($from_id, $state);
    step('guard_settings_summary', $from_id);
} elseif ($user['step'] == "guard_settings_note") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $currentUser = select("user", "*", "id", $from_id, "select");
    $state = guardLoadGuardSettingsState(is_array($currentUser) ? $currentUser : $user, $panel);
    $noteText = trim($text);
    $state['note'] = ($noteText === '-') ? '' : $noteText;
    $state['pending_changes'] = true;
    guardPersistGuardSettingsState($from_id, $state);
    $state['message_id'] = guardRenderGuardSettingsSummary($from_id, $state, false);
    guardPersistGuardSettingsState($from_id, $state);
    step('guard_settings_summary', $from_id);
} elseif ($user['step'] == "guard_settings_auto_delete") {
    if (!preg_match('/^\d+$/', $text)) {
        nm_adminInstantReply($from_id, "❌ مقدار باید فقط عدد باشد.", $backadmin, 'HTML');
        return;
    }
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $currentUser = select("user", "*", "id", $from_id, "select");
    $state = guardLoadGuardSettingsState(is_array($currentUser) ? $currentUser : $user, $panel);
    $state['auto_delete_days'] = intval($text);
    $state['pending_changes'] = true;
    guardPersistGuardSettingsState($from_id, $state);
    $state['message_id'] = guardRenderGuardSettingsSummary($from_id, $state, false);
    guardPersistGuardSettingsState($from_id, $state);
    step('guard_settings_summary', $from_id);
} elseif ($user['step'] == "guard_settings_auto_renew") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $currentUser = select("user", "*", "id", $from_id, "select");
    $state = guardLoadGuardSettingsState(is_array($currentUser) ? $currentUser : $user, $panel);
    $parsedRenewal = guardParseAutoRenewalsInput($text);
    if ($parsedRenewal['status'] === false) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['guard']['settings_invalid_renewal'], $backadmin, 'HTML');
        return;
    }
    $state['auto_renewals'] = $parsedRenewal['entries'];
    $state['pending_changes'] = true;
    guardPersistGuardSettingsState($from_id, $state);
    $state['message_id'] = guardRenderGuardSettingsSummary($from_id, $state, false);
    guardPersistGuardSettingsState($from_id, $state);
    step('guard_settings_summary', $from_id);
} elseif ($text == "👤 ویرایش نام کاربری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getusernamenew'], $backadmin, 'HTML');
    step('GetusernameNew', $from_id);
} elseif ($user['step'] == "GetusernameNew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedusernamePanel']);
    update("marzban_panel", "username_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "⚙️ تنظیم پروتکل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocolx_ui', $from_id);
} elseif ($user['step'] == "getprotocolx_ui") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['setprotocol']);
    $marzbanprotocol = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    update("x_ui", "protocol", $text, "codepanel", $marzbanprotocol['code_panel']);
    step('home', $from_id);
} elseif ($text == "🔐 ویرایش رمز عبور" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpasswordnew'], $backadmin, 'HTML');
    step('GetpaawordNew', $from_id);
} elseif ($user['step'] == "GetpaawordNew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedpasswordPanel']);
    update("marzban_panel", "password_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "❌ حذف پنل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "در صورت تایید کلمه زیر را ارسال کنید.
<code>تایید</code>", $backadmin, 'HTML');
    step('confirmremovepanel', $from_id);
} elseif ($user['step'] == "confirmremovepanel") {
    if ($text == "تایید") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['RemovedPanel'], $keyboardadmin, 'HTML');
        $marzban = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        $stmt = $pdo->prepare("DELETE FROM marzban_panel WHERE name_panel = :name_panel");
        $stmt->bindParam(':name_panel', $user['Processing_value'], PDO::PARAM_STR);
        $stmt->execute();
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managruser'] || $datain == "backlistuser") {
    $keyboardtypelistuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "لیست کاربرانی که موجودی دارند.", 'callback_data' => "balanceuserlist"],
            ],
            [
                ['text' => "لیست کاربرانی که زیرمجموعه دارند.", 'callback_data' => "listrefral"],
            ],
            [
                ['text' => "لیست کاربران شماره کارت فعال.", 'callback_data' => "cartuserlist"],
            ],
            [
                ['text' => "لیست کاربرانی که موجودی منفی دارند", 'callback_data' => "zerobalance"],
            ],
            [
                ['text' => "لیست نمایندگان", 'callback_data' => "agentlistusers"],
                ['text' => "لیست کل کاربران", 'callback_data' => "alllistusers"],
            ],
            [
                ['text' => "🛍 جستجو سفارش", 'callback_data' => "searchorder"],
                ['text' => "👥 شارژ همگانی", 'callback_data' => "balanceaddall"],
            ],
            [
                ['text' => "🔍 جستجو کاربر", 'callback_data' => "searchuser"],
                ['text' => "📨 بخش ارسال پیام", 'callback_data' => "systemsms"],
            ],
            [
                ['text' => "🔋 حجم یا زمان همگانی", 'callback_data' => "voloume_or_day_all"],
            ]
        ]
    ]);
    $text_list_users = "📌 از لیست زیر یک گزینه را انتخاب نمایید";
    if ($datain == "backlistuser") {
        Editmessagetext($from_id, $message_id, $text_list_users, $keyboardtypelistuser);
    } else {
        nm_adminInstantReply($from_id, $text_list_users, $keyboardtypelistuser, 'html');
    }
} elseif ($datain == "alllistusers") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
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
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
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
    $keyboardlists['inline_keyboard'][] = [
        [
            'text' => '❌ بستن',
            'callback_data' => 'close_listusers'
        ]
    ];
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuser') {
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
    $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
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
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = [
        [
            'text' => '❌ بستن',
            'callback_data' => 'close_listusers'
        ]
    ];
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuser') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
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
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = [
        [
            'text' => '❌ بستن',
            'callback_data' => 'close_listusers'
        ]
    ];
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'close_listusers') {
    deletemessage($from_id, $message_id);
} elseif ($datain == "agentlistusers") {
    $keyboardtypelistuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "n", 'callback_data' => "agenttypshowlist_n"],
                ['text' => "n2", 'callback_data' => "agenttypshowlist_n2"],
            ],
            [
                ['text' => "تمام نمایندگان", 'callback_data' => "agenttypshowlist_all"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 کدام گروه از نمایندگان می خواهید مشاهده کنید ؟", $keyboardtypelistuser);
} elseif (preg_match('/agenttypshowlist_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE agent != 'f'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    } else {
        $_s=(int)$start_index; $_p=(int)$items_per_page;
        $_stmt = $connect->prepare("SELECT * FROM user WHERE agent = ? LIMIT ?, ?");
        $_stmt->bind_param("sii", $typeagent, $_s, $_p);
        $_stmt->execute();
        $result = $_stmt->get_result();
        $_stmt->close();
    }
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
            'callback_data' => "next_pageuseragent_$typeagent"
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
} elseif (preg_match('/next_pageuseragent_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
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
    if ($typeagent == "all") {
        $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE agent != 'f'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    } else {
        $_s=(int)$start_index; $_p=(int)$items_per_page;
        $_stmt = $connect->prepare("SELECT * FROM user WHERE agent = ? LIMIT ?, ?");
        $_stmt->bind_param("sii", $typeagent, $_s, $_p);
        $_stmt->execute();
        $result = $_stmt->get_result();
        $_stmt->close();
    }
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
            'callback_data' => "next_pageuseragent_$typeagent"
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => "previous_pageuseragent_$typeagent"
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/previous_pageuseragent_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE agent != 'f'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    } else {
        $_s=(int)$start_index; $_p=(int)$items_per_page;
        $_stmt = $connect->prepare("SELECT * FROM user WHERE agent = ? LIMIT ?, ?");
        $_stmt->bind_param("sii", $typeagent, $_s, $_p);
        $_stmt->execute();
        $result = $_stmt->get_result();
        $_stmt->close();
    }
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
            'callback_data' => "next_pageuseragent_$typeagent"
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => "previous_pageuseragent_$typeagent"
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "balanceuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
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
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
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
} elseif ($datain == 'next_pageuserbalance') {
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
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
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
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserbalance') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
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
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "listrefral") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
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
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
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
} elseif ($datain == 'next_pageuserrefral') {
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
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
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
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserrefral') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
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
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/addbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['ManageUser']['addbalanceuserdec'],
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('addbalanceusercurrent', $from_id);
} elseif ($user['step'] == "addbalanceusercurrent") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        nm_adminInstantReply($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "add balance by admin";
    $invoice = null;
    $stmt->bind_param("sssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['addbalanced'], $keyboardadmin, 'html');


    $stmtAtomic = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $stmtAtomic->bindValue(':delta', (int) $text, PDO::PARAM_INT);
    $stmtAtomic->bindValue(':uid', $user['Processing_value'], PDO::PARAM_STR);
    $stmtAtomic->execute();
    $heibalanceuser = number_format($text, 0);
    $textadd = "💎 کاربر عزیز مبلغ $heibalanceuser تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($user['Processing_value'], $textadd, null, 'HTML');
    step('home', $from_id);
    $Balance_user_after = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    $pricadd = number_format($text);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را افزایش داده است :

🪪 اطلاعات ادمین افزایش دهنده موجودی :
نام کاربری :@$username
آیدی عددی : $from_id
👤 اطلاعات کاربر دریافت کننده موجودی :
آیدی عددی کاربر  : {$user['Processing_value']}
مبلغ موجودی : $pricadd
موجودی کاربر پس از افزایش : $Balance_user_after";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/lowbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['ManageUser']['lowbalanceuserdec'],
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('addbalanceuser', $from_id);
} elseif ($user['step'] == "addbalanceuser") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        nm_adminInstantReply($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "low balance by admin";
    $invoice = null;
    $stmt->bind_param("sssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['lowbalanced'], $keyboardadmin, 'html');


    $stmtAtomic = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid");
    $stmtAtomic->bindValue(':delta', (int) $text, PDO::PARAM_INT);
    $stmtAtomic->bindValue(':uid', $user['Processing_value'], PDO::PARAM_STR);
    $stmtAtomic->execute();
    $lowbalanceuser = number_format($text, 0);
    $textkam = "❌ کاربر عزیز مبلغ $lowbalanceuser تومان از  موجودی کیف پول تان کسر گردید.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را کم کرده است :

🪪 اطلاعات ادمین کم کننده موجودی :
نام کاربری :@$username
آیدی عددی : $from_id
👤 اطلاعات کاربر  :
آیدی عددی کاربر  : {$user['Processing_value']}
مبلغ موجودی : $text
موجودی کاربر پس از کم کردن : $Balance_user_afters";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ((preg_match('/banuserlist_(\w+)/', $datain, $dataget) || preg_match('/blockuserfake_(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "block") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockedUser'], null, 'HTML');
        return;
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید", 'callback_data' => 'acceptblock_' . $iduser],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "در صورت تایید روی دکمه تایید کلیک کنید", $Response, 'HTML');
} elseif ($user['step'] == "adddecriptionblock") {
    update("user", "description_blocking", $text, "id", $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['DescriptionBlock'], $keyboardadmin, 'HTML');
    step('home', $from_id);

} elseif ((preg_match('/acceptblock_(\w+)/', $datain, $dataget) || preg_match('/blockuserfake_(\w+)/', $datain, $dataget))) {

    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    update("user", "User_Status", "block", "id", $iduser);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockUser'], $backadmin, 'HTML');
    step('adddecriptionblock', $from_id);
    $textblok = "کاربر با آیدی عددی
$iduser  در ربات مسدود گردید
ادمین مسدود کننده : $from_id";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
            ],
        ]
    ]);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textblok,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif (preg_match('/verify_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "verify", "1", "id", $iduser);
    nm_adminInstantReply($from_id, "✅ کاربر با موفقیت احراز گردید.", null, 'HTML');
    sendmessage($iduser, "💎 کاربر گرامی حساب کاربری شما توسط ادمین با موفقیت احراز هویت گردید و هم اکنون می توانیدخرید خود را انجام دهید", $keyboard, 'HTML');
} elseif (preg_match('/unverify-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "verify", "0", "id", $iduser);
    nm_adminInstantReply($from_id, "✅ کاربر با موفقیت از حالت احراز خارج گردید.", null, 'HTML');


} elseif (preg_match('/unbanuserr_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "Active") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['UserNotBlock'], null, 'HTML');
        return;
    }
    $textblok = "کاربر با آیدی عددی
$iduser  در ربات  رفع مسدود گردید
ادمین مسدود کننده : $from_id";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
            ],
        ]
    ]);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textblok,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
    update("user", "User_Status", "Active", "id", $iduser);
    update("user", "description_blocking", " ", "id", $iduser);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['UserUnblocked'], $keyboardadmin, 'HTML');
    sendmessage($iduser, "✳️ حساب کاربری شما از مسدودی خارج شد ✳️
اکنون میتوانید از ربات استفاده کنید ✔️", $keyboard, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmnumber_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "number", "confrim number by admin", "id", $iduser);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['phone']['active'], $keyboardadmin, 'HTML');
} elseif (preg_match('/viewpaymentuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $_stmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_user = ?");
    $_stmt->bind_param("s", $iduser);
    $_stmt->execute();
    $PaymentUsers = $_stmt->get_result();
    $_stmt->close();
    foreach ($PaymentUsers as $paymentUser) {
        $text_order = "🛒 شماره پرداخت  :  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ شناسه کاربر : <code>{$paymentUser['id_user']}</code>
💰 مبلغ پرداختی : {$paymentUser['price']} تومان
⚜️ وضعیت پرداخت : {$paymentUser['payment_Status']}
⭕️ روش پرداخت : {$paymentUser['Payment_Method']}
📆 تاریخ خرید :  {$paymentUser['time']}";
        nm_adminInstantReply($from_id, $text_order, null, 'HTML');
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['sendpayemntlist'], $keyboardadmin, 'HTML');
} elseif (preg_match('/affiliates-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $affiliatesUsers = select("user", "*", "affiliates", $iduser, "count");
    if ($affiliatesUsers == 0) {
        nm_adminInstantReply($from_id, "❌ کاربر دارای زیرمجموعه نمی باشد.", null, 'HTML');
        return;
    }
    $affiliatesUsers = select("user", "*", "affiliates", $iduser, "fetchAll");
    $count = 0;
    $text_affiliates = "";
    foreach ($affiliatesUsers as $affiliatesUser) {
        $text_affiliates .= "<code>{$affiliatesUser['id']}</code>\n\r";
        $count++;
        if ($count == 10) {
            nm_adminInstantReply($from_id, $text_affiliates, null, 'HTML');
            $count = 0;
            $text_affiliates = "";
        }
    }
    nm_adminInstantReply($from_id, $text_affiliates, null, 'HTML');
    nm_adminInstantReply($from_id, "📌 شناسه مربوط به زیرمجموعه های کاربر ارسال گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeaffiliate-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $user2 = select("user", "*", "id", $iduser, "select");
    $user2 = select("user", "*", "id", $user2['affiliates'], "select");
    $affiliatescount = intval($user2['affiliatescount']) - 1;
    update("user", "affiliatescount", $affiliatescount, "id", $user2['id']);
    update("user", "affiliates", "0", "id", $iduser);
    nm_adminInstantReply($from_id, "📌 کاربر از زیرمجموعه خارج شد.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeaffiliateuser-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "affiliatescount", "0", "id", $iduser);
    update("user", "affiliates", "0", "affiliates", $iduser);
    nm_adminInstantReply($from_id, "📌 زیرمجموعه های کاربر حذف شد.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeservice-(.*)/', $datain, $dataget)) {
    $username = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $username, "select");
    $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif (preg_match('/removeserviceandback-(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $username, "select");
    if ($info_product['Status'] == "removebyadmin") {
        nm_adminInstantReply($from_id, "❌ سرویس از قبل حذف شده است", $keyboardadmin, 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
    } else {
        if ($DataUserOut['status'] == "Unsuccessful") {
            nm_adminInstantReply($from_id, 'خطایی رخ داده است', $keyboardadmin, 'HTML');
        }
    }
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
    $Balance_user = select("user", "*", "id", $info_product['id_user'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $info_product['price_product'];
    update("user", "Balance", $Balance_add_user, "id", $info_product['id_user']);
    $textadd = "💎 کاربر عزیز مبلغ {$info_product['price_product']} تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($info_product['id_user'], $textadd, null, 'HTML');
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif ($text == "🎁 ساخت کد تخفیف" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discountsell']['GetCode'], $backadmin, 'HTML');
    step('get_codesell', $from_id);
} elseif ($user['step'] == "get_codesell") {
    if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['PriceCodesell'], null, 'HTML');
    step('get_price_codesell', $from_id);
    savedata("clear", "code", strtolower($text));
} elseif ($user['step'] == "get_price_codesell") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discountsell']['getlimit'], $backadmin, 'HTML');
    step('getlimitcode', $from_id);
} elseif ($user['step'] == "getlimitcode") {
    savedata("save", "limitDiscount", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['agentcode'], $backadmin, 'HTML');
    step('gettypecodeagent', $from_id);
} elseif ($user['step'] == "gettypecodeagent") {
    $agentst = ["n", "n2", "f", "allusers"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $bakcadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $text);
    nm_adminInstantReply($from_id, "📌 کد تخفیف برای چند ساعت فعال باشد . در صورتی که میخواهید نامحدود باشد عدد 0 را ارسال کنید", $backadmin, 'HTML');
    step('gettimediscount', $from_id);
} elseif ($user['step'] == "gettimediscount") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) == 0) {
        $text = "0";
    } else {
        $text = time() + (intval($text) * 3600);
    }
    savedata("save", "time", $text);
    $keyboarddiscount = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تمامی خرید ها", 'callback_data' => "discountlimitbuy_0"],
                ['text' => "خرید اول", 'callback_data' => "discountlimitbuy_1"],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['firstdiscount'], $keyboarddiscount, 'HTML');
    step('getfirstdiscount', $from_id);
} elseif (preg_match('/discountlimitbuy_(\w+)/', $datain, $dataget)) {
    $discountbuylimit = $dataget[1];
    savedata("save", "usefirst", $discountbuylimit);
    if (intval($discountbuylimit) == 1) {
        nm_adminInstantReply($from_id, "📌محدودیت استفاده برای یک کاربر را ارسال نمایید.", $backadmin, 'HTML');
        step('getuseuser', $from_id);
        savedata("save", "typediscount", "all");
    } else {
        $keyboarddiscount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "خرید", 'callback_data' => "discounttype_buy"],
                    ['text' => "تمدید", 'callback_data' => "discounttype_extend"],
                ],
                [
                    ['text' => "هردو", 'callback_data' => "discounttype_all"]
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 کد تخفیف برای کدوم بخش باشد", $keyboarddiscount);
    }
} elseif (preg_match('/discounttype_(\w+)/', $datain, $dataget)) {
    $discountbuytype = $dataget[1];
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    savedata("save", "typediscount", $discountbuytype);
    nm_adminInstantReply($from_id, "📌محدودیت استفاده برای یک کاربر را ارسال نمایید.", $backadmin, 'HTML');
    step('getuseuser', $from_id);
} elseif ($user['step'] == "getuseuser") {
    $userdata = json_decode($user['Processing_value'], true);
    $numberlimit = $userdata['limitDiscount'];
    if (intval($text) > intval($userdata['limitDiscount'])) {
        nm_adminInstantReply($from_id, "📌 تعداد استفاده برای یک کاربر باید کوچیک تر از محدودیت کل باشد", $backadmin, 'HTML');
        return;
    }
    step('getlocdiscount', $from_id);
    savedata("save", "useuser", $text);
    nm_adminInstantReply($from_id, "📌 برای تنظیم  کد تخفیف مخصوص یک محصول ابتدا موقعیت محصول راانتخاب نمایید.
توجه : برای انتخاب تمام پنل ها کلمه<code>/all</code> را ارسال کنید", $json_list_marzban_panel, 'HTML');
    step('getlocdiscount', $from_id);
} elseif ($user['step'] == "getlocdiscount") {
    if ($text == "/all") {
        $panel['code_panel'] = "/all";
    } else {
        $panel = select("marzban_panel", "*", "name_panel", $text, "select");
    }
    if ($panel == false)
        return;
    savedata("save", "code_panel", $panel['code_panel']);
    savedata("save", "name_panel", $text);
    nm_adminInstantReply($from_id, "📌  میخواهید کد تخفیف برای کدام محصول باشد. توجه داشتید درصورتی که میخواهید کد تخفیف برای تمامی محصولات باشد کلمه all را ارسال کنید", $json_list_product_list_admin, 'HTML');
    step('getproductdiscount', $from_id);
} elseif ($user['step'] == "getproductdiscount") {
    if ($text != "all") {
        $product = select("product", "*", "name_product", $text, "select");
    } else {
        $product['code_product'] = "all";
    }
    if ($product == false) {
        nm_adminInstantReply($from_id, "❌ محصول انتخابی وجود ندارد", $keyboardadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT INTO DiscountSell (codeDiscount, usedDiscount, price, limitDiscount, agent, usefirst, useuser, code_panel, code_product, time,type) VALUES (:codeDiscount, :usedDiscount, :price, :limitDiscount, :agent, :usefirst, :useuser, :code_panel, :code_product, :time,:type)");
    $values = "0";
    $values1 = "1";
    $code_product = "0";
    $stmt->bindParam(':codeDiscount', $userdata['code'], PDO::PARAM_STR);
    $stmt->bindParam(':usedDiscount', $values, PDO::PARAM_STR);
    $stmt->bindParam(':price', $userdata['price'], PDO::PARAM_STR);
    $stmt->bindParam(':limitDiscount', $userdata['limitDiscount'], PDO::PARAM_STR);
    $stmt->bindParam(':agent', $userdata['agent'], PDO::PARAM_STR);
    $stmt->bindParam(':usefirst', $userdata['usefirst'], PDO::PARAM_STR);
    $stmt->bindParam(':useuser', $userdata['useuser'], PDO::PARAM_STR);
    $stmt->bindParam(':code_panel', $userdata['code_panel'], PDO::PARAM_STR);
    $stmt->bindParam(':code_product', $product['code_product'], PDO::PARAM_STR);
    $stmt->bindParam(':time', $userdata['time'], PDO::PARAM_STR);
    $stmt->bindParam(':type', $userdata['typediscount'], PDO::PARAM_STR);
    $stmt->execute();
    $textdiscount = "
🎁 کد تخفیف شما با موفقیت ساخته شد.

📩 نام کد تخفیف: <code>{$userdata['code']}</code>
🧮 درصد کد تخفیف: {$userdata['price']}
🎛 پنل :  {$userdata['name_panel']}
📌  محصول : $text
♻️ نوع کاربری :‌ {$userdata['agent']}
🔴 محدودیت استفاده :‌ {$userdata['limitDiscount']}";
    nm_adminInstantReply($from_id, $textdiscount, $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ حذف کد تخفیف" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['RemoveCode'], $json_list_Discount_list_admin_sell, 'HTML');
    step('remove-Discountsell', $from_id);
} elseif ($user['step'] == "remove-Discountsell") {
    if (!in_array($text, $SellDiscount)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM Giftcodeconsumed WHERE code = :code");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM DiscountSell WHERE codeDiscount = :codeDiscount");
    $stmt->bindParam(':codeDiscount', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "/end") {
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['name_panel'], "select");
    if ($panel['type'] == "marzneshin") {
        update("user", "Processing_value", $userdata['name_panel'], "id", $from_id);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionmarzneshin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionMarzban, 'HTML');
    step('home', $from_id);
    return;
} elseif ($text == "🧮 تنظیم درصد زیرمجموعه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
    step('setpercentage', $from_id);
} elseif ($user['step'] == "setpercentage") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "درصد نامعتبر", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['changedpercentage'], $affiliates, 'HTML');
    update("setting", "affiliatespercentage", $text);
    step('home', $from_id);
} elseif ($text == "🏞 تنظیم بنر زیرمجموعه گیری") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['banner'], $backadmin, 'HTML');
    step('setbanner', $from_id);
} elseif ($user['step'] == "setbanner") {
    if (!$photo) {
        nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['invalidbanner'], $backadmin, 'HTML');
        return;
    }
    update("affiliates", "id_media", $photoid);
    update("affiliates", "description", $caption);
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['insertbanner'], $affiliates, 'HTML');
    step('home', $from_id);
} elseif ($text == "👤 آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "CartDirect");
    $textcart = "📌 نام کاربری خود را بدون @ برای دریافت شماره کارت ارسال کنید\n\n{$PaySetting['ValuePay']}";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('CartDirect', $from_id);
} elseif ($user['step'] == "CartDirect") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $CartManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "CartDirect");
    step('home', $from_id);
} elseif ($text == "💳 درگاه آفلاین در پیوی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "Cartstatuspv")['ValuePay'];
    $card_Statuspv = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $PaySetting, 'callback_data' => $PaySetting],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['cardTitlepv'], $card_Statuspv, 'HTML');
} elseif ($datain == "oncardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "offcardpv", "NamePay", "Cartstatuspv");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffpv'], null);
} elseif ($datain == "offcardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "oncardpv", "NamePay", "Cartstatuspv");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonpv'], null);
} elseif (preg_match('/addbalamceuser_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    update("user", "Processing_value", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        $ff = telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $id_order);

    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['addbalanceuserdec'], $backadmin, 'html');
    step('addbalancemanual', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "addbalancemanual") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['AddBalanceUser'], $keyboardadmin, 'HTML');
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value'], "select");
    $Balance_user = select("user", "*", "id", $Payment_report['id_user'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $text;
    $balanceusers = number_format($text, 0);
    update("user", "Balance", $Balance_add_user, "id", $Payment_report['id_user']);
    $textadd = "💎 کاربر عزیز مبلغ $balanceusers تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($Payment_report['id_user'], $textadd, null, 'HTML');
    $text_report = "تایید رسید کارت به کارت و افزایش دستی موجودی توسط ادمین

آیدی عددی کاربر : {$Payment_report['id_user']}
نام کاربری کاربر : {$Balance_user['username']}
مبلغ تراکنش در فاکتور :  {$Payment_report['price']}
مبلغ تراکنش واریزی توسط ادمین : $text";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    step('home', $from_id);
} elseif ($text == "🎁 پورسانت بعد از خرید" && $adminrulecheck['rule'] == "administrator") {
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['commission'], $keyboardcommission, 'HTML');
} elseif ($datain == "oncommission") {
    update("affiliates", "status_commission", "offcommission");
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatusOff'], $keyboardcommission);
} elseif ($datain == "offcommission") {
    update("affiliates", "status_commission", "oncommission");
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatuson'], $keyboardcommission);
} elseif ($text == "🎁 هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['Discountaffiliates'], $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "onDiscountaffiliates") {
    update("affiliates", "Discount", "offDiscountaffiliates");
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatusOff'], $keyboardDiscountaffiliates);
} elseif ($datain == "offDiscountaffiliates") {
    update("affiliates", "Discount", "onDiscountaffiliates");
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatuson'], $keyboardDiscountaffiliates);
} elseif ($text == "🌟 مبلغ هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
    step('getdiscont', $from_id);
} elseif ($user['step'] == "getdiscont") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['changedpriceDiscount'], $affiliates, 'HTML');
    update("affiliates", "price_Discount", $text);
    step('home', $from_id);
} elseif ($datain == "mainbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")[$user['agent']], true);
    $textmin = "📌 حداقل مبلغی که می خواهید کاربر حساب خود را شارژ کند را تعیین کنید";
    nm_adminInstantReply($from_id, $textmin, $backadmin, 'HTML');
    step('minbalance', $from_id);
} elseif ($user['step'] == "minbalance") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemin', $from_id);
    nm_adminInstantReply($from_id, "📌حداقل موجودی برای کدام گروه کاربری باشید.
f
n
n2", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemin") {
    $agentst = ["n", "n2", "f", "allusers"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $bakcadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")['ValuePay'], true);
    $balancemaax[$text] = $user['Processing_value'];
    $balancemaax = json_encode($balancemaax);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $balancemaax, "NamePay", "minbalance");
} elseif ($datain == "maxbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select");
    $textmax = "📌 حداکثر مبلغی که می خواهید کاربر حساب خود را شارژ کند را تعیین کنید";
    nm_adminInstantReply($from_id, $textmax, $backadmin, 'HTML');
    step('maxbalance', $from_id);
} elseif ($user['step'] == "maxbalance") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemax', $from_id);
    nm_adminInstantReply($from_id, "📌حداقل موجودی برای کدام گروه کاربری باشید.
f
n
n2", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemax") {
    $agentst = ["n", "n2", "f", "allusers"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $bakcadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'], true);
    $balancemaax[$text] = $user['Processing_value'];
    $balancemaax = json_encode($balancemaax);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $balancemaax, "NamePay", "maxbalance");
} elseif (preg_match('/removeagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['agent']['useragentremoved'],
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    update("user", "agent", "f", "id", $id_user);
    update("user", "pricediscount", "0", "id", $id_user);
    update("user", "expire", null, "id", $id_user);
    $stmt = $pdo->prepare("DELETE FROM Requestagent WHERE id = '$id_user'");
    $stmt->execute();
    step('home', $from_id);
} elseif (preg_match('/addagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['agent']['gettypeagent'],
        'parse_mode' => "HTML",
        'reply_markup' => $backadmin,
        'reply_to_message_id' => $message_id,
    ]);
    step('gettypeagentoflist', $from_id);
} elseif ($user['step'] == "gettypeagentoflist") {
    $agentst = ["n", "n2"];
    if (!in_array($text, $agentst)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
    update("user", "expire", null, "id", $user['Processing_value']);
    update("user", "agent", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif (preg_match('/Percentlow_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => "📌 تعداد درصدی که میخواهید در صورتی که کاربر هرگونه خریدی انجام داده است تخفیفی دریافت کند را ارسال نمایید.",
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('getpercentuser', $from_id);
} elseif ($user['step'] == "getpercentuser") {
    if (intval($text) > 100 || intval($text) < 0 || !ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $keyboardadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "تغییرات با موفقیت اعمال شد", $keyboardadmin, 'HTML');
    update("user", "pricediscount", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif (preg_match('/maxbuyagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغی که کاربر می توانید موجودی  اش در زمان خرید منفی شود را ارسال نمایید
توجه : عدد بدون خط تیره یا نماد منفی باشد
در صورتی که می خواهید کاربر نامحدود خریداری کند عدد 0 ارسال کنید", $backadmin, 'HTML');
    step('getmaxbuyagent', $from_id);
} elseif ($user['step'] == "getmaxbuyagent") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "تغییرات با موفقیت اعمال شد", $keyboardadmin, 'HTML');
    update("user", "maxbuyagent", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif ($datain == "searchorder") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['order']['vieworderusername'], $backadmin, 'HTML');
    step('GetusernameconfigAndOrdedrs', $from_id);
} elseif ($user['step'] == "GetusernameconfigAndOrdedrs" || strpos($text, "/config ") !== false || preg_match('/manageinvoice_(\w+)/', $datain, $datagetr)) {
    if ($user['step'] == "GetusernameconfigAndOrdedrs") {
        $usernameconfig = $text;
        $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    } elseif ($text[0] == "/") {
        $usernameconfig = explode(" ", $text)[1];
        $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    } else {
        $usernameconfig = select("invoice", "*", "id_invoice", $datagetr[1], "select")['username'];
        $sql = "SELECT * FROM invoice WHERE username = :username OR note  = :notes";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    }
    $stmt->execute();
    step("home", $from_id);
    if ($stmt->rowCount() > 1) {
        $keyboardlists = [
            'inline_keyboard' => [],
        ];
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "عملیات", 'callback_data' => "action"],
            ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
            ['text' => "نام کاربری", 'callback_data' => "username"],
        ];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
        $keyboardlists = json_encode($keyboardlists);
        nm_adminInstantReply($from_id, "⚠️ بیشتر از یک سرویس یافت از لیست زیر سرویس صحیح را انتخاب کنید", $keyboardlists, 'HTML');
        return;
    }
    $OrderUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$OrderUser) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['order']['notfound'], null, 'HTML');
        return;
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "♻️ بروزرسانی", 'callback_data' => "manageinvoice_" . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['Admin']['ManageUser']['removeservice'], 'callback_data' => "removeservice-" . $OrderUser['id_invoice']],
        ['text' => $textbotlang['Admin']['ManageUser']['removeserviceandback'], 'callback_data' => "removeserviceandback-" . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "🗑 حذف کامل سرویس", 'callback_data' => "removefull-" . $OrderUser['id_invoice']],
    ];
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
    $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC LIMIT 20");
    $stmt->execute([
        ':username' => $OrderUser['username'],
    ]);
    if ($stmt->rowCount() != 0) {
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "name_product", "code_product", $service_other['code_product'], "select");
            if ($codeproduct != false) {
                $OrderUser['name_product'] = $codeproduct['name_product'];
                $OrderUser['Volume'] = $codeproduct['Volume_constraint'];
                $OrderUser['Service_time'] = $codeproduct['Service_time'];
            }
        }
    }
    $text_order = "
🛒 شماره سفارش  :  <code>{$OrderUser['id_invoice']}</code>
🛒  وضعیت سفارش در ربات : <code>{$OrderUser['Status']}</code>
🙍‍♂️ شناسه کاربر : <code>{$OrderUser['id_user']}</code>
👤 نام کاربری اشتراک :  <code>{$OrderUser['username']}</code>
📍 موقعیت سرویس :  {$OrderUser['Service_location']}
🛍 نام محصول :  {$OrderUser['name_product']}
💰 قیمت پرداختی سرویس : {$OrderUser['price_product']} تومان
⚜️ حجم سرویس خریداری شده : {$OrderUser['Volume']}
⏳ زمان سرویس خریداری شده : {$OrderUser['Service_time']}
📆 تاریخ خرید : $datatime
";
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($OrderUser, $from_id, $keyboardadmin)) {
        $keyboard_json = json_encode($keyboardlists);
        nm_adminInstantReply($from_id, $text_order, $keyboard_json, 'HTML');
        step('home', $from_id);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($OrderUser['Service_location'], $OrderUser['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $keyboard_json = json_encode($keyboardlists);
        nm_adminInstantReply($from_id, "کاربر در پنل وجود ندارد", $keyboardadmin, 'html');
        nm_adminInstantReply($from_id, $text_order, $keyboard_json, 'HTML');
        step('home', $from_id);
        return;
    }
    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
    ][$status];

    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['stateus']['Unlimited'];

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    $lastupdate = "";
    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }
    $limitValue = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
    $usedTrafficValue = isset($DataUserOut['used_traffic']) ? (float) $DataUserOut['used_traffic'] : 0;
    $Percent = safe_divide(($limitValue - $usedTrafficValue) * 100, $limitValue, 100);
    if ($Percent < 0) {
        $Percent = -$Percent;
    }
    $Percent = round($Percent, 2);
    $text_order .= "

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
    if ($DataUserOut['status'] == "active") {
        $namestatus = '❌ خاموش کردن اکانت';
    } else {
        $namestatus = '💡 روشن کردن اکانت';
    }
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extendadmin_' . $OrderUser['id_invoice']],
        ['text' => $textbotlang['users']['stateus']['config'], 'callback_data' => 'config_' . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $namestatus, 'callback_data' => 'changestatusadmin_' . $OrderUser['id_invoice']],
    ];
    $keyboard_json = json_encode($keyboardlists);
    nm_adminInstantReply($from_id, $text_order, $keyboard_json, 'HTML');
    $stmt = $pdo->prepare("SELECT * FROM service_other s WHERE username = :uc AND (status = 'paid' OR status IS NULL)");
    $stmt->execute([':uc' => (string) $usernameconfig]);
    $list_service = $stmt->fetchAll();
    if ($list_service) {
        foreach ($list_service as $extend) {
            $extend_type = [
                'extend_user' => "تمدید",
                'extend_user_by_admin' => 'تمدید شده توسط ادمین',
                'extra_user' => "حجم اضافه",
                "extra_time_user" => "زمان اضافه",
                "transfertouser" => "انتقال به حساب دیگر",
                "extends_not_user" => "تمدید از نوع نبودن یوزر در لیست",
                "change_location" => "تغییر لوکیشن",
                'gift_time' => 'هدیه همگانی زمان',
                'gift_volume' => 'هدیه همگانی حجم'
            ][$extend['type']];
            $time_jalali = jdate('Y/m/d H:i:s', strtotime($extend['time']));

            $extendtext = "
📌 گزارش سرویس
🔗  نوع سرویس : $extend_type
🕰 زمان انجام سرویس : {$extend['time']} \n\n($time_jalali)
💰مبلغ انجام سرویس : {$extend['price']}
👤 آیدی عددی کاربر : {$extend['id_user']}
👤 نام کاربری کانفیگ: {$extend['username']}";
            nm_adminInstantReply($from_id, $extendtext, null, 'HTML');
        }
    }
    step('home', $from_id);
} elseif ($text == "🛒 وضعیت قابلیت های فروشگاه" && $adminrulecheck['rule'] == "administrator") {
    $setting = select("setting", "*", null, null, "select") ?? [];

    $marzbanstatusextraRow = select("shopSetting", "*", "Namevalue", "statusextra", "select") ?? [];
    $marzbandirectpayRow = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select") ?? [];
    $statustimeextraRow = select("shopSetting", "*", "Namevalue", "statustimeextra", "select") ?? [];
    $statusdisorderRow = select("shopSetting", "*", "Namevalue", "statusdisorder", "select") ?? [];
    $statuschangeserviceRow = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select") ?? [];
    $statusshowpriceRow = select("shopSetting", "*", "Namevalue", "statusshowprice", "select") ?? [];
    $statusshowconfigRow = select("shopSetting", "*", "Namevalue", "configshow", "select") ?? [];
    $statusremoveserveiceRow = select("shopSetting", "*", "Namevalue", "backserviecstatus", "select") ?? [];

    $marzbanstatusextra = $marzbanstatusextraRow['value'] ?? 'offextra';
    $marzbandirectpay = $marzbandirectpayRow['value'] ?? 'offdirectbuy';
    $statustimeextra = $statustimeextraRow['value'] ?? 'offtimeextraa';
    $statusdisorder = $statusdisorderRow['value'] ?? 'offdisorder';
    $statuschangeservice = $statuschangeserviceRow['value'] ?? 'offstatus';
    $statusshowprice = $statusshowpriceRow['value'] ?? 'offshowprice';
    $statusshowconfig = $statusshowconfigRow['value'] ?? 'offconfig';
    $statusremoveserveice = $statusremoveserveiceRow['value'] ?? 'off';

    $categoryStatusGeneralKey = $setting['statuscategorygenral'] ?? 'offcategorys';
    if (!in_array($categoryStatusGeneralKey, ['oncategorys', 'offcategorys'], true)) {
        $categoryStatusGeneralKey = 'offcategorys';
    }
    $categoryStatusKey = $setting['statuscategory'] ?? 'offcategory';
    if (!in_array($categoryStatusKey, ['oncategory', 'offcategory'], true)) {
        $categoryStatusKey = 'offcategory';
    }

    $name_status_extra_Vloume = [
        'onextra' => $textbotlang['Admin']['Status']['statuson'],
        'offextra' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbanstatusextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_paydirect = [
        'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
        'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbandirectpay] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_timeextra = [
        'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
        'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
    ][$statustimeextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_disorder = [
        'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
        'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusdisorder] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $categorygenral = [
        'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
        'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusGeneralKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statustextchange = [
        'onstatus' => $textbotlang['Admin']['Status']['statuson'],
        'offstatus' => $textbotlang['Admin']['Status']['statusoff']
    ][$statuschangeservice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusshowpricestext = [
        'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
        'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowprice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusshowconfigtext = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowconfig] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusbackremovetext = [
        'on' => $textbotlang['Admin']['Status']['statuson'],
        'off' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusremoveserveice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_categorytime = [
        'oncategory' => $textbotlang['Admin']['Status']['statuson'],
        'offcategory' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => (string)($textbotlang['Admin']['Status']['statussubject'] ?? '📌 موضوع'), 'callback_data' => "subjectde"],
                ['text' => (string)($textbotlang['Admin']['Status']['subject'] ?? '📌 موضوع'), 'callback_data' => "subject"],
            ],
            [
                ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                ['text' => (string)($textbotlang['Admin']['Status']['statusvolumeextra'] ?? '📦 حجم اضافه'), 'callback_data' => "extravolunme"],
            ],
            [
                ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                ['text' => (string)($textbotlang['Admin']['Status']['paydirect'] ?? '💳 پرداخت مستقیم'), 'callback_data' => "paydirect"],
            ],
            [
                ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                ['text' => (string)($textbotlang['Admin']['Status']['statustimeextra'] ?? '⏱ زمان اضافه'), 'callback_data' => "statustimeextra"],
            ],
            [
                ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                ['text' => "⚠️ ارسال گزارش اختلال", 'callback_data' => "disorderss"],
            ],
            [
                ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                ['text' => "🐛 دسته بندی ", 'callback_data' => "categroygenral"],
            ],
            [
                ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                ['text' => (string)($textbotlang['Admin']['Status']['statuscategorytime'] ?? '📂 دسته زمان‌دار'), 'callback_data' => "statuscategorytime"],
            ],
            [
                ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                ['text' => "❓وضعیت غیرفعال کردن اکانت", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 نمایش قیمت محصول", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 دکمه دریافت کانفیگ", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 دکمه بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editshops-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "extravolunme") {
        if ($value == "onextra") {
            $valuenew = "offextra";
        } else {
            $valuenew = "onextra";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusextra");
    } elseif ($type == "paydirect") {
        if ($value == "ondirectbuy") {
            $valuenew = "offdirectbuy";
        } else {
            $valuenew = "ondirectbuy";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusdirectpabuy");
    } elseif ($type == "statustimeextra") {
        if ($value == "ontimeextraa") {
            $valuenew = "offtimeextraa";
        } else {
            $valuenew = "ontimeextraa";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statustimeextra");
    } elseif ($type == "disorderss") {
        if ($value == "ondisorder") {
            $valuenew = "offdisorder";
        } else {
            $valuenew = "ondisorder";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusdisorder");
    } elseif ($type == "categroygenral") {
        if ($value == "oncategorys") {
            $valuenew = "offcategorys";
        } else {
            $valuenew = "oncategorys";
        }
        update("setting", "statuscategorygenral", $valuenew, null, null);
    } elseif ($type == "changgestatus") {
        if ($value == "onstatus") {
            $valuenew = "offstatus";
        } else {
            $valuenew = "onstatus";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statuschangeservice");
    } elseif ($type == "showprice") {
        if ($value == "onshowprice") {
            $valuenew = "offshowprice";
        } else {
            $valuenew = "onshowprice";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusshowprice");
    } elseif ($type == "showconfig") {
        if ($value == "onconfig") {
            $valuenew = "offconfig";
        } else {
            $valuenew = "onconfig";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "configshow");
    } elseif ($type == "removeservicebackbtn") {
        if ($value == "on") {
            $valuenew = "off";
        } else {
            $valuenew = "on";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "backserviecstatus");
    } elseif ($type == "categorytime") {
        if ($value == "oncategory") {
            $valuenew = "offcategory";
        } else {
            $valuenew = "oncategory";
        }
        update("setting", "statuscategory", $valuenew);
    }
    $setting = select("setting", "*", null, null, "select") ?? [];

    $marzbanstatusextraRow = select("shopSetting", "*", "Namevalue", "statusextra", "select") ?? [];
    $marzbandirectpayRow = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select") ?? [];
    $statustimeextraRow = select("shopSetting", "*", "Namevalue", "statustimeextra", "select") ?? [];
    $statusdisorderRow = select("shopSetting", "*", "Namevalue", "statusdisorder", "select") ?? [];
    $statuschangeserviceRow = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select") ?? [];
    $statusshowpriceRow = select("shopSetting", "*", "Namevalue", "statusshowprice", "select") ?? [];
    $statusshowconfigRow = select("shopSetting", "*", "Namevalue", "configshow", "select") ?? [];
    $statusremoveserveiceRow = select("shopSetting", "*", "Namevalue", "backserviecstatus", "select") ?? [];

    $marzbanstatusextra = $marzbanstatusextraRow['value'] ?? 'offextra';
    $marzbandirectpay = $marzbandirectpayRow['value'] ?? 'offdirectbuy';
    $statustimeextra = $statustimeextraRow['value'] ?? 'offtimeextraa';
    $statusdisorder = $statusdisorderRow['value'] ?? 'offdisorder';
    $statuschangeservice = $statuschangeserviceRow['value'] ?? 'offstatus';
    $statusshowprice = $statusshowpriceRow['value'] ?? 'offshowprice';
    $statusshowconfig = $statusshowconfigRow['value'] ?? 'offconfig';
    $statusremoveserveice = $statusremoveserveiceRow['value'] ?? 'off';

    $categoryStatusGeneralKey = $setting['statuscategorygenral'] ?? 'offcategorys';
    if (!in_array($categoryStatusGeneralKey, ['oncategorys', 'offcategorys'], true)) {
        $categoryStatusGeneralKey = 'offcategorys';
    }

    $categoryStatusKey = $setting['statuscategory'] ?? 'offcategory';
    if (!in_array($categoryStatusKey, ['oncategory', 'offcategory'], true)) {
        $categoryStatusKey = 'offcategory';
    }

    $name_status_extra_Vloume = [
        'onextra' => $textbotlang['Admin']['Status']['statuson'],
        'offextra' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbanstatusextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_paydirect = [
        'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
        'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbandirectpay] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_timeextra = [
        'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
        'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
    ][$statustimeextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_disorder = [
        'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
        'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusdisorder] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $categorygenral = [
        'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
        'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusGeneralKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statustextchange = [
        'onstatus' => $textbotlang['Admin']['Status']['statuson'],
        'offstatus' => $textbotlang['Admin']['Status']['statusoff']
    ][$statuschangeservice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusshowpricestext = [
        'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
        'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowprice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusshowconfigtext = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowconfig] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusbackremovetext = [
        'on' => $textbotlang['Admin']['Status']['statuson'],
        'off' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusremoveserveice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_categorytime = [
        'oncategory' => $textbotlang['Admin']['Status']['statuson'],
        'offcategory' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => (string)($textbotlang['Admin']['Status']['statussubject'] ?? '📌 موضوع'), 'callback_data' => "subjectde"],
                ['text' => (string)($textbotlang['Admin']['Status']['subject'] ?? '📌 موضوع'), 'callback_data' => "subject"],
            ],
            [
                ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                ['text' => (string)($textbotlang['Admin']['Status']['statusvolumeextra'] ?? '📦 حجم اضافه'), 'callback_data' => "extravolunme"],
            ],
            [
                ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                ['text' => (string)($textbotlang['Admin']['Status']['paydirect'] ?? '💳 پرداخت مستقیم'), 'callback_data' => "paydirect"],
            ],
            [
                ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                ['text' => (string)($textbotlang['Admin']['Status']['statustimeextra'] ?? '⏱ زمان اضافه'), 'callback_data' => "statustimeextra"],
            ],
            [
                ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                ['text' => "⚠️ ارسال گزارش اختلال", 'callback_data' => "disorderss"],
            ],
            [
                ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                ['text' => "🐛 دسته بندی ", 'callback_data' => "categroygenral"],
            ],
            [
                ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                ['text' => (string)($textbotlang['Admin']['Status']['statuscategorytime'] ?? '📂 دسته زمان‌دار'), 'callback_data' => "statuscategorytime"],
            ],
            [
                ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                ['text' => "❓وضعیت غیرفعال کردن اکانت", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 نمایش قیمت محصول", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 دکمه دریافت کانفیگ", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 دکمه بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status);
} elseif ($text == "🪪 خروجی گرفتن اطلاعات" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardexportdata, 'HTML');
} elseif ($text == "🕚 تنظیمات کرون جاب" && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_cron_settings', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($datain == "cronjobs_settings" && $adminrulecheck['rule'] == "administrator") {
    if (function_exists('buildCronJobsKeyboard')) {
        step('admin_nav_cron_jobs', $from_id);
        $rx_cron_title = "🕚 زمان‌بندی کرون‌ها\n\nبرای تغییر بازهٔ اجرای هر کرون روی ⚙️ تنظیمات همان ردیف بزنید.";
        nm_adminInstantReply($from_id, $rx_cron_title, buildCronJobsKeyboard(), 'HTML');
    } else {
        nm_adminInstantReply($from_id, "❌ سیستم کرون در دسترس نیست.", $setting_panel, 'HTML');
    }
} elseif ($datain == "cronjob_display" && $adminrulecheck['rule'] == "administrator") {
    if (!empty($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'cache_time' => 1,
        ]);
    }
} elseif ($datain == "cronjobs_back_settings" && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_cron_settings', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif (preg_match('/^cronjob_config-([A-Za-z0-9_]+)$/', $datain, $rx_cron_cfg) && $adminrulecheck['rule'] == "administrator") {
    if (function_exists('getCronJobDefinitions') && function_exists('loadCronSchedules') && function_exists('describeCronSchedule')) {
        $rx_cron_key = $rx_cron_cfg[1];
        $rx_cron_defs = getCronJobDefinitions();
        if (!isset($rx_cron_defs[$rx_cron_key])) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => "❌ کرون نامعتبر",
                'show_alert' => true,
                'cache_time' => 5,
            ]);
            return;
        }
        $rx_cron_def = $rx_cron_defs[$rx_cron_key];
        $rx_cron_schedules = loadCronSchedules();
        $rx_cron_current = $rx_cron_schedules[$rx_cron_key] ?? $rx_cron_def['default'];
        $rx_cron_current_label = describeCronSchedule($rx_cron_current);
        $rx_cron_label = (string) ($rx_cron_def['admin_label'] ?? $rx_cron_key);
        $rx_cron_minute_options = [1, 2, 3, 5, 10, 15, 30];
        $rx_cron_hour_options   = [1, 2, 3, 6, 12];
        $rx_cron_day_options    = [1, 2, 5, 7];
        $rx_cron_rows = [];
        $rx_cron_row = [];
        foreach ($rx_cron_minute_options as $rx_cron_v) {
            $rx_cron_row[] = ['text' => "🕒 هر {$rx_cron_v} دقیقه", 'callback_data' => "cronjob_apply-{$rx_cron_key}-minute-{$rx_cron_v}"];
            if (count($rx_cron_row) === 2) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        }
        if (!empty($rx_cron_row)) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        foreach ($rx_cron_hour_options as $rx_cron_v) {
            $rx_cron_row[] = ['text' => "⏰ هر {$rx_cron_v} ساعت", 'callback_data' => "cronjob_apply-{$rx_cron_key}-hour-{$rx_cron_v}"];
            if (count($rx_cron_row) === 2) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        }
        if (!empty($rx_cron_row)) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        foreach ($rx_cron_day_options as $rx_cron_v) {
            $rx_cron_row[] = ['text' => "📅 هر {$rx_cron_v} روز", 'callback_data' => "cronjob_apply-{$rx_cron_key}-day-{$rx_cron_v}"];
            if (count($rx_cron_row) === 2) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        }
        if (!empty($rx_cron_row)) { $rx_cron_rows[] = $rx_cron_row; }
        $rx_cron_rows[] = [
            ['text' => "⛔ غیرفعال", 'callback_data' => "cronjob_apply-{$rx_cron_key}-disabled-1"],
        ];
        $rx_cron_rows[] = [
            ['text' => "🔙 بازگشت به لیست کرون‌ها", 'callback_data' => "cronjobs_settings"],
        ];
        $rx_cron_keyboard = json_encode(['inline_keyboard' => $rx_cron_rows], JSON_UNESCAPED_UNICODE);
        $rx_cron_text = "⚙️ تنظیم زمان‌بندی\n\n📌 کرون: <b>{$rx_cron_label}</b>\n⏱ زمان فعلی: <b>{$rx_cron_current_label}</b>\n\nزمان‌بندی جدید را انتخاب کنید:";
        step('cronjob_set_value', $from_id);
        nm_adminInstantReply($from_id, $rx_cron_text, $rx_cron_keyboard, 'HTML');
    }
} elseif (preg_match('/^cronjob_apply-([A-Za-z0-9_]+)-(minute|hour|day|disabled)-(\d+)$/', $datain, $rx_cron_apply) && $adminrulecheck['rule'] == "administrator") {
    if (function_exists('updateCronSchedule') && function_exists('buildCronJobsKeyboard')) {
        $rx_cron_key  = $rx_cron_apply[1];
        $rx_cron_unit = $rx_cron_apply[2];
        $rx_cron_val  = max(1, (int) $rx_cron_apply[3]);
        $rx_cron_ok   = updateCronSchedule($rx_cron_key, ['unit' => $rx_cron_unit, 'value' => $rx_cron_val]);
        if (function_exists('activecron')) {
            try { @activecron(); } catch (Throwable $rx_cron_e) {}
        }
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $rx_cron_ok ? "✅ ذخیره شد" : "❌ ذخیره نشد",
                'show_alert' => false,
                'cache_time' => 1,
            ]);
        }
        step('admin_nav_cron_jobs', $from_id);
        nm_adminInstantReply($from_id, "🕚 زمان‌بندی کرون‌ها\n\nبرای تغییر بازهٔ اجرای هر کرون روی ⚙️ تنظیمات همان ردیف بزنید.", buildCronJobsKeyboard(), 'HTML');
    }
} elseif ($text == "خروجی کاربران" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("user", "*", null, null, "count");
    if ($counttable == 0) {
        nm_adminInstantReply($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM user";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "users_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 خروجی دیتای کاربران");
    unlink($filename);
} elseif ($text == "خروجی سفارشات" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("invoice", "*", null, null, "count");
    if ($counttable == 0) {
        nm_adminInstantReply($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM invoice";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "invoice_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 خروجی سفارشات کاربران");
    unlink($filename);
} elseif ($text == "خروجی گرفتن پرداخت ها" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("Payment_report", "*", null, null, "count");
    if ($counttable == 0) {
        nm_adminInstantReply($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM Payment_report";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "Payment_report_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 خروجی پرداختی های کاربران");
    unlink($filename);
} elseif (preg_match('/rejectremoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    step("descriptionsrequsts", $from_id);
    update("user", "Processing_value", $requestcheck['username'], "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['requestadmin'], $backuser, 'HTML');
} elseif ($user['step'] == "descriptionsrequsts") {
    nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['accecptreqests'], $keyboardadmin, 'HTML');
    $nameloc = select("invoice", "*", "username", $user['Processing_value'], "select");
    update("cancel_service", "status", "reject", "username", $user['Processing_value']);
    update("cancel_service", "description", $text, "username", $user['Processing_value']);
    step("home", $from_id);
    sendmessage($nameloc['id_user'], "❌ کاربری گرامی درخواست حذف شما با نام کاربری  {$user['Processing_value']} موافقت نگردید.

        دلیل عدم تایید : $text", null, 'HTML');
} elseif (preg_match('/remoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $nameloc = select("invoice", "*", "username", $requestcheck['username'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $requestcheck['username']);
    $stmt = $pdo->prepare("SELECT  SUM(price) FROM service_other WHERE username = :username AND type != 'change_location' AND type != 'extend_user' LIMIT 1");
    $stmt->bindParam(':username', $nameloc['username']);
    $stmt->execute();
    $sumproduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['data_limit'] == null && $DataUserOut['expire'] == null) {
        nm_adminInstantReply($from_id, "❌ به دلیل نامحدود بودن حجم و زمان امکان حذف سرویس وجود ندارد. ", null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        $pricelast = $invoice['price_product'];
    } elseif ($DataUserOut['data_limit'] == null) {
        $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
        if ($serviceTime > 0) {
            $pricetime = safe_divide($nameloc['price_product'], $serviceTime, 0) + intval($sumproduct['SUM(price)']);
            $pricelast = (($DataUserOut['expire'] - time()) / 86400) * $pricetime;
        } else {
            $pricelast = 0;
        }
    } elseif ($DataUserOut['expire'] == null) {
        $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
        if ($dataLimit > 0) {
            $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
            $volumeDivisor = $dataLimit / pow(1024, 3);
            $volumeleft = $volumeDivisor > 0 ? safe_divide($volumelefts, $volumeDivisor, 0) : 0;
            $pricelast = round($volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
        } else {
            $pricelast = 0;
        }
    } else {
        $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
        $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
        $volumeDivisor = $dataLimit / pow(1024, 3);
        if ($serviceTime > 0 && $volumeDivisor > 0) {
            $timeleft = safe_divide(round(($DataUserOut['expire'] - time()) / 86400, 0), $serviceTime, 0);
            $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
            $volumeleft = safe_divide($volumelefts, $volumeDivisor, 0);
            $pricelast = round($timeleft * $volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
        } else {
            $pricelast = 0;
        }
    }
    $pricelast = intval($pricelast);
    if (intval($pricelast) != 0) {


        $stmtAtomicRefund = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
        $stmtAtomicRefund->bindValue(':delta', (int) $pricelast, PDO::PARAM_INT);
        $stmtAtomicRefund->bindValue(':uid', $nameloc['id_user'], PDO::PARAM_STR);
        $stmtAtomicRefund->execute();
        sendmessage($nameloc['id_user'], "💰کاربر گرامی مبلغ $pricelast تومان به موجودی شما اضافه گردید.", null, 'HTML');
    }
    $ManagePanel->RemoveUser($nameloc['Service_location'], $requestcheck['username']);
    update("cancel_service", "status", "accept", "username", $requestcheck['username']);
    update("invoice", "status", "removedbyadmin", "username", $requestcheck['username']);
    nm_adminInstantReply($from_id, "❌ مبلغ $pricelast تومان به موجودی کاربر اضافه گردید.", null, 'HTML');
    sendmessage($nameloc['id_user'], "✅ کاربری گرامی درخواست حذف شما با نام کاربری  {$nameloc['username']} موافقت گردید.", null, 'HTML');
    $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد

اطلاعات کاربر تایید کننده  :

🪪 آیدی عددی : <code>$from_id</code>
💰 مبلغ بازگشتی : $pricelast تومان
👤 نام کاربری : {$requestcheck['username']}
        آیدی عددی درخواست کننده کنسل کردن : {$nameloc['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/remoceserviceadminmanual-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
    $ManagePanel->RemoveUser($invoice['Service_location'], $requestcheck['username']);
    update("cancel_service", "status", "accept", "username", $requestcheck['username']);
    update("invoice", "status", "removedbyadmin", "username", $requestcheck['username']);
    sendmessage($invoice['id_user'], "✅ کاربری گرامی درخواست حذف شما با نام کاربری  {$invoice['username']} موافقت گردید.", null, 'HTML');
    nm_adminInstantReply($from_id, "📌 مبلغ  برای بازگشت وجه را ارسال نمایید", $backadmin, 'HTML');
    step("getpricebackremove", $from_id);
} elseif ($user['step'] == "getpricebackremove") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $invoice = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");

    $stmtAtomicRefund2 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $stmtAtomicRefund2->bindValue(':delta', (int) $text, PDO::PARAM_INT);
    $stmtAtomicRefund2->bindValue(':uid', $invoice['id_user'], PDO::PARAM_STR);
    $stmtAtomicRefund2->execute();
    sendmessage($invoice['id_user'], "💰کاربر گرامی مبلغ $text تومان به موجودی شما اضافه گردید.", null, 'HTML');
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت به حساب کاربر اضافه گردید.", $keyboardadmin, 'HTML');
    $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد

اطلاعات کاربر تایید کننده  :

🪪 آیدی عددی : <code>$from_id</code>
💰 مبلغ بازگشتی : $text تومان
👤 نام کاربری : {$invoice['username']}
آیدی عددی درخواست کننده کنسل کردن : {$invoice['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/^nmrefokdef_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $nm_inv_id = $dataget[1];
    $nm_invoice = select("invoice", "*", "id_invoice", $nm_inv_id, "select");
    if (!is_array($nm_invoice) || (string)($nm_invoice['Status'] ?? '') !== 'nm_refund_pending') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده یا نامعتبر است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }


    $stmtClaimDefault = $pdo->prepare(
        "UPDATE invoice SET Status = 'removebyuser' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'"
    );
    $stmtClaimDefault->bindValue(':inv', $nm_inv_id, PDO::PARAM_STR);
    $stmtClaimDefault->execute();
    if ($stmtClaimDefault->rowCount() === 0) {
        if (function_exists('rx_log_event')) {
            rx_log_event('NM_REFUND_RACE', 'Default refund raced; dropping duplicate', [
                'invoice' => $nm_inv_id,
                'admin'   => $from_id,
            ]);
        }
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    $nm_target_user = select("user", "*", "id", $nm_invoice['id_user'], "select");
    $nm_refund_amount = (float)($nm_invoice['price_product'] ?? 0);
    $stmtAtomicNmRefund = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $stmtAtomicNmRefund->bindValue(':delta', (int) round($nm_refund_amount), PDO::PARAM_INT);
    $stmtAtomicNmRefund->bindValue(':uid', $nm_invoice['id_user'], PDO::PARAM_STR);
    $stmtAtomicNmRefund->execute();
    if (function_exists('nmStockLog')) {
        try { nmStockLog(null, $nm_invoice['id_user'], $nm_inv_id, 'refund_approved_default', ['refund' => $nm_refund_amount, 'admin' => $from_id]); } catch (Throwable $e) {}
    }
    sendmessage($nm_invoice['id_user'], "✅ سرویس انبار از لیست فعال خارج شد و مبلغ " . number_format($nm_refund_amount) . " تومان به کیف پول شما برگشت خورد.", null, 'HTML');
    $nm_admin_done = "✅ بازگشت وجه تأیید شد.\n\n💰 مبلغ: " . number_format($nm_refund_amount) . " تومان\n👤 کاربر: <code>" . $nm_invoice['id_user'] . "</code>\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>";
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        Editmessagetext($from_id, $message_id, $nm_admin_done, null, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $nm_admin_done, $keyboardadmin, 'HTML');
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'تأیید شد',
        'show_alert' => false,
        'cache_time' => 2,
    ]);
    if (!empty($setting['Channel_Report'])) {
        $nm_report_payload = [
            'chat_id' => $setting['Channel_Report'],
            'text' => "⭕️ ادمین درخواست بازگشت وجه انبار را تأیید کرد\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ بازگشتی: " . number_format($nm_refund_amount) . " تومان\n👤 کاربر: <code>" . $nm_invoice['id_user'] . "</code>\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>",
            'parse_mode' => 'HTML',
        ];
        if (!empty($otherreport)) $nm_report_payload['message_thread_id'] = $otherreport;
        telegram('sendmessage', $nm_report_payload);
    }
} elseif (preg_match('/^nmrefcustom_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $nm_inv_id = $dataget[1];
    $nm_invoice = select("invoice", "*", "id_invoice", $nm_inv_id, "select");
    if (!is_array($nm_invoice) || (string)($nm_invoice['Status'] ?? '') !== 'nm_refund_pending') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده یا نامعتبر است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    update("user", "Processing_value", $nm_inv_id, "id", $from_id);
    step("nm_getpricebackrefund", $from_id);
    nm_adminInstantReply($from_id, "📌 مبلغی که باید به کیف‌پول کاربر اضافه شود را به‌صورت عدد (تومان) ارسال کنید.\n\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>\n💰 مبلغ پیش‌فرض پرداختی: " . number_format((float)($nm_invoice['price_product'] ?? 0)) . " تومان", $backadmin, 'HTML');
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'مبلغ موردنظر را ارسال کنید',
        'show_alert' => false,
        'cache_time' => 2,
    ]);
} elseif ($user['step'] == "nm_getpricebackrefund" && $adminrulecheck['rule'] == "administrator") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'] ?? '❌ مقدار واردشده معتبر نیست. لطفاً فقط عدد ارسال کنید.', $backadmin, 'HTML');
        return;
    }
    $nm_inv_id = $user['Processing_value'];
    $nm_invoice = select("invoice", "*", "id_invoice", $nm_inv_id, "select");
    if (!is_array($nm_invoice) || (string)($nm_invoice['Status'] ?? '') !== 'nm_refund_pending') {
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده یا نامعتبر است.", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }


    $stmtClaimCustom = $pdo->prepare(
        "UPDATE invoice SET Status = 'removebyuser' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'"
    );
    $stmtClaimCustom->bindValue(':inv', $nm_inv_id, PDO::PARAM_STR);
    $stmtClaimCustom->execute();
    if ($stmtClaimCustom->rowCount() === 0) {
        if (function_exists('rx_log_event')) {
            rx_log_event('NM_REFUND_RACE', 'Custom refund raced; dropping duplicate', [
                'invoice' => $nm_inv_id,
                'admin'   => $from_id,
            ]);
        }
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده یا نامعتبر است.", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $nm_target_user = select("user", "*", "id", $nm_invoice['id_user'], "select");
    $nm_refund_amount = intval($text);
    $stmtAtomicNmRefundCustom = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $stmtAtomicNmRefundCustom->bindValue(':delta', (int) $nm_refund_amount, PDO::PARAM_INT);
    $stmtAtomicNmRefundCustom->bindValue(':uid', $nm_invoice['id_user'], PDO::PARAM_STR);
    $stmtAtomicNmRefundCustom->execute();
    if (function_exists('nmStockLog')) {
        try { nmStockLog(null, $nm_invoice['id_user'], $nm_inv_id, 'refund_approved_custom', ['refund' => $nm_refund_amount, 'admin' => $from_id]); } catch (Throwable $e) {}
    }
    sendmessage($nm_invoice['id_user'], "✅ سرویس انبار از لیست فعال خارج شد و مبلغ " . number_format($nm_refund_amount) . " تومان به کیف پول شما برگشت خورد.", null, 'HTML');
    nm_adminInstantReply($from_id, "✅ مبلغ " . number_format($nm_refund_amount) . " تومان به حساب کاربر اضافه شد.", $keyboardadmin, 'HTML');
    step("home", $from_id);
    if (!empty($setting['Channel_Report'])) {
        $nm_report_payload = [
            'chat_id' => $setting['Channel_Report'],
            'text' => "⭕️ ادمین درخواست بازگشت وجه انبار را با مبلغ دلخواه تأیید کرد\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ بازگشتی: " . number_format($nm_refund_amount) . " تومان\n👤 کاربر: <code>" . $nm_invoice['id_user'] . "</code>\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>",
            'parse_mode' => 'HTML',
        ];
        if (!empty($otherreport)) $nm_report_payload['message_thread_id'] = $otherreport;
        telegram('sendmessage', $nm_report_payload);
    }
} elseif (preg_match('/^nmrefreject_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $nm_inv_id = $dataget[1];
    $nm_invoice = select("invoice", "*", "id_invoice", $nm_inv_id, "select");
    if (!is_array($nm_invoice) || (string)($nm_invoice['Status'] ?? '') !== 'nm_refund_pending') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده یا نامعتبر است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    update("invoice", "Status", "active", "id_invoice", $nm_inv_id);
    if (function_exists('nmStockLog')) {
        try { nmStockLog(null, $nm_invoice['id_user'], $nm_inv_id, 'refund_rejected', ['admin' => $from_id]); } catch (Throwable $e) {}
    }
    sendmessage($nm_invoice['id_user'], "❌ درخواست بازگشت وجه شما برای سرویس انبار توسط ادمین رد شد. سرویس همچنان فعال است.", null, 'HTML');
    $nm_admin_done = "❌ درخواست بازگشت وجه رد شد.\n\n👤 کاربر: <code>" . $nm_invoice['id_user'] . "</code>\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>";
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        Editmessagetext($from_id, $message_id, $nm_admin_done, null, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $nm_admin_done, $keyboardadmin, 'HTML');
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'رد شد',
        'show_alert' => false,
        'cache_time' => 2,
    ]);
} elseif (preg_match('/^mafurefauto-([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $mafu_inv_id = $dataget[1];
    $mafu_invoice = select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
    if (!is_array($mafu_invoice)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'فاکتور پیدا نشد.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    $mafu_status = (string)($mafu_invoice['Status'] ?? '');
    if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }

    $mafu_price = (int)($mafu_invoice['price_product'] ?? 0);
    $mafu_user_id = (string)($mafu_invoice['id_user'] ?? '');
    $mafu_username_svc = (string)($mafu_invoice['username'] ?? '');
    $mafu_panel_name = (string)($mafu_invoice['Service_location'] ?? '');

    if ($mafu_price <= 0 || $mafu_user_id === '' || $mafu_username_svc === '') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'اطلاعات فاکتور ناقص است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }


    $mafu_claim = $pdo->prepare("UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status NOT IN ('removedbyadmin','removebyuser','refunded')");
    $mafu_claim->bindValue(':inv', $mafu_inv_id, PDO::PARAM_STR);
    $mafu_claim->execute();
    if ($mafu_claim->rowCount() === 0) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً پردازش شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }


    if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser')) {
        try { @$ManagePanel->RemoveUser($mafu_panel_name, $mafu_username_svc); } catch (\Throwable $_) {}
    }


    $mafu_bal = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $mafu_bal->bindValue(':delta', $mafu_price, PDO::PARAM_INT);
    $mafu_bal->bindValue(':uid', $mafu_user_id, PDO::PARAM_STR);
    $mafu_bal->execute();

    sendmessage($mafu_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($mafu_price) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $mafu_username_svc . "</code> حذف شد.", null, 'HTML');

    $mafu_done = "✅ بازگشت وجه خودکار انجام شد.\n\n💰 مبلغ: " . number_format($mafu_price) . " تومان\n👤 کاربر: <code>$mafu_user_id</code>\n📛 سرویس: <code>$mafu_username_svc</code>\n🆔 فاکتور: <code>$mafu_inv_id</code>";
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        Editmessagetext($from_id, $message_id, $mafu_done, null, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $mafu_done, $keyboardadmin ?? null, 'HTML');
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'بازگشت وجه انجام شد',
        'show_alert' => false,
        'cache_time' => 2,
    ]);

    if (!empty($setting['Channel_Report'])) {
        $mafu_rep = [
            'chat_id'    => $setting['Channel_Report'],
            'text'       => "⭕️ بازگشت وجه خودکار (مینی‌اپ)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($mafu_price) . " تومان\n👤 کاربر: <code>$mafu_user_id</code>\n📛 سرویس: <code>$mafu_username_svc</code>\n🆔 فاکتور: <code>$mafu_inv_id</code>",
            'parse_mode' => 'HTML',
        ];
        if (!empty($otherreport)) $mafu_rep['message_thread_id'] = $otherreport;
        telegram('sendmessage', $mafu_rep);
    }
} elseif (preg_match('/^mafurefmanu-([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $mafu_inv_id = $dataget[1];
    $mafu_invoice = select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
    if (!is_array($mafu_invoice)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'فاکتور پیدا نشد.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    $mafu_status = (string)($mafu_invoice['Status'] ?? '');
    if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }

    update("user", "Processing_value", $mafu_inv_id, "id", $from_id);
    step("mafurefamount", $from_id);

    $mafu_default = number_format((int)($mafu_invoice['price_product'] ?? 0));
    nm_adminInstantReply(
        $from_id,
        "📌 مبلغ بازگشتی برای این فاکتور را به‌صورت عدد (تومان) ارسال کنید.\n\n🆔 فاکتور: <code>$mafu_inv_id</code>\n💵 مبلغ خرید اولیه: $mafu_default تومان\n📛 سرویس: <code>" . htmlspecialchars((string)($mafu_invoice['username'] ?? '')) . "</code>",
        $backadmin ?? null,
        'HTML'
    );
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'مبلغ موردنظر را ارسال کنید',
        'show_alert' => false,
        'cache_time' => 2,
    ]);
} elseif (($user['step'] ?? '') == "mafurefamount" && $adminrulecheck['rule'] == "administrator") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'] ?? '❌ مقدار معتبر نیست. فقط عدد ارسال کنید.', $backadmin ?? null, 'HTML');
        return;
    }

    $mafu_inv_id = (string)$user['Processing_value'];
    $mafu_invoice = select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
    if (!is_array($mafu_invoice)) {
        nm_adminInstantReply($from_id, "❌ فاکتور پیدا نشد.", $keyboardadmin ?? null, 'HTML');
        step("home", $from_id);
        return;
    }
    $mafu_status = (string)($mafu_invoice['Status'] ?? '');
    if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده است.", $keyboardadmin ?? null, 'HTML');
        step("home", $from_id);
        return;
    }

    $mafu_amount = intval($text);
    $mafu_user_id = (string)($mafu_invoice['id_user'] ?? '');
    $mafu_username_svc = (string)($mafu_invoice['username'] ?? '');
    $mafu_panel_name = (string)($mafu_invoice['Service_location'] ?? '');


    $mafu_claim = $pdo->prepare("UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status NOT IN ('removedbyadmin','removebyuser','refunded')");
    $mafu_claim->bindValue(':inv', $mafu_inv_id, PDO::PARAM_STR);
    $mafu_claim->execute();
    if ($mafu_claim->rowCount() === 0) {
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً پردازش شده است.", $keyboardadmin ?? null, 'HTML');
        step("home", $from_id);
        return;
    }


    if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser') && $mafu_username_svc !== '') {
        try { @$ManagePanel->RemoveUser($mafu_panel_name, $mafu_username_svc); } catch (\Throwable $_) {}
    }


    $mafu_bal = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $mafu_bal->bindValue(':delta', $mafu_amount, PDO::PARAM_INT);
    $mafu_bal->bindValue(':uid', $mafu_user_id, PDO::PARAM_STR);
    $mafu_bal->execute();

    sendmessage($mafu_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($mafu_amount) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $mafu_username_svc . "</code> حذف شد.", null, 'HTML');

    nm_adminInstantReply($from_id, "✅ مبلغ " . number_format($mafu_amount) . " تومان به کیف‌پول کاربر اضافه شد و سرویس حذف گردید.", $keyboardadmin ?? null, 'HTML');
    step("home", $from_id);

    if (!empty($setting['Channel_Report'])) {
        $mafu_rep = [
            'chat_id'    => $setting['Channel_Report'],
            'text'       => "⭕️ بازگشت وجه دستی (مینی‌اپ)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($mafu_amount) . " تومان\n👤 کاربر: <code>$mafu_user_id</code>\n📛 سرویس: <code>$mafu_username_svc</code>\n🆔 فاکتور: <code>$mafu_inv_id</code>",
            'parse_mode' => 'HTML',
        ];
        if (!empty($otherreport)) $mafu_rep['message_thread_id'] = $otherreport;
        telegram('sendmessage', $mafu_rep);
    }
} elseif ($datain == "settimecornremovevolume" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['setvolumeremove'] . $setting['cronvolumere'] . "روز", $backadmin, 'HTML');
    step("getcronvolumere", $from_id);
} elseif ($user['step'] == "getcronvolumere") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "cronvolumere", $text);
} elseif ($datain == "setting_on_holdcron" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "در این بخش باید تغیین کنید که اگر کاربر بعد از چند روز به کانفیگ خود وصل نشد و در وضعیت on_hold بود به کاربر پیام دهد" . $setting['on_hold_day'] . "روز", $backadmin, 'HTML');
    step("on_hold_day", $from_id);
} elseif ($user['step'] == "on_hold_day") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "on_hold_day", $text);
}