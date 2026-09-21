<?php

if (function_exists('nmResolvePanelNameForUser')) {
    // Some legacy handlers in this file (panel-name edit, URL edit, etc.)
    // expect $user['Processing_value'] to be a SCALAR panel name. This shim
    // extracts the panel name from a JSON-encoded state and writes it back
    // as a scalar — but doing that UNCONDITIONALLY destroys the multi-key
    // JSON state used by the discount/gift creation flows.
    //
    // Symptom: in step `getproductdiscount` the admin types "all", the
    // handler runs json_decode($user['Processing_value']) and gets NULL
    // (because this shim just overwrote the JSON with "/all"), every key
    // looks "missing", and the bot replies "اطلاعات ساخت کد تخفیف ناقص".
    //
    // Skip the shim while the user is mid-flow in one of those JSON-state
    // steps. Other steps keep their previous behavior.
    $jsonStateSteps = [
        'get_code','get_price_code','getlimitcodedis',
        'get_codesell','get_price_codesell','getlimitcode','gettypecodeagent',
        'gettimediscount','getfirstdiscount','getuseuser','getlocdiscount','getproductdiscount',
        'switchtype_pick','switchtype_link_panel','switchtype_username_panel',
        'switchtype_password_panel','switchtype_guard_api_key','switchtype_remna_token',
        'switchtype_xui_api_mode','switchtype_xui_token','switchtype_confirm',
    ];
    if (!in_array($user['step'] ?? '', $jsonStateSteps, true)) {
        $rxResolvedPanelName = nmResolvePanelNameForUser($user);
        if ($rxResolvedPanelName !== '') {
            $rawProcessing = (string)($user['Processing_value'] ?? '');
            $rxFlatten = ($rawProcessing === '');
            if (!$rxFlatten && ($rawProcessing[0] === '{' || $rawProcessing[0] === '[')) {
                // Only flatten a JSON state that is a BARE panel reference. If it
                // carries ANY other key it is an in-progress multi-step flow state
                // (custom volume/time/price, add-config, etc.) whose JSON must
                // survive — otherwise the next step json_decode()s it, gets the
                // scalar panel name back, and reports "اطلاعات مرحله قبلی ناقص".
                $rxDecodedState = json_decode($rawProcessing, true);
                $rxFlatten = true;
                if (is_array($rxDecodedState)) {
                    foreach ($rxDecodedState as $rxK => $rxV) {
                        if (!in_array($rxK, ['namepanel', 'name_panel', 'panel', 'panel_name'], true)) {
                            $rxFlatten = false;
                            break;
                        }
                    }
                }
                unset($rxDecodedState, $rxK, $rxV);
            }
            if ($rxFlatten) {
                $user['Processing_value'] = $rxResolvedPanelName;
            }
            unset($rxFlatten);
        }
        unset($rxResolvedPanelName, $rawProcessing);
    }
    unset($jsonStateSteps);
}
if (false) {
} elseif ($text == "✍️ نام پنل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['GetNameNew'], $backadmin, 'HTML');
    step('GetNameNew', $from_id);
} elseif ($user['step'] == "GetNameNew") {
    if (!isset($update['message']) && empty($text)) { return; }
    $marzban_list = is_array($marzban_list) ? $marzban_list : [];
    if (in_array($text, $marzban_list, true)) {
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
    step('PanelMenu', $from_id);
} elseif (($text == "⁉️ تست اتصال به پنل" || $text == "🔄 تست اتصال مجدد") && $adminrulecheck['rule'] == "administrator") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
        return;
    }
    require_once __DIR__ . '/../../../remnawave.php';
    $mgr = new RemnawaveManager($typepanel);
    $testRes = $mgr->testConnection($pdo);
    $configuredSquads = array_values(array_filter(array_map('trim', explode(',', (string) ($typepanel['remna_squad'] ?? '')))));
    $squadOk = count($configuredSquads) > 0 && count(array_filter($configuredSquads, 'remnawave_is_uuid')) === count($configuredSquads);
    if (!empty($testRes['ok'])) {
        $squadLine = $squadOk
            ? "🛡 Squad: ✅ " . count($configuredSquads) . " مورد انتخاب شده"
            : "🛡 Squad: ⚠️ تنظیم نشده یا نامعتبر";
        outtypepanel("remnawave", "✅ اتصال به پنل رمن‌ویو موفق بود.
" . $squadLine);
    } else {
        outtypepanel("remnawave", "❌ اتصال ناموفق: " . ($testRes['message'] ?? '') . "

جزئیات خطا در لاگ سرور با هدر [REMNAMWAVE-PANEL-TEST-FAIL] ثبت شد.");
    }
    step('PanelMenu', $from_id);
} elseif ($text == "🔑 تنظیم توکن API" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 توکن ثابت API پنل رمن‌ویو را ارسال کنید.
(از مسیر API Tokens در داشبورد ادمین رمن‌ویو بسازید)", $backadmin, 'HTML');
    step('get_remna_token', $from_id);
} elseif ($user['step'] == "get_remna_token" && $adminrulecheck['rule'] == "administrator") {
    update("marzban_panel", "remna_api_token", trim($text), "name_panel", $user['Processing_value']);
    outtypepanel("remnawave", "✅ توکن API با موفقیت ثبت شد.");
    step('PanelMenu', $from_id);
} elseif ($text == "🛡 تنظیم شناسه Squad" && $adminrulecheck['rule'] == "administrator") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
        nm_adminInstantReply($from_id, "❌ اطلاعات پنل در دسترس نیست.", $backadmin, 'HTML');
        return;
    }
    require_once __DIR__ . '/../../../remnawave.php';
    $mgr = new RemnawaveManager($typepanel);
    $squadsRes = $mgr->getInternalSquads($pdo);
    $squadsList = (!empty($squadsRes['ok']) && is_array($squadsRes['data']['internalSquads'] ?? null)) ? $squadsRes['data']['internalSquads'] : [];
    if (empty($squadsList)) {
        nm_adminInstantReply($from_id, "❌ دریافت لیست Squad از پنل ناموفق بود یا هیچ Squadی تعریف نشده است.", $backadmin, 'HTML');
        return;
    }
    $currentSquads = array_filter(array_map('trim', explode(',', (string) ($typepanel['remna_squad'] ?? ''))));
    $squadKeyboard = json_encode(['inline_keyboard' => remnawave_squad_keyboard_rows($squadsList, $currentSquads)], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "🛡 Squad های پیش‌فرض این پنل را انتخاب کنید (می‌توانید چند Squad انتخاب کنید).", $squadKeyboard, 'HTML');
} elseif (preg_match('/^remnatogglesquad#([0-9a-fA-F-]{36})$/', $datain, $remnaSquadMatch)) {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'اطلاعات پنل در دسترس نیست.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $toggledSquad = $remnaSquadMatch[1];
    $currentSquads = array_filter(array_map('trim', explode(',', (string) ($typepanel['remna_squad'] ?? ''))));
    if (in_array($toggledSquad, $currentSquads, true)) {
        $currentSquads = array_values(array_diff($currentSquads, [$toggledSquad]));
    } else {
        $currentSquads[] = $toggledSquad;
        $currentSquads = array_values($currentSquads);
    }
    update("marzban_panel", "remna_squad", implode(',', $currentSquads), "name_panel", $typepanel['name_panel']);
    require_once __DIR__ . '/../../../remnawave.php';
    $mgr = new RemnawaveManager($typepanel);
    $squadsRes = $mgr->getInternalSquads($pdo);
    $squadsList = (!empty($squadsRes['ok']) && is_array($squadsRes['data']['internalSquads'] ?? null)) ? $squadsRes['data']['internalSquads'] : [];
    $squadKeyboard = json_encode(['inline_keyboard' => remnawave_squad_keyboard_rows($squadsList, $currentSquads)], JSON_UNESCAPED_UNICODE);
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, "🛡 Squad های پیش‌فرض این پنل را انتخاب کنید (می‌توانید چند Squad انتخاب کنید).", $squadKeyboard, 'HTML');
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => count($currentSquads) > 0 ? 'Squad های پیش‌فرض به‌روزرسانی شد.' : 'هیچ Squadی انتخاب نشده — کاربران جدید ساخته نخواهند شد تا حداقل یک Squad انتخاب کنید.',
        'show_alert' => false,
        'cache_time' => 0,
    ]);
} elseif ($text == "🔄 تغییر لینک کاربر" && $adminrulecheck['rule'] == "administrator") {
    $panelRow = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $newRevoke = (is_array($panelRow) && ($panelRow['remna_revoke'] ?? 'off') === 'on') ? 'off' : 'on';
    update("marzban_panel", "remna_revoke", $newRevoke, "name_panel", $user['Processing_value']);
    $stateTxt = $newRevoke === 'on' ? '✅ فعال شد' : '❌ غیرفعال شد';
    outtypepanel("remnawave", "🔄 دکمهٔ «تغییر لینک» برای کاربران این پنل $stateTxt.
با لغو اشتراک کامل، لینک اشتراک و رمزهای اتصال هر دو بازتولید می‌شوند.");
    step('PanelMenu', $from_id);
} elseif ($text == "🔑 ریست رمز عبور کاربر" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 نام کاربری سرویس رمن‌ویو را ارسال کنید تا فقط رمزهای اتصال آن بازتولید شود (لینک اشتراک تغییر نمی‌کند).", $backadmin, 'HTML');
    step('get_remna_resetcreds', $from_id);
} elseif ($user['step'] == "get_remna_resetcreds" && $adminrulecheck['rule'] == "administrator") {
    try {
        $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
            remnawave_admin_log("ریست رمز عبور کاربر", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
            nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
            return;
        }
        $targetUser = trim($text);
        if ($targetUser === '') {
            nm_adminInstantReply($from_id, "❌ نام کاربری نامعتبر است.", $backadmin, 'HTML');
            return;
        }
        $resetRes = function_exists('remnawave_reset_credentials') ? remnawave_reset_credentials($typepanel['name_panel'], $targetUser) : ['status' => 'Unsuccessful'];
        if (($resetRes['status'] ?? '') === 'successful') {
            nm_adminInstantReply($from_id, "✅ رمزهای اتصال کاربر <code>" . htmlspecialchars($targetUser) . "</code> بازتولید شد. لینک اشتراک بدون تغییر است.", $backadmin, 'HTML');
        } else {
            remnawave_admin_log("ریست رمز عبور کاربر", $from_id, "reset_credentials", ($resetRes['msg'] ?? 'unsuccessful') . " | user=$targetUser");
            nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل: " . ($resetRes['msg'] ?? 'خطا'), $backadmin, 'HTML');
        }
    } catch (\Throwable $e) {
        remnawave_admin_log("ریست رمز عبور کاربر", $from_id, "exception", $e->getMessage());
        nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
    }
    step('remna_panel_back', $from_id);
} elseif ($text == "🖥 داشبورد نودها" && $adminrulecheck['rule'] == "administrator") {
    try {
        $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
            remnawave_admin_log("داشبورد نودها", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
            nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
            return;
        }
        $nodesRes = function_exists('remnawave_get_nodes') ? remnawave_get_nodes($typepanel['name_panel']) : ['status' => 'Unsuccessful', 'nodes' => []];
        if (($nodesRes['status'] ?? '') !== 'successful' || empty($nodesRes['nodes'])) {
            remnawave_admin_log("داشبورد نودها", $from_id, "get_nodes", "empty or unsuccessful response");
            nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل (دریافت لیست نودها ناموفق بود).", $backadmin, 'HTML');
            step('home', $from_id);
            return;
        }
        $txt = "🖥 داشبورد نودهای رمن‌ویو:\n";
        foreach ($nodesRes['nodes'] as $nd) {
            $light = !empty($nd['isDisabled']) ? '⛔️' : (!empty($nd['isConnected']) ? '🟢' : '🔴');
            $cc = $nd['countryCode'] !== '' ? " ({$nd['countryCode']})" : '';
            $txt .= "\n$light <b>" . htmlspecialchars($nd['name']) . "</b>$cc\n   👥 آنلاین: {$nd['usersOnline']} | ⚙️ Load: {$nd['loadAvg']} | 💻 RAM: {$nd['memPct']}%\n";
        }
        nm_adminInstantReply($from_id, $txt, $backadmin, 'HTML');
    } catch (\Throwable $e) {
        remnawave_admin_log("داشبورد نودها", $from_id, "exception", $e->getMessage());
        nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
    }
    step('remna_panel_back', $from_id);
} elseif ($text == "🔎 جستجو با آیدی تلگرام" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 آیدی عددی تلگرام کاربر را ارسال کنید.", $backadmin, 'HTML');
    step('get_remna_tglookup', $from_id);
} elseif ($user['step'] == "get_remna_tglookup" && $adminrulecheck['rule'] == "administrator") {
    try {
        $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
            remnawave_admin_log("جستجو با آیدی تلگرام", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
            nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
            return;
        }
        $tgId = trim($text);
        $look = function_exists('remnawave_user_by_tg') ? remnawave_user_by_tg($typepanel['name_panel'], $tgId) : ['status' => 'Unsuccessful', 'data' => null];
        if (($look['status'] ?? '') !== 'successful' || empty($look['data'])) {
            remnawave_admin_log("جستجو با آیدی تلگرام", $from_id, "by_telegram_id", "no user for tgId=$tgId");
            nm_adminInstantReply($from_id, "❌ کاربری با این آیدی تلگرام یافت نشد.", $backadmin, 'HTML');
            step('home', $from_id);
            return;
        }
        $data = $look['data'];
        $list = [];
        if (isset($data['users']) && is_array($data['users'])) {
            $list = $data['users'];
        } elseif (isset($data[0]) && is_array($data[0])) {
            $list = $data;
        } elseif (is_array($data) && !empty($data['uuid'])) {
            $list = [$data];
        }
        $txt = "🔎 نتایج برای تلگرام <code>" . htmlspecialchars($tgId) . "</code>:\n";
        foreach ($list as $u) {
            if (!is_array($u)) {
                continue;
            }
            $txt .= "\n👤 <code>" . htmlspecialchars((string)($u['username'] ?? '')) . "</code>\n   وضعیت: " . htmlspecialchars((string)($u['status'] ?? '')) . "\n   UUID: <code>" . htmlspecialchars((string)($u['uuid'] ?? '')) . "</code>\n";
        }
        nm_adminInstantReply($from_id, $txt, $backadmin, 'HTML');
    } catch (\Throwable $e) {
        remnawave_admin_log("جستجو با آیدی تلگرام", $from_id, "exception", $e->getMessage());
        nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
    }
    step('remna_panel_back', $from_id);
} elseif ($text == "🛡 انتساب Squad کاربر" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 با فرمت زیر بفرستید:\n<code>username,squad-uuid[,squad-uuid2]</code>", $backadmin, 'HTML');
    step('get_remna_setsquad', $from_id);
} elseif ($user['step'] == "get_remna_setsquad" && $adminrulecheck['rule'] == "administrator") {
    try {
        $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
            remnawave_admin_log("انتساب Squad کاربر", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
            nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
            return;
        }
        $parts = array_map('trim', explode(',', $text));
        $uname = array_shift($parts);
        $squads = array_values(array_filter($parts, function ($v) {
            return $v !== '';
        }));
        if ($uname === '' || count($squads) === 0) {
            nm_adminInstantReply($from_id, "❌ فرمت نادرست. نمونه: username,squad-uuid", $backadmin, 'HTML');
            return;
        }
        $sres = function_exists('remnawave_set_squads') ? remnawave_set_squads($typepanel['name_panel'], $uname, $squads) : ['status' => 'Unsuccessful'];
        if (($sres['status'] ?? '') !== 'successful') {
            remnawave_admin_log("انتساب Squad کاربر", $from_id, "set_squads", ($sres['msg'] ?? 'unsuccessful') . " | user=$uname");
        }
        nm_adminInstantReply($from_id, ($sres['status'] ?? '') === 'successful' ? "✅ Squad کاربر بروزرسانی شد." : "❌ خطا در ارتباط با پنل: " . ($sres['msg'] ?? 'خطا'), $backadmin, 'HTML');
    } catch (\Throwable $e) {
        remnawave_admin_log("انتساب Squad کاربر", $from_id, "exception", $e->getMessage());
        nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
    }
    step('remna_panel_back', $from_id);
} elseif ($text == "🎁 تمدید گروهی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 تعداد روز برای تمدید همهٔ کاربران فعال این پنل را ارسال کنید (به هر کاربر این تعداد روز اضافه می‌شود).", $backadmin, 'HTML');
    step('get_remna_bulkextend', $from_id);
} elseif ($user['step'] == "get_remna_bulkextend" && $adminrulecheck['rule'] == "administrator") {
    try {
        $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
            remnawave_admin_log("تمدید گروهی", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
            nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
            return;
        }
        $days = (int) trim($text);
        if ($days < 1) {
            nm_adminInstantReply($from_id, "❌ عدد روز نامعتبر است.", $backadmin, 'HTML');
            return;
        }
        $bres = function_exists('remnawave_bulk_extend') ? remnawave_bulk_extend($typepanel['name_panel'], $days) : ['status' => 'Unsuccessful', 'count' => 0];
        if (($bres['status'] ?? '') !== 'successful') {
            remnawave_admin_log("تمدید گروهی", $from_id, "bulk_extend", ($bres['msg'] ?? 'unsuccessful') . " | days=$days");
        }
        nm_adminInstantReply($from_id, ($bres['status'] ?? '') === 'successful' ? "✅ {$bres['count']} کاربر $days روز تمدید شدند." : "❌ خطا در ارتباط با پنل: " . ($bres['msg'] ?? 'خطا'), $backadmin, 'HTML');
    } catch (\Throwable $e) {
        remnawave_admin_log("تمدید گروهی", $from_id, "exception", $e->getMessage());
        nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
    }
    step('remna_panel_back', $from_id);
} elseif ($text == "🔗 ویرایش آدرس پنل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['geturlnew'], $backadmin, 'HTML');
    step('GeturlNew', $from_id);
} elseif ($user['step'] == "GeturlNew") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "url_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('PanelMenu', $from_id);
} elseif ($text == "📍 تغییر گروه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 نوع کاربری را ارسال کنید
گروه های کاربری : f,n,n2
❌ در صورتی که می خواهید پنل برای تمام گروه کاربری ها نمایش داده شود متن all را ارسال کنید", $backadmin, 'HTML');
    step('getagentpanel', $from_id);
} elseif ($user['step'] == "getagentpanel") {
    if (!isset($update['message']) && empty($text)) { return; }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "📌گروه کاربری با موفقیت تغییر کرد");
    update("marzban_panel", "agent", $text, "name_panel", $user['Processing_value']);
    step('PanelMenu', $from_id);
} elseif ($text == "🔗 دامنه لینک ساب" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 اگر پنل ثنایی هستید یک لینک ساب کاربر را از پنل کپی کرده سپس در این بخش ارسال کنید .بقیه پنل ها باید طبق ساختارش ارسال نمایید.", $backadmin, 'HTML');
    step('GeturlNewx', $from_id);
} elseif ($user['step'] == "GeturlNewx") {
    if (!isset($update['message']) && empty($text)) { return; }
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
    step('PanelMenu', $from_id);
} elseif ($text == "🔗 uuid admin" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 uuid ادمین را ارسال کنید", $backadmin, 'HTML');
    step('getuuidadmin', $from_id);
} elseif ($user['step'] == "getuuidadmin") {
    if (!isset($update['message']) && empty($text)) { return; }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "✅ uuid ادمین ذخیره گردید");
    update("marzban_panel", "secret_code", $text, "name_panel", $user['Processing_value']);
    step('PanelMenu', $from_id);
} elseif ($text == "🚨 محدودیت اکانت" && $adminrulecheck['rule'] == "administrator") {
    $rxLimitPanelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    $rxLimitMenu = function_exists('panel_limit_menu_render') ? panel_limit_menu_render($rxLimitPanelName) : null;
    if ($rxLimitMenu === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
} elseif ($datain == "panellimit_menu" && $adminrulecheck['rule'] == "administrator") {
    $rxLimitPanelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    $rxLimitMenu = function_exists('panel_limit_menu_render') ? panel_limit_menu_render($rxLimitPanelName) : null;
    if ($rxLimitMenu === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
} elseif ($datain == "panellimit_change" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['setlimit'], $backadmin, 'HTML');
    step('panellimit_getnew', $from_id);
} elseif ($user['step'] == "panellimit_getnew") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit((string)$text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['limitmenu_invalid'], $backadmin, 'HTML');
        return;
    }
    $rxLimitPanelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    $typepanel = select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select");
    panel_limit_set($typepanel, $text);
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['changedlimit']);
    step('PanelMenu', $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
    $rxLimitMenu = panel_limit_menu_render($typepanel);
    if ($rxLimitMenu !== null) {
        sendmessage($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
    }
} elseif ($datain == "panellimit_reset" && $adminrulecheck['rule'] == "administrator") {
    $rxLimitPanelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    $typepanel = select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
    panel_limit_reset($typepanel);
    $typepanel = select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
    $rxLimitMenu = panel_limit_menu_render($typepanel);
    if ($rxLimitMenu !== null) {
        nm_adminInstantReply($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
    }
} elseif ($datain == "panellimit_unlimited" && $adminrulecheck['rule'] == "administrator") {
    $rxLimitPanelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    $typepanel = select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
    panel_limit_set_unlimited($typepanel);
    $typepanel = select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
    $rxLimitMenu = panel_limit_menu_render($typepanel);
    if ($rxLimitMenu !== null) {
        nm_adminInstantReply($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
    }
} elseif ($datain == "panellimit_back" && $adminrulecheck['rule'] == "administrator") {
    step('PanelMenu', $from_id);
    $rxLimitPanelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    $typepanel = select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select");
    if (is_array($typepanel) && !empty($typepanel)) {
        outtypepanel($typepanel['type'], $textbotlang['Admin']['Back-menu']);
    }
} elseif ($text == "⏳ زمان سرویس تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🕰 مدت زمان سرویس تست را ارسال کنید.
⚠️ زمان بر حسب ساعت است.", $backadmin, 'HTML');
    step('updatetime', $from_id);
} elseif ($user['step'] == "updatetime") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidtime'] ?? '❌ زمان نامعتبر است', $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "time_usertest", $text, "name_panel", $user['Processing_value']);
    step('PanelMenu', $from_id);
} elseif ($text == "💾 حجم اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "حجم سرویس تست را ارسال کنید.
⚠️ حجم بر حسب مگابایت است.", $backadmin, 'HTML');
    step('val_usertest', $from_id);
} elseif ($user['step'] == "val_usertest") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "val_usertest", $text, "name_panel", $user['Processing_value']);
    step('PanelMenu', $from_id);
} elseif ($text == "💎 شناسه اینباند" && $adminrulecheck['rule'] == "administrator") {
    $typepanelInboundPrompt = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (is_array($typepanelInboundPrompt) && ($typepanelInboundPrompt['type'] ?? '') === 'x-ui_single' && xui_panel_uses_token($typepanelInboundPrompt)) {
        nm_adminInstantReply($from_id, "📌 نام کاربری (ایمیل) یک کلاینت نمونه که همین الان روی پنل ساخته شده را ارسال کنید تا ربات شناسه اینباند(های) همان کلاینت را خودکار تشخیص داده و ذخیره کند.", $backadmin, 'HTML');
    } else {
        nm_adminInstantReply($from_id, "📌 شناسه اینباندی که می خواهید کانفیگ ازآن ساخته شود راارسال نمایید.  شناسه اینباند یک عدد چند رقمی است که در پنل  در صفحه اینباند ها ستون id  نوشته شده است

⚠️ در صورتی که پنل wgdashboard هستید باید نام کانفیگ را ارسال نمایید", $backadmin, 'HTML');
    }
    step('getinboundiid', $from_id);
} elseif ($user['step'] == "getinboundiid") {
    if (!isset($update['message']) && empty($text)) { return; }
    $inboundInputRaw = trim($text);
    $typepanelInbound = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (is_array($typepanelInbound) && ($typepanelInbound['type'] ?? '') === 'x-ui_single' && xui_panel_uses_token($typepanelInbound)) {
        $sampleClient = xui_get_full_client($typepanelInbound, $inboundInputRaw);
        if (empty($sampleClient['status']) || empty($sampleClient['inboundIds'])) {
            nm_adminInstantReply($from_id, "❌ کلاینتی با این نام کاربری روی پنل پیدا نشد یا به هیچ اینباندی متصل نیست. نام کاربری صحیح یک کلاینت موجود روی پنل را ارسال کنید.", $backadmin, 'HTML');
            return;
        }
        $detectedIds = array_map('intval', $sampleClient['inboundIds']);
        $text = implode(',', $detectedIds);
        nm_adminInstantReply($from_id, "✅ شناسه اینباند(های) [{$text}] از روی کلاینت «{$inboundInputRaw}» تشخیص داده و ذخیره شد", $optionX_ui_single, 'HTML');
        update("marzban_panel", "inboundid", $text, "name_panel", $user['Processing_value']);
        step('PanelMenu', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, "✅ شناسه اینباند با موفقیت ذخیره گردید", $optionX_ui_single, 'HTML');
    update("marzban_panel", "inboundid", $text, "name_panel", $user['Processing_value']);
    step('PanelMenu', $from_id);
} elseif ($text == "🔐 ویرایش کلید" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (is_array($typepanel) && ($typepanel['type'] ?? null) == "rebecca") {
        nm_adminInstantReply($from_id, "🔑 لطفاً API Key جدید پنل Rebecca را ارسال کنید.", $backadmin, 'HTML');
        step('rebecca_edit_api_key', $from_id);
        return;
    }
    if (is_array($typepanel) && ($typepanel['type'] ?? null) == "pasarguard") {
        if (($typepanel['pasarguard_auth_mode'] ?? 'api_key') === 'password') {
            nm_adminInstantReply($from_id, "👤 لطفاً نام کاربری جدید پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
            step('pasarguard_edit_username', $from_id);
            return;
        }
        nm_adminInstantReply($from_id, "🔑 لطفاً API Key جدید پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
        step('pasarguard_edit_api_key', $from_id);
        return;
    }
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['guard']['request_api_key'], $backadmin, 'HTML');
    step('guard_edit_api_key', $from_id);
} elseif ($user['step'] == "pasarguard_edit_api_key") {
    if (!isset($update['message']) && empty($text)) { return; }
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "pasarguard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $baseUrl = rtrim((string) ($typepanel['url_panel'] ?? ''), '/');
    $currentKey = trim((string) ($typepanel['api_key'] ?? ''));
    if ($currentKey !== '' && hash_equals($currentKey, $apiKey)) {
        $sameKeyMessage = "ℹ️ کلید جدید با کلید قبلی یکسان است. تغییری انجام نشد.";
        $testResult = pasarguardTestConnection($baseUrl, $apiKey);
        if ($testResult['status'] === false) {
            $sameKeyMessage .= "\n❌ " . ($testResult['msg'] ?? 'اتصال ناموفق بود');
        } else {
            $sameKeyMessage .= "\n✅ اتصال برقرار است";
        }
        outtypepanel("pasarguard", $sameKeyMessage);
        step('PanelMenu', $from_id);
        return;
    }
    $testResult = pasarguardTestConnection($baseUrl, $apiKey);
    if ($testResult['status'] === false) {
        $errorMessage = $testResult['msg'] ?? 'اتصال ناموفق بود';
        outtypepanel("pasarguard", "❌ کلید جدید ذخیره نشد، اتصال به PasarGuard ناموفق بود:\n{$errorMessage}");
        step('PanelMenu', $from_id);
        return;
    }
    update("marzban_panel", "api_key", $apiKey, "name_panel", $typepanel['name_panel']);
    outtypepanel("pasarguard", "🔑 کلید PasarGuard با موفقیت ذخیره شد.\n✅ اتصال برقرار است");
    step('PanelMenu', $from_id);
} elseif ($user['step'] == "pasarguard_edit_username") {
    if (!isset($update['message']) && empty($text)) { return; }
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "pasarguard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $newUsername = trim((string) $text);
    if ($newUsername === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
        return;
    }
    $editState = json_encode(['panel' => $panelName, 'pasarguard_edit_username_value' => $newUsername], JSON_UNESCAPED_UNICODE);
    update("user", "Processing_value", $editState, "id", $from_id);
    nm_adminInstantReply($from_id, "🔑 لطفاً رمز عبور جدید پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
    step('pasarguard_edit_password', $from_id);
} elseif ($user['step'] == "pasarguard_edit_password") {
    if (!isset($update['message']) && empty($text)) { return; }
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "pasarguard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $newPassword = trim((string) $text);
    if ($newPassword === '') {
        nm_adminInstantReply($from_id, "🔑 لطفاً رمز عبور جدید پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
        return;
    }
    $pendingData = json_decode($user['Processing_value'], true);
    $newUsername = is_array($pendingData) ? (string) ($pendingData['pasarguard_edit_username_value'] ?? '') : '';
    if ($newUsername === '') {
        nm_adminInstantReply($from_id, "❌ اطلاعات ویرایش منقضی شده است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('PanelMenu', $from_id);
        return;
    }
    $baseUrl = rtrim((string) ($typepanel['url_panel'] ?? ''), '/');
    $testResult = pasarguardTestConnectionUserPass($baseUrl, $newUsername, $newPassword);
    if (empty($testResult['status'])) {
        $errorMessage = $testResult['msg'] ?? 'اتصال ناموفق بود';
        outtypepanel("pasarguard", "❌ اطلاعات جدید ذخیره نشد، اتصال به PasarGuard ناموفق بود:\n{$errorMessage}");
        step('PanelMenu', $from_id);
        return;
    }
    if (function_exists('pasarguardClearToken')) {
        pasarguardClearToken($baseUrl, (string) ($typepanel['username_panel'] ?? ''));
    }
    update("marzban_panel", "username_panel", $newUsername, "name_panel", $typepanel['name_panel']);
    update("marzban_panel", "password_panel", $newPassword, "name_panel", $typepanel['name_panel']);
    outtypepanel("pasarguard", "🔑 نام کاربری و رمز عبور PasarGuard با موفقیت ذخیره شد.\n✅ اتصال برقرار است");
    step('PanelMenu', $from_id);
} elseif ($user['step'] == "rebecca_edit_api_key") {
    if (!isset($update['message']) && empty($text)) { return; }
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "rebecca") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $baseUrl = rebeccaGetBaseUrl($typepanel['url_panel'] ?? null);
    $currentKey = trim((string) ($typepanel['api_key'] ?? ''));
    if ($currentKey !== '' && hash_equals($currentKey, $apiKey)) {
        $sameKeyMessage = "ℹ️ کلید جدید با کلید قبلی یکسان است. تغییری انجام نشد.";
        $testResult = rebeccaTestConnection($baseUrl, $apiKey);
        if ($testResult['status'] === false) {
            $sameKeyMessage .= "\n❌ " . ($testResult['msg'] ?? 'اتصال ناموفق بود');
        } else {
            $sameKeyMessage .= "\n✅ اتصال برقرار است";
        }
        outtypepanel("rebecca", $sameKeyMessage);
        step('PanelMenu', $from_id);
        return;
    }
    $testResult = rebeccaTestConnection($baseUrl, $apiKey);
    if ($testResult['status'] === false) {
        $errorMessage = $testResult['msg'] ?? 'اتصال ناموفق بود';
        outtypepanel("rebecca", "❌ کلید جدید ذخیره نشد، اتصال به Rebecca ناموفق بود:\n{$errorMessage}");
        step('PanelMenu', $from_id);
        return;
    }
    update("marzban_panel", "api_key", $apiKey, "name_panel", $typepanel['name_panel']);
    outtypepanel("rebecca", "🔑 کلید Rebecca با موفقیت ذخیره شد.\n✅ اتصال برقرار است");
    step('PanelMenu', $from_id);
} elseif ($user['step'] == "guard_edit_api_key") {
    if (!isset($update['message']) && empty($text)) { return; }
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
    $guardVersionForTest = ($typepanel['guard_version'] ?? 'v1') === 'v2' ? 'v2' : 'v1';
    if ($currentKey !== '' && hash_equals($currentKey, $apiKey)) {
        $sameKeyMessage = "ℹ️ کلید جدید با کلید قبلی یکسان است. تغییری انجام نشد.";
        $testResult = guardTestConnection($baseUrl, $apiKey, $guardVersionForTest);
        $statusText = guardFormatConnectionResult($testResult);
        if ($statusText !== '') {
            $sameKeyMessage .= "\n{$statusText}";
        }
        outtypepanel("guard", $sameKeyMessage);
        step('PanelMenu', $from_id);
        return;
    }
    update("marzban_panel", "api_key", $apiKey, "name_panel", $user['Processing_value']);
    update("marzban_panel", "password_panel", $apiKey, "name_panel", $user['Processing_value']);
    update("marzban_panel", "url_panel", $baseUrl, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    $connectionResult = guardTestConnection($baseUrl, $apiKey, $guardVersionForTest);
    $statusText = guardFormatConnectionResult($connectionResult);
    $savedMessage = $textbotlang['Admin']['managepanel']['guard']['api_key_saved'] ?? "🔑 کلید Guard ذخیره شد.";
    outtypepanel("guard", "{$savedMessage}\n{$statusText}");
    step('PanelMenu', $from_id);
} elseif ($text == "⁉️ اتصال به پنل" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (is_array($typepanel) && ($typepanel['type'] ?? null) == "pasarguard") {
        $pasarguardApiKey = trim((string) ($typepanel['api_key'] ?? ''));
        if ($pasarguardApiKey === '') {
            outtypepanel("pasarguard", "❌ کلید API برای این پنل تنظیم نشده است.");
            step('PanelMenu', $from_id);
            return;
        }
        $testResult = pasarguardTestConnection($typepanel['url_panel'] ?? null, $pasarguardApiKey);
        if ($testResult['status'] === false) {
            outtypepanel("pasarguard", "❌ اتصال ناموفق: " . ($testResult['msg'] ?? ''));
        } else {
            outtypepanel("pasarguard", "✅ اتصال به PasarGuard موفق بود.");
        }
        step('PanelMenu', $from_id);
        return;
    }
    if (is_array($typepanel) && ($typepanel['type'] ?? null) == "rebecca") {
        $rebeccaApiKey = trim((string) ($typepanel['api_key'] ?? ''));
        if ($rebeccaApiKey === '') {
            outtypepanel("rebecca", "❌ کلید API برای این پنل تنظیم نشده است.");
            step('PanelMenu', $from_id);
            return;
        }
        $testResult = rebeccaTestConnection($typepanel['url_panel'] ?? null, $rebeccaApiKey);
        if ($testResult['status'] === false) {
            outtypepanel("rebecca", "❌ اتصال ناموفق: " . ($testResult['msg'] ?? ''));
        } else {
            outtypepanel("rebecca", "✅ اتصال به Rebecca موفق بود.");
        }
        step('PanelMenu', $from_id);
        return;
    }
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['errorstateuspanel'], $backadmin, 'HTML');
        return;
    }
    $apiKey = !empty($typepanel['api_key']) ? $typepanel['api_key'] : ($typepanel['password_panel'] ?? '');
    if (trim($apiKey) === '') {
        outtypepanel("guard", $textbotlang['Admin']['managepanel']['guard']['connection_missing_key']);
        step('PanelMenu', $from_id);
        return;
    }
    $testResult = guardTestConnection($typepanel['url_panel'] ?? null, $apiKey, $typepanel['guard_version'] ?? 'v1');
    $statusText = guardFormatConnectionResult($testResult);
    outtypepanel("guard", $statusText);
    step('PanelMenu', $from_id);
} elseif ($text == "⚙️ سرویس پیش‌فرض" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "rebecca") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $servicesResponse = rebeccaGetServices($panel['name_panel']);
    if ($servicesResponse['status'] === false || empty($servicesResponse['services'])) {
        $errorMsg = $servicesResponse['msg'] ?? 'لیست سرویس‌ها خالی است.';
        outtypepanel("rebecca", "❌ دریافت سرویس‌ها از Rebecca ناموفق بود: {$errorMsg}");
        step('PanelMenu', $from_id);
        return;
    }
    $services = $servicesResponse['services'];
    $currentServiceId = trim((string) ($panel['rebecca_service_id'] ?? ''));
    $rebeccaServiceRows = [];
    foreach ($services as $serviceRow) {
        if (!isset($serviceRow['id'])) {
            continue;
        }
        $serviceId = (string) intval($serviceRow['id']);
        $isSelected = ($currentServiceId === $serviceId);
        $checkIcon = $isSelected ? '✅' : '❌';
        $rebeccaServiceRows[] = [[
            'text' => rebeccaServiceLabel($serviceRow) . " {$checkIcon}",
            'callback_data' => 'rebeccasetservice#' . $serviceId,
        ]];
    }
    $rebeccaServiceKeyboard = json_encode(['inline_keyboard' => $rebeccaServiceRows], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "⚙️ سرویس پیش‌فرض فعلی را انتخاب کنید. با هر بار ضربه زدن روی یک سرویس، همان سرویس به‌عنوان پیش‌فرض ذخیره می‌شود.", $rebeccaServiceKeyboard, 'HTML');
} elseif (preg_match('/^rebeccasetservice#(\d+)$/', $datain, $rebeccaServiceMatch)) {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "rebecca") {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'اطلاعات پنل در دسترس نیست.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $chosenServiceId = $rebeccaServiceMatch[1];
    update("marzban_panel", "rebecca_service_id", $chosenServiceId, "name_panel", $panel['name_panel']);
    $servicesResponse = rebeccaGetServices($panel['name_panel']);
    $services = ($servicesResponse['status'] !== false) ? $servicesResponse['services'] : [];
    $rebeccaServiceRows = [];
    foreach ($services as $serviceRow) {
        if (!isset($serviceRow['id'])) {
            continue;
        }
        $serviceId = (string) intval($serviceRow['id']);
        $isSelected = ($chosenServiceId === $serviceId);
        $checkIcon = $isSelected ? '✅' : '❌';
        $rebeccaServiceRows[] = [[
            'text' => rebeccaServiceLabel($serviceRow) . " {$checkIcon}",
            'callback_data' => 'rebeccasetservice#' . $serviceId,
        ]];
    }
    $rebeccaServiceKeyboard = json_encode(['inline_keyboard' => $rebeccaServiceRows], JSON_UNESCAPED_UNICODE);
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, "⚙️ سرویس پیش‌فرض فعلی را انتخاب کنید. با هر بار ضربه زدن روی یک سرویس، همان سرویس به‌عنوان پیش‌فرض ذخیره می‌شود.", $rebeccaServiceKeyboard, 'HTML');
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'سرویس پیش‌فرض ذخیره شد.',
        'show_alert' => false,
        'cache_time' => 0,
    ]);
} elseif ($text == "⚙️ تنظیم سرویس ها" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $servicesResponse = guardGetServices($panel['name_panel']);
    if ($servicesResponse['status'] === false || empty($servicesResponse['services'])) {
        $errorMsg = $servicesResponse['msg'] ?? 'لیست سرویس Guard خالی است.';
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        outtypepanel("guard", sprintf($failTextTemplate, $errorMsg));
        return;
    }
    $services = $servicesResponse['services'];
    $availableIds = guardExtractServiceIdsFromList($services);
    if (empty($availableIds)) {
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        outtypepanel("guard", sprintf($failTextTemplate, 'شناسه معتبر برای سرویس‌ها یافت نشد.'));
        return;
    }
    $storedIds = guardParseServiceIds($panel['guard_service_ids'] ?? null);
    $isAllStored = in_array('all', $storedIds, true) || in_array(0, $storedIds, true);
    $selected = $isAllStored ? $availableIds : array_values(array_intersect($availableIds, array_map('intval', $storedIds)));
    if (empty($selected)) {
        $selected = $availableIds;
    }
    $state = ['panel' => $panel['name_panel'], 'services' => $services, 'selected' => $selected];
    $sent = sendmessage($from_id, guardSvcRenderText($services, $selected), guardSvcRenderKeyboard($services, $selected), 'HTML');
    $state['message_id'] = $sent['result']['message_id'] ?? null;
    savedata("save", "guard_svc", $state);
    step('guard_svc_edit', $from_id);
} elseif ($text == "👤 ویرایش نام" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getusernamenew'], $backadmin, 'HTML');
    step('GetusernameNew', $from_id);
} elseif ($user['step'] == "GetusernameNew") {
    if (!isset($update['message']) && empty($text)) { return; }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedusernamePanel']);
    update("marzban_panel", "username_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('PanelMenu', $from_id);
} elseif ($text == "⚙️ تنظیم پروتکل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocolx_ui', $from_id);
} elseif ($user['step'] == "getprotocolx_ui") {
    if (!isset($update['message']) && empty($text)) { return; }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['setprotocol']);
    $marzbanprotocol = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    update("x_ui", "protocol", $text, "codepanel", $marzbanprotocol['code_panel']);
    step('PanelMenu', $from_id);
} elseif ($text == "🔄 تغییر حالت API" && $adminrulecheck['rule'] == "administrator") {
    $rxXuiModePanelRow = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!is_array($rxXuiModePanelRow) || ($rxXuiModePanelRow['type'] ?? '') !== 'x-ui_single') {
        step('PanelMenu', $from_id);
    } else {
        $xuiModeEditKb = json_encode([
            'inline_keyboard' => [
                [['text' => $textbotlang['Admin']['managepanel']['xuimodelegacy'], 'callback_data' => 'editxuimode#legacy']],
                [['text' => $textbotlang['Admin']['managepanel']['xuimodetoken'], 'callback_data' => 'editxuimode#token']],
            ],
        ]);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuimode'], $xuiModeEditKb, 'HTML');
        step('edit_xui_api_mode', $from_id);
    }
} elseif ($user['step'] == "edit_xui_api_mode" && preg_match('/editxuimode#(legacy|token)/', $datain, $dataget)) {
    $xuiEditMode = $dataget[1];
    if ($xuiEditMode === 'token') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuiapitoken'], $backadmin, 'HTML');
        step('edit_xui_api_token', $from_id);
    } else {
        update("marzban_panel", "xui_api_mode", "legacy", "name_panel", $user['Processing_value']);
        outtypepanel('x-ui_single', $textbotlang['Admin']['managepanel']['saveddata']);
    }
} elseif ($user['step'] == "edit_xui_api_token") {
    if (!isset($update['message']) && empty($text)) { return; }
    $xuiEditToken = trim($text);
    if ($xuiEditToken === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidxuiapitoken'], $backadmin, 'HTML');
        return;
    }
    update("marzban_panel", "xui_api_token", $xuiEditToken, "name_panel", $user['Processing_value']);
    update("marzban_panel", "xui_api_mode", "token", "name_panel", $user['Processing_value']);
    outtypepanel('x-ui_single', $textbotlang['Admin']['managepanel']['saveddata']);
} elseif ($text == "🔐 ویرایش رمز عبور" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpasswordnew'], $backadmin, 'HTML');
    step('GetpaawordNew', $from_id);
} elseif ($user['step'] == "GetpaawordNew") {
    if (!isset($update['message']) && empty($text)) { return; }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedpasswordPanel']);
    update("marzban_panel", "password_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('PanelMenu', $from_id);
} elseif ($text == "❌ حذف پنل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "در صورت تایید کلمه زیر را ارسال کنید.
<code>تایید</code>", $backadmin, 'HTML');
    step('confirmremovepanel', $from_id);
} elseif ($user['step'] == "confirmremovepanel") {
    if (!isset($update['message']) && empty($text)) { return; }
    if ($text == "تایید") {
        $rxPanelsHubKb = isset($adminPanelsMenu) ? $adminPanelsMenu : $keyboardadmin;
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['RemovedPanel'], $rxPanelsHubKb, 'HTML');
        $marzban = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        $stmt = $pdo->prepare("DELETE FROM marzban_panel WHERE name_panel = :name_panel");
        $stmt->bindParam(':name_panel', $user['Processing_value'], PDO::PARAM_STR);
        $stmt->execute();
        update("user", "Processing_value", "0", "id", $from_id);
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managruser'] || $datain == "backlistuser") {
    $keyboardtypelistuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "کاربران دارای موجودی", 'callback_data' => "balanceuserlist"],
            ],
            [
                ['text' => "کاربران دارای زیرمجموعه", 'callback_data' => "listrefral"],
            ],
            [
                ['text' => "کاربران شماره کارت فعال", 'callback_data' => "cartuserlist"],
            ],
            [
                ['text' => "کاربران با موجودی منفی", 'callback_data' => "zerobalance"],
            ],
            [
                ['text' => "کاربران دارای اشتراک فعال", 'callback_data' => "activesubuserlist"],
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
            ],
            [
                ['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => "admin_usershub"],
                ['text' => "🏠 منوی مدیریت", 'callback_data' => "adm_hub_main"],
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuser', 'previous_pageuser', true, true);
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuser', 'previous_pageuser', true, true);
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuser', 'previous_pageuser', true, true);
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, "next_pageuseragent_$typeagent", null, true);
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, "next_pageuseragent_$typeagent", "previous_pageuseragent_$typeagent", true);
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, "next_pageuseragent_$typeagent", "previous_pageuseragent_$typeagent", true);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "balanceuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuserbalance', 'previous_pageuserbalance', true);
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuserbalance', 'previous_pageuserbalance', true);
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuserbalance', 'previous_pageuserbalance', true);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "activesubuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT DISTINCT user.* FROM user INNER JOIN invoice ON invoice.id_user = user.id WHERE invoice.Status IN ('active','sendedwarn','send_on_hold') LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuseractivesub', 'previous_pageuseractivesub', true);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuseractivesub') {
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
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT DISTINCT user.* FROM user INNER JOIN invoice ON invoice.id_user = user.id WHERE invoice.Status IN ('active','sendedwarn','send_on_hold') LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuseractivesub', 'previous_pageuseractivesub', true);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuseractivesub') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT DISTINCT user.* FROM user INNER JOIN invoice ON invoice.id_user = user.id WHERE invoice.Status IN ('active','sendedwarn','send_on_hold') LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuseractivesub', 'previous_pageuseractivesub', true);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "listrefral") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuserrefral', 'previous_pageuserrefral', true);
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuserrefral', 'previous_pageuserrefral', true);
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
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $keyboard_json = buildUserListKeyboard($rows, $textbotlang, 'next_pageuserrefral', 'previous_pageuserrefral', true);
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
    if (!isset($update['message']) && empty($text)) { return; }
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
<blockquote>نام کاربری :@$username</blockquote>
<blockquote>آیدی عددی : $from_id</blockquote>
👤 اطلاعات کاربر دریافت کننده موجودی :
<blockquote>آیدی عددی کاربر  : {$user['Processing_value']}</blockquote>
<blockquote>مبلغ موجودی : $pricadd</blockquote>
<blockquote>موجودی کاربر پس از افزایش : $Balance_user_after</blockquote>";
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
<blockquote>نام کاربری :@$username</blockquote>
<blockquote>آیدی عددی : $from_id</blockquote>
👤 اطلاعات کاربر  :
<blockquote>آیدی عددی کاربر  : {$user['Processing_value']}</blockquote>
<blockquote>مبلغ موجودی : $text</blockquote>
<blockquote>موجودی کاربر پس از کم کردن : $Balance_user_afters</blockquote>";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/banuserlist_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "block") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockedUser'], null, 'HTML');
        return;
    }
    update("user", "Processing_value_four", "", "id", $from_id);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید", 'callback_data' => 'acceptblock_' . $iduser],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "در صورت تایید روی دکمه تایید کلیک کنید", $Response, 'HTML');
} elseif (preg_match('/blockuserfake_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $_blockfake_chat_id = $update['callback_query']['message']['chat']['id'] ?? $from_id;
    $_blockfake_thread_id = (int) ($update['callback_query']['message']['message_thread_id'] ?? 0);
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "block") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockedUser'], null, 'HTML');
        return;
    }
    update("user", "Processing_value_four", $_blockfake_chat_id . ':' . $message_id . ':' . $_blockfake_thread_id, "id", $from_id);
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
    $_blockfake_origin = explode(':', (string) ($user['Processing_value_four'] ?? ''), 3);
    $_blockfake_chat_id = $_blockfake_origin[0] ?? '';
    $_blockfake_message_id = $_blockfake_origin[1] ?? '';
    $_blockfake_thread_id = (int) ($_blockfake_origin[2] ?? 0);
    if ($_blockfake_chat_id !== '' && $_blockfake_message_id !== '') {
        $_blockfake_confirmed_text = "🚫 کاربر مسدود شد.

<blockquote>آیدی عددی کاربر : {$user['Processing_value']}</blockquote>
<blockquote>دلیل مسدودی : $text</blockquote>
<blockquote>👤آیدی عددی ادمین مسدودکننده : $from_id</blockquote>
<blockquote>نام کاربری ادمین مسدودکننده : @$username</blockquote>";
        Editmessagetext($_blockfake_chat_id, $_blockfake_message_id, $_blockfake_confirmed_text, null, 'HTML', $_blockfake_thread_id > 0 ? $_blockfake_thread_id : null);
        update("user", "Processing_value_four", "", "id", $from_id);
    }
    step('home', $from_id);

} elseif (preg_match('/acceptblock_(\w+)/', $datain, $dataget)) {

    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    update("user", "User_Status", "block", "id", $iduser);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockUser'], $backadmin, 'HTML');
    step('adddecriptionblock', $from_id);
    $textblok = "کاربر با آیدی عددی
$iduser  در ربات مسدود گردید
<blockquote>ادمین مسدود کننده : $from_id</blockquote>";
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

} elseif (preg_match('/^cardverifyoff_(\d+)$/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "card_verify_bypass", "1", "id", $iduser);
    nm_adminInstantReply($from_id, "✅ احراز کارت برای این کاربر حذف شد. کاربر بدون تایید کارت پرداخت می‌کند.", null, 'HTML');

} elseif (preg_match('/^cardverifyon_(\d+)$/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "card_verify_bypass", "0", "id", $iduser);
    nm_adminInstantReply($from_id, "✅ احراز کارت برای این کاربر فعال شد. کاربر باید تایید کارت را طی کند.", null, 'HTML');

} elseif (preg_match('/^usercardmenu_(\d+)$/', $datain, $dataget)) {
    $iduser = (int)$dataget[1];
    $allCards = [];
    $_ac_res = mysqli_query($connect, "SELECT id, cardnumber, namecard FROM card_number WHERE is_active = 1 ORDER BY created_at ASC LIMIT 50");
    if ($_ac_res) {
        while ($_ac_row = mysqli_fetch_assoc($_ac_res)) {
            $allCards[] = $_ac_row;
        }
    }
    if (empty($allCards)) {
        nm_adminInstantReply($from_id, "❌ هیچ کارت فعالی در سیستم ثبت نشده است.", null, 'HTML');
    } else {
        $blockedIds = [];
        $_bl_res = mysqli_query($connect, "SELECT card_id FROM user_card_block WHERE user_id = {$iduser}");
        if ($_bl_res) {
            while ($_bl_row = mysqli_fetch_assoc($_bl_res)) {
                $blockedIds[] = (int)$_bl_row['card_id'];
            }
        }
        $_uck_rows = [];
        foreach ($allCards as $_uc_card) {
            $_uc_cid = (int)$_uc_card['id'];
            $_uc_last4 = substr((string)$_uc_card['cardnumber'], -4);
            $_uc_blocked = in_array($_uc_cid, $blockedIds, true);
            $_uc_status = $_uc_blocked ? "❌ پنهان" : "✅ نمایان";
            $_uck_rows[] = [[
                'text' => "{$_uc_status} | *{$_uc_last4} — {$_uc_card['namecard']}",
                'callback_data' => "toggleusercard_{$_uc_cid}_{$iduser}",
            ]];
        }
        $_uck_rows[] = [['text' => "🔙 بازگشت", 'callback_data' => "manageuser_{$iduser}"]];
        $ucMenu = json_encode(['inline_keyboard' => $_uck_rows], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, "💳 مدیریت کارت‌های کاربر #{$iduser}\n\nروی هر کارت کلیک کنید تا وضعیت نمایش آن برای این کاربر تغییر کند:", $ucMenu, 'HTML');
    }

} elseif (preg_match('/^toggleusercard_(\d+)_(\d+)$/', $datain, $dataget)) {
    $cardId = (int)$dataget[1];
    $iduser = (int)$dataget[2];
    $_tb_res = mysqli_query($connect, "SELECT id FROM user_card_block WHERE user_id = {$iduser} AND card_id = {$cardId} LIMIT 1");
    if ($_tb_res && mysqli_num_rows($_tb_res) > 0) {
        mysqli_query($connect, "DELETE FROM user_card_block WHERE user_id = {$iduser} AND card_id = {$cardId}");
        $_toggle_notice = "✅ کارت نمایان شد";
    } else {
        mysqli_query($connect, "INSERT IGNORE INTO user_card_block (user_id, card_id) VALUES ({$iduser}, {$cardId})");
        $_toggle_notice = "❌ کارت پنهان شد";
    }
    $allCards2 = [];
    $_ac2_res = mysqli_query($connect, "SELECT id, cardnumber, namecard FROM card_number WHERE is_active = 1 ORDER BY created_at ASC LIMIT 50");
    if ($_ac2_res) {
        while ($_ac2_row = mysqli_fetch_assoc($_ac2_res)) {
            $allCards2[] = $_ac2_row;
        }
    }
    $blockedIds2 = [];
    $_bl2_res = mysqli_query($connect, "SELECT card_id FROM user_card_block WHERE user_id = {$iduser}");
    if ($_bl2_res) {
        while ($_bl2_row = mysqli_fetch_assoc($_bl2_res)) {
            $blockedIds2[] = (int)$_bl2_row['card_id'];
        }
    }
    $_uck2_rows = [];
    foreach ($allCards2 as $_uc2_card) {
        $_uc2_cid = (int)$_uc2_card['id'];
        $_uc2_last4 = substr((string)$_uc2_card['cardnumber'], -4);
        $_uc2_blocked = in_array($_uc2_cid, $blockedIds2, true);
        $_uc2_status = $_uc2_blocked ? "❌ پنهان" : "✅ نمایان";
        $_uck2_rows[] = [[
            'text' => "{$_uc2_status} | *{$_uc2_last4} — {$_uc2_card['namecard']}",
            'callback_data' => "toggleusercard_{$_uc2_cid}_{$iduser}",
        ]];
    }
    $_uck2_rows[] = [['text' => "🔙 بازگشت", 'callback_data' => "manageuser_{$iduser}"]];
    $ucMenu2 = json_encode(['inline_keyboard' => $_uck2_rows], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "💳 مدیریت کارت‌های کاربر #{$iduser} — {$_toggle_notice}\n\nروی هر کارت کلیک کنید تا وضعیت نمایش آن برای این کاربر تغییر کند:", $ucMenu2, 'HTML');

} elseif (preg_match('/unbanuserr_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "Active") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['UserNotBlock'], null, 'HTML');
        return;
    }
    $textblok = "کاربر با آیدی عددی
$iduser  در ربات  رفع مسدود گردید
<blockquote>ادمین مسدود کننده : $from_id</blockquote>";
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
💰 مبلغ پرداختی : " . rxFormatToman($paymentUser['price']) . " تومان
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
    $textadd = "💎 کاربر عزیز مبلغ " . rxFormatToman($info_product['price_product']) . " تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($info_product['id_user'], $textadd, null, 'HTML');
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif ($text == "🎁 ساخت کد تخفیف" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
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
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['agentcode'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypecodeagent', $from_id);
} elseif ($user['step'] == "gettypecodeagent") {
    $agentst = ["n", "n2", "f", "allusers"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], rx_agentGroupKeyboard(true), 'HTML');
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
        $panel = ['code_panel' => "/all", 'name_panel' => '/all'];
    } else {
        $panel = function_exists('rx_resolvePanelFromInput') ? rx_resolvePanelFromInput($text, $pdo) : select("marzban_panel", "*", "name_panel", $text, "select");
    }
    if ($panel == false || empty($panel))
        return;
    savedata("save", "code_panel", $panel['code_panel']);
    savedata("save", "name_panel", $panel['name_panel']);
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
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
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
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionMarzban, 'HTML');
    step('home', $from_id);
    return;
} elseif ($text == "🧮 تنظیم درصد زیرمجموعه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
    step('setpercentage', $from_id);
} elseif ($user['step'] == "setpercentage") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text) || (int) $text < 0 || (int) $text > 100) {
        nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_percentage'] ?? "❌ درصد نامعتبر است. لطفاً عددی بین 0 تا 100 وارد کنید.", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['changedpercentage'], $affiliates, 'HTML');
    update("setting", "affiliatespercentage", $text);
    step('featnav_affiliates', $from_id);
} elseif (($text == "🏞 تنظیم بنر زیرمجموعه گیری" || $text == "🏞 تنظیم عکس بنر" || $datain == "affiliates_edit_banner_image") && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_image_prompt'] ?? "🏞 عکس جدید بنر زیرمجموعه‌گیری را ارسال کنید.\n\nپس از دریافت عکس، متن بنر را نیز از شما می‌پرسیم.", $backadmin, 'HTML');
    step('setbannerimage', $from_id);
} elseif ($user['step'] == "setbannerimage") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!$photo) {
        nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['invalidbanner'], $backadmin, 'HTML');
        return;
    }
    update("affiliates", "id_media", $photoid);
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_text_prompt'] ?? "📝 اکنون متن بنر زیرمجموعه‌گیری را ارسال کنید.\n\nمتغیرهای قابل استفاده:\n<code>{link}</code> لینک اختصاصی دعوت\n<code>{name}</code> نام کاربر\n<code>{code}</code> کد دعوت کاربر", $backadmin, 'HTML');
    step('setbannertext', $from_id);
} elseif (($text == "✏️ ویرایش متن بنر" || $datain == "affiliates_edit_banner_text") && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_text_prompt'] ?? "📝 متن جدید بنر زیرمجموعه‌گیری را ارسال کنید.\n\nمتغیرهای قابل استفاده:\n<code>{link}</code> لینک اختصاصی دعوت\n<code>{name}</code> نام کاربر\n<code>{code}</code> کد دعوت کاربر", $backadmin, 'HTML');
    step('setbannertext', $from_id);
} elseif ($user['step'] == "setbannertext") {
    if (!isset($update['message']) && empty($text)) { return; }
    update("affiliates", "banner_text", $text);
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_text_saved'] ?? "✅ متن بنر با موفقیت ذخیره شد.", $affiliates, 'HTML');
    step('featnav_affiliates', $from_id);
} elseif (($text == "🗑 حذف بنر" || $datain == "affiliates_delete_banner") && $adminrulecheck['rule'] == "administrator") {
    update("affiliates", "id_media", "none");
    update("affiliates", "description", "none");
    update("affiliates", "banner_text", null);
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_deleted'] ?? "✅ بنر به‌طور کامل حذف شد (عکس و متن).", $affiliates, 'HTML');
    step('featnav_affiliates', $from_id);
} elseif (($text == "♻️ بازگردانی بنر پیش‌فرض" || $datain == "affiliates_reset_banner") && $adminrulecheck['rule'] == "administrator") {
    update("affiliates", "id_media", "none");
    update("affiliates", "description", "none");
    update("affiliates", "banner_text", null);
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_reset'] ?? "✅ بنر به حالت پیش‌فرض بازگردانی شد.", $affiliates, 'HTML');
    step('featnav_affiliates', $from_id);
} elseif (($text == "🖼 مشاهده بنر فعال" || $datain == "affiliates_view_banner") && $adminrulecheck['rule'] == "administrator") {
    $rxBannerPreview = select("affiliates", "*", null, null, "select");
    $rxBannerPreviewText = faoxima_render_text(
        !empty($rxBannerPreview['banner_text']) ? $rxBannerPreview['banner_text'] : ($rxBannerPreview['description'] ?? ''),
        [
            'link' => "https://t.me/$usernamebot?start=$from_id",
            'name' => $user['first_name'] ?? $username,
            'code' => $user['codeInvitation'] ?? $from_id,
        ]
    );
    if (!empty($rxBannerPreview['id_media']) && strlen($rxBannerPreview['id_media']) >= 5 && $rxBannerPreview['id_media'] !== 'none') {
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => $rxBannerPreview['id_media'],
            'caption' => $rxBannerPreviewText !== '' ? $rxBannerPreviewText : ($datatextbot['dyn_affiliates_extra_banner_empty'] ?? "بنری تنظیم نشده است."),
            'parse_mode' => 'HTML',
        ]);
    } else {
        nm_adminInstantReply($from_id, $rxBannerPreviewText !== '' ? $rxBannerPreviewText : ($datatextbot['dyn_affiliates_extra_banner_empty'] ?? "بنری تنظیم نشده است."), $affiliates, 'HTML');
    }
    step('featnav_affiliates', $from_id);
} elseif ($text == "👤 آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "CartDirect");
    $textcart = "📌 نام کاربری خود را بدون @ برای دریافت شماره کارت ارسال کنید\n\n{$PaySetting['ValuePay']}";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('CartDirect', $from_id);
} elseif ($user['step'] == "CartDirect") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $CartManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "CartDirect");
    step('home', $from_id);
} elseif ($text == "💳 آفلاین در پیوی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "Cartstatuspv")['ValuePay'];
    $card_Statuspv = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $PaySetting, 'callback_data' => $PaySetting],
            ],
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['cardTitlepv'], $card_Statuspv, 'HTML');
} elseif ($datain == "oncardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "offcardpv", "NamePay", "Cartstatuspv");
    $card_Statuspv = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "offcardpv", 'callback_data' => "offcardpv"],
            ],
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffpv'], $card_Statuspv);
} elseif ($datain == "offcardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "oncardpv", "NamePay", "Cartstatuspv");
    $card_Statuspv = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "oncardpv", 'callback_data' => "oncardpv"],
            ],
            [
                ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonpv'], $card_Statuspv);
} elseif (preg_match('/addbalamceuser_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    $_addbal_chat_id = !empty($Payment_report['report_chat_id']) ? $Payment_report['report_chat_id'] : ($update['callback_query']['message']['chat']['id'] ?? $from_id);
    $_addbal_msg_id = !empty($Payment_report['report_message_id']) ? (int) $Payment_report['report_message_id'] : (int) $message_id;
    $_addbal_thread_id = !empty($Payment_report['report_thread_id']) ? (int) $Payment_report['report_thread_id'] : (int) ($update['callback_query']['message']['message_thread_id'] ?? 0);
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
    $rxClaimAddBal = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = :o AND payment_Status <> 'paid'");
    $rxClaimAddBal->execute([':o' => $id_order]);
    if ($rxClaimAddBal->rowCount() < 1) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');

    update("user", "Processing_value_four", $_addbal_chat_id . ':' . $_addbal_msg_id . ':' . $_addbal_thread_id, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['addbalanceuserdec'], $backadmin, 'html');
    step('addbalancemanual', $from_id);
} elseif ($user['step'] == "addbalancemanual") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['AddBalanceUser'], $keyboardadmin, 'HTML');
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value'], "select");
    $Balance_user = select("user", "*", "id", $Payment_report['id_user'], "select");
    $balanceusers = number_format($text, 0);
    if (function_exists('balance_atomic_credit')) {
        balance_atomic_credit($Payment_report['id_user'], (float) $text);
    } else {
        $Balance_add_user = $Balance_user['Balance'] + $text;
        update("user", "Balance", $Balance_add_user, "id", $Payment_report['id_user']);
    }
    if (function_exists('wallet_ledger_record')) {
        wallet_ledger_record($Payment_report['id_user'], 'credit', $text, 'topup_card', 'تایید رسید کارت به کارت توسط ادمین', $Payment_report['id_order']);
    }
    if (function_exists('update')) {
        update('Payment_report', 'at_updated', date('Y/m/d H:i:s'), 'id_order', $Payment_report['id_order']);
    }
    $textadd = "💎 کاربر عزیز مبلغ $balanceusers تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($Payment_report['id_user'], $textadd, null, 'HTML');
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('wallet_deposit', [
            'user_id' => $Payment_report['id_user'],
            'amount'  => $balanceusers,
            'price'   => $balanceusers,
        ], $setting);
    }
    $text_report = "✅ رسید کارت به کارت با افزایش دستی موجودی تایید شد.

<blockquote>آیدی عددی کاربر : {$Payment_report['id_user']}</blockquote>
<blockquote>نام کاربری کاربر : {$Balance_user['username']}</blockquote>
<blockquote>مبلغ تراکنش در فاکتور :  " . rxFormatToman($Payment_report['price']) . "</blockquote>
<blockquote>مبلغ تراکنش واریزی توسط ادمین : $text</blockquote>
<blockquote>👤آیدی عددی ادمین تایید کننده : $from_id</blockquote>
<blockquote>نام کاربری ادمین تایید کننده : @$username</blockquote>";
    $_addbal_origin = explode(':', (string) ($user['Processing_value_four'] ?? ''), 3);
    $_addbal_chat_id = $_addbal_origin[0] ?? '';
    $_addbal_message_id = $_addbal_origin[1] ?? '';
    $_addbal_thread_id = (int) ($_addbal_origin[2] ?? 0);
    if ($_addbal_chat_id === '' || $_addbal_message_id === '') {
        $_addbal_chat_id = (string) ($Payment_report['report_chat_id'] ?? '');
        $_addbal_message_id = (string) ($Payment_report['report_message_id'] ?? '');
        $_addbal_thread_id = (int) ($Payment_report['report_thread_id'] ?? 0);
    }
    if ($_addbal_chat_id !== '' && $_addbal_message_id !== '') {
        Editmessagetext($_addbal_chat_id, $_addbal_message_id, $text_report, null, 'HTML', $_addbal_thread_id > 0 ? $_addbal_thread_id : null);
        update("user", "Processing_value_four", "", "id", $from_id);
    } elseif (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    step('home', $from_id);
} elseif ($text == "🎁 پورسانت بعد از خرید" && $adminrulecheck['rule'] == "administrator") {
    step('featnav_affiliates', $from_id);
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['commission'], $keyboardcommission, 'HTML');
} elseif ($datain == "oncommission") {
    update("affiliates", "status_commission", "offcommission");
    step('featnav_affiliates', $from_id);
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatusOff'], $keyboardcommission);
} elseif ($datain == "offcommission") {
    update("affiliates", "status_commission", "oncommission");
    step('featnav_affiliates', $from_id);
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatuson'], $keyboardcommission);
} elseif ($text == "🎁 هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
    step('featnav_affiliates', $from_id);
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['Discountaffiliates'], $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "onDiscountaffiliates") {
    update("affiliates", "Discount", "offDiscountaffiliates");
    step('featnav_affiliates', $from_id);
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatusOff'], $keyboardDiscountaffiliates);
} elseif ($datain == "offDiscountaffiliates") {
    update("affiliates", "Discount", "onDiscountaffiliates");
    step('featnav_affiliates', $from_id);
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatuson'], $keyboardDiscountaffiliates);
} elseif ($text == "🌟 مبلغ هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
    step('getdiscont', $from_id);
} elseif ($user['step'] == "getdiscont") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text) || (int) $text <= 0) {
        nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_amount'] ?? "❌ مبلغ نامعتبر است. لطفاً یک عدد مثبت وارد کنید.", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['changedpriceDiscount'], $affiliates, 'HTML');
    update("affiliates", "price_Discount", $text);
    step('featnav_affiliates', $from_id);
} elseif ($datain == "affiliates_set_percentage" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
    step('setpercentage', $from_id);
} elseif ($datain == "affiliates_set_start_amount" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
    step('getdiscont', $from_id);
} elseif ($datain == "affiliates_toggle_start_gift" && $adminrulecheck['rule'] == "administrator") {
    step('featnav_affiliates', $from_id);
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['Discountaffiliates'], $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "affiliates_toggle_commission" && $adminrulecheck['rule'] == "administrator") {
    step('featnav_affiliates', $from_id);
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['commission'], $keyboardcommission, 'HTML');
} elseif ($datain == "affiliates_toggle_first_buy_only" && $adminrulecheck['rule'] == "administrator") {
    step('featnav_affiliates', $from_id);
    $rxOneBuy = select("affiliates", "*", null, null, "select");
    $rxOneBuyLabel = ($rxOneBuy['porsant_one_buy'] ?? '') === 'on_buy_porsant' ? "✅ فقط خرید اول" : "❌ همه خریدها";
    $keyboardOneBuy = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $rxOneBuyLabel, 'callback_data' => $rxOneBuy['porsant_one_buy']],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardOneBuy, 'HTML');
} elseif (($text == "🛡 حفاظت از سواستفاده" || $datain == "affiliates_antifraud_menu") && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_antifraud_title'] ?? "🛡 تنظیمات ضد سواستفاده از سیستم زیرمجموعه‌گیری را انتخاب کنید", $affiliatesAntiFraud, 'HTML');
    step('affiliates_antifraud_menu', $from_id);
} elseif ($text == "☎️ الزام تایید شماره" && $adminrulecheck['rule'] == "administrator") {
    step('affiliates_antifraud_menu', $from_id);
    $rxAntiFraud = select("affiliates", "*", null, null, "select");
    $rxPhoneReqLabel = ($rxAntiFraud['require_phone_verified'] ?? '') === 'on_require_phone' ? "✅ فعال" : "❌ غیرفعال";
    $keyboardPhoneReq = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $rxPhoneReqLabel, 'callback_data' => "affiliates_toggle_phone_required"],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_phone_required_title'] ?? "📞 در صورت فعال بودن، کاربران باید شماره موبایل خود را قبل از دریافت هدیه عضویت تایید کنند.", $keyboardPhoneReq, 'HTML');
} elseif ($datain == "affiliates_toggle_phone_required" && $adminrulecheck['rule'] == "administrator") {
    $rxAntiFraud = select("affiliates", "*", null, null, "select");
    $rxNewPhoneReq = ($rxAntiFraud['require_phone_verified'] ?? '') === 'on_require_phone' ? 'off_require_phone' : 'on_require_phone';
    update("affiliates", "require_phone_verified", $rxNewPhoneReq);
    step('affiliates_antifraud_menu', $from_id);
    $rxPhoneReqLabel = $rxNewPhoneReq === 'on_require_phone' ? "✅ فعال" : "❌ غیرفعال";
    $keyboardPhoneReq = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $rxPhoneReqLabel, 'callback_data' => "affiliates_toggle_phone_required"],
            ],
            [
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_affiliates_extra_phone_required_title'] ?? "📞 در صورت فعال بودن، کاربران باید شماره موبایل خود را قبل از دریافت هدیه عضویت تایید کنند.", $keyboardPhoneReq);
} elseif ($text == "⏳ حداقل سن اکانت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_min_age_prompt'] ?? "⏳ حداقل سن اکانت (به ساعت) برای واجد شرایط بودن دریافت هدیه عضویت را وارد کنید. برای غیرفعال کردن این محدودیت عدد 0 را ارسال کنید.", $backadmin, 'HTML');
    step('setminaccountage', $from_id);
} elseif ($user['step'] == "setminaccountage") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text) || (int) $text < 0) {
        nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_amount'] ?? "❌ مقدار نامعتبر است. لطفاً یک عدد وارد کنید.", $backadmin, 'HTML');
        return;
    }
    update("affiliates", "min_account_age_hours", $text);
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_min_age_saved'] ?? "✅ حداقل سن اکانت ذخیره شد.", $affiliatesAntiFraud, 'HTML');
    step('affiliates_antifraud_menu', $from_id);
} elseif ($text == "📅 سقف روزانه معرفی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_daily_cap_prompt'] ?? "📅 حداکثر تعداد هدیه عضویت قابل پرداخت به هر معرف در روز را وارد کنید. برای غیرفعال کردن سقف عدد 0 را ارسال کنید.", $backadmin, 'HTML');
    step('setdailycap', $from_id);
} elseif ($user['step'] == "setdailycap") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text) || (int) $text < 0) {
        nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_amount'] ?? "❌ مقدار نامعتبر است. لطفاً یک عدد وارد کنید.", $backadmin, 'HTML');
        return;
    }
    update("affiliates", "daily_referral_cap", $text);
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_daily_cap_saved'] ?? "✅ سقف روزانه ذخیره شد.", $affiliatesAntiFraud, 'HTML');
    step('affiliates_antifraud_menu', $from_id);
} elseif ($text == "🗓 سقف ماهانه معرفی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_monthly_cap_prompt'] ?? "🗓 حداکثر تعداد هدیه عضویت قابل پرداخت به هر معرف در ماه را وارد کنید. برای غیرفعال کردن سقف عدد 0 را ارسال کنید.", $backadmin, 'HTML');
    step('setmonthlycap', $from_id);
} elseif ($user['step'] == "setmonthlycap") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text) || (int) $text < 0) {
        nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_amount'] ?? "❌ مقدار نامعتبر است. لطفاً یک عدد وارد کنید.", $backadmin, 'HTML');
        return;
    }
    update("affiliates", "monthly_referral_cap", $text);
    nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_monthly_cap_saved'] ?? "✅ سقف ماهانه ذخیره شد.", $affiliatesAntiFraud, 'HTML');
    step('affiliates_antifraud_menu', $from_id);
} elseif (($text == "📊 گزارشات زیرمجموعه" || $datain == "affiliates_reports") && $adminrulecheck['rule'] == "administrator") {
    $rxTotalCommissionStmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_ledger WHERE category = 'affiliate_commission' AND direction = 'credit'");
    $rxTotalCommission = number_format((float) $rxTotalCommissionStmt->fetchColumn());
    $rxTotalStartGiftStmt = $pdo->query("SELECT COUNT(*) FROM reagent_report WHERE get_gift = 1");
    $rxTotalStartGiftClaims = (int) $rxTotalStartGiftStmt->fetchColumn();
    $rxTotalReferredStmt = $pdo->query("SELECT COUNT(*) FROM user WHERE affiliates IS NOT NULL AND affiliates != '' AND affiliates != '0'");
    $rxTotalReferredUsers = (int) $rxTotalReferredStmt->fetchColumn();
    $rxTopReferrersStmt = $pdo->query("SELECT wl.id_user, COALESCE(NULLIF(MAX(u.username),''), wl.id_user) AS uname, COALESCE(SUM(wl.amount),0) AS total_commission, COUNT(*) AS payouts FROM wallet_ledger wl LEFT JOIN user u ON u.id = wl.id_user WHERE wl.category = 'affiliate_commission' AND wl.direction = 'credit' GROUP BY wl.id_user ORDER BY total_commission DESC LIMIT 10");
    $rxTopReferrers = $rxTopReferrersStmt->fetchAll(PDO::FETCH_ASSOC);
    $rxTopReferrersText = "";
    $rxRank = 1;
    foreach ($rxTopReferrers as $rxRow) {
        $rxTopReferrersText .= "{$rxRank}. @{$rxRow['uname']} — " . number_format((float) $rxRow['total_commission']) . " تومان ({$rxRow['payouts']} پرداخت)\n";
        $rxRank++;
    }
    if ($rxTopReferrersText === '') {
        $rxTopReferrersText = "— هنوز پورسانتی پرداخت نشده است —";
    }
    $rxReportsText = "<b>📊 گزارشات زیرمجموعه‌گیری</b>

💰 مجموع پورسانت پرداخت شده: {$rxTotalCommission} تومان
🎁 تعداد هدیه‌های عضویت پرداخت شده: {$rxTotalStartGiftClaims}
👥 تعداد کل کاربران جذب شده از طریق زیرمجموعه: {$rxTotalReferredUsers}

<b>🏆 برترین معرف‌ها:</b>
{$rxTopReferrersText}";
    nm_adminInstantReply($from_id, $rxReportsText, $affiliates, 'HTML');
} elseif ($datain == "mainbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $_minRaw = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")['ValuePay'] ?? '{}', true);
    $_minRaw = is_array($_minRaw) ? $_minRaw : [];
    $_minLines = implode("\n", array_map(fn($k, $v) => "• <b>$k</b>: " . number_format((int)$v) . " تومان", array_keys($_minRaw), $_minRaw));
    $textmin = "📌 <b>کف شارژ کیف‌پول</b>\n\nمقادیر فعلی:\n" . ($_minLines ?: '—') . "\n\nمبلغ جدید را به تومان وارد کنید:";
    nm_adminInstantReply($from_id, $textmin, $backadmin, 'HTML');
    step('minbalance', $from_id);
} elseif ($user['step'] == "minbalance") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemin', $from_id);
    nm_adminInstantReply($from_id, "📌 برای کدام گروه کاربری اعمال شود?\n\n• <b>n</b> — کاربر عادی\n• <b>n2</b> — کاربر ویژه\n• <b>f</b> — فروشنده\n• <b>allusers</b> — همه گروه‌ها", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemin") {
    if (!isset($update['message']) && empty($text)) { return; }
    $agentst = ["n", "n2", "f", "allusers"];
    $grp = function_exists('rx_resolveAgentGroupFromReplyButton') ? rx_resolveAgentGroupFromReplyButton($text, $agentst) : (in_array($text, $agentst, true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
        return;
    }
    $text = $grp;
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")['ValuePay'] ?? '{}', true);
    if (!is_array($balancemaax)) {
        $balancemaax = ["n" => "20000", "n2" => "20000", "f" => "20000"];
    }
    if ($text === 'allusers') {
        foreach (["n", "n2", "f"] as $_ag) { $balancemaax[$_ag] = $user['Processing_value']; }
    } else {
        $balancemaax[$text] = $user['Processing_value'];
    }
    $balancemaax = json_encode($balancemaax);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $balancemaax, "NamePay", "minbalance");
} elseif ($datain == "maxbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $_maxRaw = json_decode(select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'] ?? '{}', true);
    $_maxRaw = is_array($_maxRaw) ? $_maxRaw : [];
    $_maxLines = implode("\n", array_map(fn($k, $v) => "• <b>$k</b>: " . number_format((int)$v) . " تومان", array_keys($_maxRaw), $_maxRaw));
    $textmax = "📌 <b>سقف شارژ کیف‌پول</b>\n\nمقادیر فعلی:\n" . ($_maxLines ?: '—') . "\n\nمبلغ جدید را به تومان وارد کنید:";
    nm_adminInstantReply($from_id, $textmax, $backadmin, 'HTML');
    step('maxbalance', $from_id);
} elseif ($user['step'] == "maxbalance") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemax', $from_id);
    nm_adminInstantReply($from_id, "📌 برای کدام گروه کاربری اعمال شود?\n\n• <b>n</b> — کاربر عادی\n• <b>n2</b> — کاربر ویژه\n• <b>f</b> — فروشنده\n• <b>allusers</b> — همه گروه‌ها", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemax") {
    if (!isset($update['message']) && empty($text)) { return; }
    $agentst = ["n", "n2", "f", "allusers"];
    $grp = function_exists('rx_resolveAgentGroupFromReplyButton') ? rx_resolveAgentGroupFromReplyButton($text, $agentst) : (in_array($text, $agentst, true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
        return;
    }
    $text = $grp;
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'] ?? '{}', true);
    if (!is_array($balancemaax)) {
        $balancemaax = ["n" => "1000000", "n2" => "1000000", "f" => "1000000"];
    }
    if ($text === 'allusers') {
        foreach (["n", "n2", "f"] as $_ag) { $balancemaax[$_ag] = $user['Processing_value']; }
    } else {
        $balancemaax[$text] = $user['Processing_value'];
    }
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
    $grp = function_exists('rx_resolveAgentGroupFromReplyButton') ? rx_resolveAgentGroupFromReplyButton($text, $agentst) : (in_array($text, $agentst, true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $text = $grp;
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
        if (!isset($update['message']) && empty($text)) { return; }
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
                    'text' => "مشاهده‌اطلاعات",
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
    } else {
        $OrderUser['Service_time'] = $OrderUser['Service_time'] . "روزه";
    }
    $OrderUser['Volume'] = formatInvoiceVolume($OrderUser);
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
                $OrderUser['Volume'] = intval($codeproduct['Volume_constraint']) == 0 ? $textbotlang['users']['stateus']['Unlimited'] : $codeproduct['Volume_constraint'];
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
💰 قیمت پرداختی سرویس : " . rxFormatToman($OrderUser['price_product']) . " تومان
⚜️ حجم سرویس خریداری شده : {$OrderUser['Volume']}
⏳ زمان سرویس خریداری شده : {$OrderUser['Service_time']}
📆 تاریخ خرید : $datatime
";
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
💰مبلغ انجام سرویس : " . rxFormatToman($extend['price']) . "
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
                ['text' => (string)($textbotlang['Admin']['Status']['statusvolumeextra'] ?? '📦 حجم اضافه'), 'callback_data' => "none"],
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
                ['text' => "⚠️ گزارش اختلال", 'callback_data' => "disorderss"],
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
                ['text' => "❓ غیرفعال اکانت", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 قیمت محصول", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 دریافت کانفیگ", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
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
                ['text' => (string)($textbotlang['Admin']['Status']['statusvolumeextra'] ?? '📦 حجم اضافه'), 'callback_data' => "none"],
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
                ['text' => "⚠️ گزارش اختلال", 'callback_data' => "disorderss"],
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
                ['text' => "❓ غیرفعال اکانت", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 قیمت محصول", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 دریافت کانفیگ", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
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
        $rx_cron_hour_fields = ['lottery' => 'lottery_hour', 'statusday' => 'statusday_hour'];
        $rx_cron_has_hour = isset($rx_cron_hour_fields[$rx_cron_key]);
        $rx_cron_hour_now = $rx_cron_has_hour ? (int) ($setting[$rx_cron_hour_fields[$rx_cron_key]] ?? 0) : 0;
        if ($rx_cron_has_hour) {
            $rx_cron_rows[] = [
                ['text' => "🕛 ساعت اجرا: {$rx_cron_hour_now}:00", 'callback_data' => "cronjob_sethour-{$rx_cron_key}"],
            ];
        }
        $rx_cron_rows[] = [
            ['text' => "🔙 بازگشت به لیست کرون‌ها", 'callback_data' => "cronjobs_settings"],
        ];
        $rx_cron_keyboard = json_encode(['inline_keyboard' => $rx_cron_rows], JSON_UNESCAPED_UNICODE);
        $rx_cron_text = "⚙️ تنظیم زمان‌بندی\n\n📌 کرون: <b>{$rx_cron_label}</b>\n⏱ زمان فعلی: <b>{$rx_cron_current_label}</b>\n\nزمان‌بندی جدید را انتخاب کنید:";
        if ($rx_cron_has_hour) {
            $rx_cron_text .= "\n\n🕛 اگر بازه را روی «هر ۱ روز» بگذاری، این کرون رأس ساعت <b>{$rx_cron_hour_now}:00</b> (به وقت تهران) اجرا می‌شود.\nبرای تغییرِ این ساعت، دکمهٔ «تنظیم ساعت اجرا» را بزن.\n(برای حالت «هر N ساعت/دقیقه» این ساعت بی‌اثر است و دقیقاً طبق همان بازه اجرا می‌شود.)";
        }
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
                'cache_time' => 0,
            ]);
        }
        step('admin_nav_cron_jobs', $from_id);
        nm_adminInstantReply($from_id, "🕚 زمان‌بندی کرون‌ها\n\nبرای تغییر بازهٔ اجرای هر کرون روی ⚙️ تنظیمات همان ردیف بزنید.", buildCronJobsKeyboard(), 'HTML');
    }
} elseif (preg_match('/^cronjob_sethour-(lottery|statusday)$/', $datain, $rx_cron_h) && $adminrulecheck['rule'] == "administrator") {
    $rx_h_field = $rx_cron_h[1] === 'lottery' ? 'lottery_hour' : 'statusday_hour';
    $rx_h_now   = (int) ($setting[$rx_h_field] ?? 0);
    step("cronjob_get_hour-{$rx_cron_h[1]}", $from_id);
    nm_adminInstantReply($from_id, "🕛 ساعت اجرای این کرون را به‌صورت عددی بین <b>0</b> تا <b>23</b> ارسال کنید (به وقت تهران).\n\nساعت فعلی: <b>{$rx_h_now}:00</b>", $backadmin, 'HTML');
} elseif (preg_match('/^cronjob_get_hour-(lottery|statusday)$/', (string) ($user['step'] ?? ''), $rx_cron_hs) && $adminrulecheck['rule'] == "administrator") {
    $rx_h_field = $rx_cron_hs[1] === 'lottery' ? 'lottery_hour' : 'statusday_hour';
    if (!ctype_digit((string) $text) || (int) $text < 0 || (int) $text > 23) {
        nm_adminInstantReply($from_id, "❌ لطفاً فقط یک عدد بین 0 تا 23 ارسال کنید.", $backadmin, 'HTML');
        return;
    }
    update("setting", $rx_h_field, (int) $text, null, null);
    step('admin_nav_cron_jobs', $from_id);
    nm_adminInstantReply($from_id, "✅ ساعت اجرا روی <b>" . (int) $text . ":00</b> تنظیم شد.", buildCronJobsKeyboard(), 'HTML');
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
    update("cancel_service", "resolved_at", time(), "username", $user['Processing_value']);
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
        if (empty($nameloc['invalidated_at'])) {
            update("invoice", "invalidated_at", time(), "id_invoice", $nameloc['id_invoice']);
        }
        update("invoice", "Status", "disabledn", "id_invoice", $nameloc['id_invoice']);
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
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
        sendmessage($nameloc['id_user'], "💰کاربر گرامی مبلغ " . rxFormatToman($pricelast) . " تومان به موجودی شما اضافه گردید.", null, 'HTML');
    }
    $ManagePanel->RemoveUser($nameloc['Service_location'], $requestcheck['username']);
    update("cancel_service", "status", "accept", "username", $requestcheck['username']);
    update("cancel_service", "resolved_at", time(), "username", $requestcheck['username']);
    if (function_exists('rxRefundHardDeleteService')) {
        rxRefundHardDeleteService($nameloc['Service_location'] ?? '', $requestcheck['username'] ?? '', $nameloc['id_invoice'] ?? '');
    }
    try {
        $delInvRs = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
        $delInvRs->bindValue(':iid', $nameloc['id_invoice'] ?? '', PDO::PARAM_STR);
        $delInvRs->execute();
    } catch (Throwable $e) {
        error_log('remoceserviceadmin invoice delete failed: ' . $e->getMessage());
    }
    nm_adminInstantReply($from_id, "❌ مبلغ " . rxFormatToman($pricelast) . " تومان به موجودی کاربر اضافه گردید.", null, 'HTML');
    sendmessage($nameloc['id_user'], "✅ کاربری گرامی درخواست حذف شما با نام کاربری  {$nameloc['username']} موافقت گردید.", null, 'HTML');
    $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد

اطلاعات کاربر تایید کننده  :

<blockquote>🪪 آیدی عددی : <code>$from_id</code></blockquote>
<blockquote>💰 مبلغ بازگشتی : " . rxFormatToman($pricelast) . " تومان</blockquote>
<blockquote>👤 نام کاربری : {$requestcheck['username']}</blockquote>
        <blockquote>آیدی عددی درخواست کننده کنسل کردن : {$nameloc['id_user']}</blockquote>";
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
    update("cancel_service", "resolved_at", time(), "username", $requestcheck['username']);
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
    if (function_exists('rxRefundHardDeleteService')) {
        rxRefundHardDeleteService($invoice['Service_location'] ?? '', $invoice['username'] ?? '', $invoice['id_invoice'] ?? '');
    }
    try {
        $delInvGpb = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
        $delInvGpb->bindValue(':iid', $invoice['id_invoice'] ?? '', PDO::PARAM_STR);
        $delInvGpb->execute();
    } catch (Throwable $e) {
        error_log('getpricebackremove invoice delete failed: ' . $e->getMessage());
    }
    sendmessage($invoice['id_user'], "💰کاربر گرامی مبلغ " . rxFormatToman($text) . " تومان به موجودی شما اضافه گردید.", null, 'HTML');
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت به حساب کاربر اضافه گردید.", $keyboardadmin, 'HTML');
    $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد

اطلاعات کاربر تایید کننده  :

<blockquote>🪪 آیدی عددی : <code>$from_id</code></blockquote>
<blockquote>💰 مبلغ بازگشتی : " . rxFormatToman($text) . " تومان</blockquote>
<blockquote>👤 نام کاربری : {$invoice['username']}</blockquote>
<blockquote>آیدی عددی درخواست کننده کنسل کردن : {$invoice['id_user']}</blockquote>";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/^mafurefauto-([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $mafu_inv_id = $dataget[1];
    $mafu_invoice = select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
    if (!is_array($mafu_invoice)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'فاکتور پیدا نشد.',
            'show_alert' => true,
            'cache_time' => 0,
        ]);
        return;
    }
    $mafu_status = (string)($mafu_invoice['Status'] ?? '');
    if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 0,
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
            'cache_time' => 0,
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
            'cache_time' => 0,
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

    if (function_exists('rxRefundHardDeleteService')) {
        rxRefundHardDeleteService($mafu_panel_name, $mafu_username_svc, $mafu_inv_id);
    }
    try {
        $mafu_del_inv = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
        $mafu_del_inv->bindValue(':iid', $mafu_inv_id, PDO::PARAM_STR);
        $mafu_del_inv->execute();
    } catch (Throwable $e) {
        error_log('mafurefauto invoice delete failed: ' . $e->getMessage());
    }

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
        'cache_time' => 0,
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
            'cache_time' => 0,
        ]);
        return;
    }
    $mafu_status = (string)($mafu_invoice['Status'] ?? '');
    if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 0,
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
        'cache_time' => 0,
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

    if (function_exists('rxRefundHardDeleteService')) {
        rxRefundHardDeleteService($mafu_panel_name, $mafu_username_svc, $mafu_inv_id);
    }
    try {
        $mafu_del_inv2 = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
        $mafu_del_inv2->bindValue(':iid', $mafu_inv_id, PDO::PARAM_STR);
        $mafu_del_inv2->execute();
    } catch (Throwable $e) {
        error_log('mafurefamount invoice delete failed: ' . $e->getMessage());
    }

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
} elseif (preg_match('/^nmrefokdef_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $nmref_inv_id = $dataget[1];
    $nmref_invoice = select("invoice", "*", "id_invoice", $nmref_inv_id, "select");
    if (!is_array($nmref_invoice)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور پیدا نشد.', 'show_alert' => true, 'cache_time' => 0]);
        return;
    }
    $nmref_status = (string)($nmref_invoice['Status'] ?? '');
    if (in_array($nmref_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این درخواست قبلاً بررسی شده است.', 'show_alert' => true, 'cache_time' => 0]);
        return;
    }
    $nmref_claim = $pdo->prepare("UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'");
    $nmref_claim->bindValue(':inv', $nmref_inv_id, PDO::PARAM_STR);
    $nmref_claim->execute();
    if ($nmref_claim->rowCount() === 0) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این درخواست قبلاً پردازش شده است.', 'show_alert' => true, 'cache_time' => 0]);
        return;
    }
    $nmref_amount = (int)($nmref_invoice['price_product'] ?? 0);
    $nmref_user_id = (string)($nmref_invoice['id_user'] ?? '');
    $nmref_username_svc = (string)($nmref_invoice['username'] ?? '');
    $nmref_panel_name = (string)($nmref_invoice['Service_location'] ?? '');
    if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser') && $nmref_username_svc !== '') {
        try { @$ManagePanel->RemoveUser($nmref_panel_name, $nmref_username_svc); } catch (\Throwable $_) {}
    }
    $nmref_bal = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $nmref_bal->bindValue(':delta', $nmref_amount, PDO::PARAM_INT);
    $nmref_bal->bindValue(':uid', $nmref_user_id, PDO::PARAM_STR);
    $nmref_bal->execute();
    if (function_exists('rxRefundHardDeleteService')) {
        rxRefundHardDeleteService($nmref_panel_name, $nmref_username_svc, $nmref_inv_id);
    }
    try {
        $nmref_del_inv = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
        $nmref_del_inv->bindValue(':iid', $nmref_inv_id, PDO::PARAM_STR);
        $nmref_del_inv->execute();
    } catch (Throwable $e) {
        error_log('nmrefokdef invoice delete failed: ' . $e->getMessage());
    }
    if (function_exists('nmStockLog')) {
        try { nmStockLog(null, $nmref_user_id, $nmref_inv_id, 'refund_approved_default', ['amount' => $nmref_amount]); } catch (Throwable $e) {}
    }
    sendmessage($nmref_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($nmref_amount) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $nmref_username_svc . "</code> حذف شد.", null, 'HTML');
    $nmref_done = "✅ بازگشت وجه انبار شبکه ملی انجام شد.\n\n💰 مبلغ: " . number_format($nmref_amount) . " تومان\n👤 کاربر: <code>$nmref_user_id</code>\n📛 سرویس: <code>$nmref_username_svc</code>\n🆔 فاکتور: <code>$nmref_inv_id</code>";
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        Editmessagetext($from_id, $message_id, $nmref_done, null, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $nmref_done, $keyboardadmin ?? null, 'HTML');
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'بازگشت وجه انجام شد', 'show_alert' => false, 'cache_time' => 0]);
    if (!empty($setting['Channel_Report'])) {
        $nmref_rep = ['chat_id' => $setting['Channel_Report'], 'text' => "⭕️ بازگشت وجه انبار شبکه ملی (پیش‌فرض)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($nmref_amount) . " تومان\n👤 کاربر: <code>$nmref_user_id</code>\n📛 سرویس: <code>$nmref_username_svc</code>\n🆔 فاکتور: <code>$nmref_inv_id</code>", 'parse_mode' => 'HTML'];
        if (!empty($otherreport)) $nmref_rep['message_thread_id'] = $otherreport;
        telegram('sendmessage', $nmref_rep);
    }
} elseif (preg_match('/^nmrefcustom_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $nmref_inv_id = $dataget[1];
    $nmref_invoice = select("invoice", "*", "id_invoice", $nmref_inv_id, "select");
    if (!is_array($nmref_invoice)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور پیدا نشد.', 'show_alert' => true, 'cache_time' => 0]);
        return;
    }
    $nmref_status = (string)($nmref_invoice['Status'] ?? '');
    if (in_array($nmref_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این درخواست قبلاً بررسی شده است.', 'show_alert' => true, 'cache_time' => 0]);
        return;
    }
    update("user", "Processing_value", $nmref_inv_id, "id", $from_id);
    step("nmrefamount", $from_id);
    $nmref_default = number_format((int)($nmref_invoice['price_product'] ?? 0));
    nm_adminInstantReply($from_id, "📌 مبلغ بازگشتی برای این فاکتور را به‌صورت عدد (تومان) ارسال کنید.\n\n🆔 فاکتور: <code>$nmref_inv_id</code>\n💵 مبلغ خرید اولیه: $nmref_default تومان\n📛 سرویس: <code>" . htmlspecialchars((string)($nmref_invoice['username'] ?? '')) . "</code>", $backadmin ?? null, 'HTML');
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'مبلغ موردنظر را ارسال کنید', 'show_alert' => false, 'cache_time' => 0]);
} elseif (($user['step'] ?? '') == "nmrefamount" && $adminrulecheck['rule'] == "administrator") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'] ?? '❌ مقدار معتبر نیست. فقط عدد ارسال کنید.', $backadmin ?? null, 'HTML');
        return;
    }
    $nmref_inv_id = (string)$user['Processing_value'];
    $nmref_invoice = select("invoice", "*", "id_invoice", $nmref_inv_id, "select");
    if (!is_array($nmref_invoice)) {
        nm_adminInstantReply($from_id, "❌ فاکتور پیدا نشد.", $keyboardadmin ?? null, 'HTML');
        step("home", $from_id);
        return;
    }
    $nmref_status = (string)($nmref_invoice['Status'] ?? '');
    if (in_array($nmref_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده است.", $keyboardadmin ?? null, 'HTML');
        step("home", $from_id);
        return;
    }
    $nmref_amount = intval($text);
    $nmref_user_id = (string)($nmref_invoice['id_user'] ?? '');
    $nmref_username_svc = (string)($nmref_invoice['username'] ?? '');
    $nmref_panel_name = (string)($nmref_invoice['Service_location'] ?? '');
    $nmref_claim = $pdo->prepare("UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'");
    $nmref_claim->bindValue(':inv', $nmref_inv_id, PDO::PARAM_STR);
    $nmref_claim->execute();
    if ($nmref_claim->rowCount() === 0) {
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً پردازش شده است.", $keyboardadmin ?? null, 'HTML');
        step("home", $from_id);
        return;
    }
    if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser') && $nmref_username_svc !== '') {
        try { @$ManagePanel->RemoveUser($nmref_panel_name, $nmref_username_svc); } catch (\Throwable $_) {}
    }
    $nmref_bal = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $nmref_bal->bindValue(':delta', $nmref_amount, PDO::PARAM_INT);
    $nmref_bal->bindValue(':uid', $nmref_user_id, PDO::PARAM_STR);
    $nmref_bal->execute();
    if (function_exists('rxRefundHardDeleteService')) {
        rxRefundHardDeleteService($nmref_panel_name, $nmref_username_svc, $nmref_inv_id);
    }
    try {
        $nmref_del_inv2 = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
        $nmref_del_inv2->bindValue(':iid', $nmref_inv_id, PDO::PARAM_STR);
        $nmref_del_inv2->execute();
    } catch (Throwable $e) {
        error_log('nmrefamount invoice delete failed: ' . $e->getMessage());
    }
    if (function_exists('nmStockLog')) {
        try { nmStockLog(null, $nmref_user_id, $nmref_inv_id, 'refund_approved_custom', ['amount' => $nmref_amount]); } catch (Throwable $e) {}
    }
    sendmessage($nmref_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($nmref_amount) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $nmref_username_svc . "</code> حذف شد.", null, 'HTML');
    nm_adminInstantReply($from_id, "✅ مبلغ " . number_format($nmref_amount) . " تومان به کیف‌پول کاربر اضافه شد و سرویس حذف گردید.", $keyboardadmin ?? null, 'HTML');
    step("home", $from_id);
    if (!empty($setting['Channel_Report'])) {
        $nmref_rep = ['chat_id' => $setting['Channel_Report'], 'text' => "⭕️ بازگشت وجه انبار شبکه ملی (دلخواه)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($nmref_amount) . " تومان\n👤 کاربر: <code>$nmref_user_id</code>\n📛 سرویس: <code>$nmref_username_svc</code>\n🆔 فاکتور: <code>$nmref_inv_id</code>", 'parse_mode' => 'HTML'];
        if (!empty($otherreport)) $nmref_rep['message_thread_id'] = $otherreport;
        telegram('sendmessage', $nmref_rep);
    }
} elseif (preg_match('/^nmrefreject_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $nmref_inv_id = $dataget[1];
    $nmref_invoice = select("invoice", "*", "id_invoice", $nmref_inv_id, "select");
    if (!is_array($nmref_invoice)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور پیدا نشد.', 'show_alert' => true, 'cache_time' => 0]);
        return;
    }
    $nmref_status = (string)($nmref_invoice['Status'] ?? '');
    if ($nmref_status !== 'nm_refund_pending') {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این درخواست قبلاً بررسی شده است.', 'show_alert' => true, 'cache_time' => 0]);
        return;
    }
    $nmref_reject = $pdo->prepare("UPDATE invoice SET Status = 'active' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'");
    $nmref_reject->bindValue(':inv', $nmref_inv_id, PDO::PARAM_STR);
    $nmref_reject->execute();
    if ($nmref_reject->rowCount() === 0) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این درخواست قبلاً پردازش شده است.', 'show_alert' => true, 'cache_time' => 0]);
        return;
    }
    $nmref_user_id = (string)($nmref_invoice['id_user'] ?? '');
    if (function_exists('nmStockLog')) {
        try { nmStockLog(null, $nmref_user_id, $nmref_inv_id, 'refund_rejected', null); } catch (Throwable $e) {}
    }
    sendmessage($nmref_user_id, "❌ درخواست بازگشت وجه شما توسط ادمین رد شد. سرویس شما فعال باقی می‌ماند.", null, 'HTML');
    $nmref_done = "❌ درخواست بازگشت وجه رد شد.\n\n👤 کاربر: <code>$nmref_user_id</code>\n🆔 فاکتور: <code>$nmref_inv_id</code>";
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        Editmessagetext($from_id, $message_id, $nmref_done, null, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $nmref_done, $keyboardadmin ?? null, 'HTML');
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'درخواست رد شد', 'show_alert' => false, 'cache_time' => 0]);
} elseif ($datain == "settimecornremovevolume" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['setvolumeremove'] . $setting['cronvolumere'] . "روز", $backadmin, 'HTML');
    step("getcronvolumere", $from_id);
} elseif ($user['step'] == "getcronvolumere") {
    if (!isset($update['message']) && empty($text)) { return; }
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
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "on_hold_day", $text);
} elseif ($datain == "set_panel_timeout" && $adminrulecheck['rule'] == "administrator") {
    $curTimeout = (int)($setting['panel_curl_timeout'] ?? 20);
    nm_adminInstantReply($from_id, "⏱ <b>تایم‌اوت اتصال به پنل</b>\n\nمقدار فعلی: <b>{$curTimeout} ثانیه</b>\n\nمقدار جدید را (عدد بین 5 تا 120) ارسال کنید:", $backadmin, 'HTML');
    step("get_panel_timeout", $from_id);
} elseif ($user['step'] == "get_panel_timeout") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text) || (int)$text < 5 || (int)$text > 120) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر است. عددی بین 5 تا 120 ارسال کنید.", $backadmin, 'HTML');
        return;
    }
    update("setting", "panel_curl_timeout", $text);
    nm_adminInstantReply($from_id, "✅ تایم‌اوت پنل به <b>{$text} ثانیه</b> تغییر یافت.", $setting_panel, 'HTML');
    step("home", $from_id);
}
