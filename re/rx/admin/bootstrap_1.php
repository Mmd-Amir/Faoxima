<?php

$guardHelperPath = REFACTORED_LEGACY_ROOT . '/guard.php';
if (is_file($guardHelperPath)) {
    require_once $guardHelperPath;
}


if (
    in_array($from_id, $admin_ids ?? [])
    && empty($callback_query_id)
    && !empty($message_id)
    && isset($setting['inlinebtnmain'])
    && $setting['inlinebtnmain'] === 'oninline'
    && function_exists('deletemessage')
) {


    if (!isset($GLOBALS['rx_admin_instant_deleted']) || $GLOBALS['rx_admin_instant_deleted'] !== $message_id) {
        try { @deletemessage($from_id, $message_id); } catch (Throwable $e) {}
        $GLOBALS['rx_admin_instant_deleted'] = $message_id;
    }
}

$textadmin = ["panel", "/panel", $textbotlang['Admin']['textpaneladmin']];
if (isset($datain) && $datain != "" && $text == "" && in_array($from_id, $admin_ids)) {
    $text = $datain;
}
$text_panel_admin_login_template = "💎 | Version Bot: 0.0.1\n📌 | Version Mini App: 0.0.1\n<blockquote>🔹 | این ربات کاملاً رایگان است توسط Mmd | Amir ریفکتور شده است</blockquote>\n\n<blockquote>🔹 | هرگونه فروش یا دریافت وجه بابت این ربات تخلف محسوب می‌شود.</blockquote>\n\n<blockquote>🔹 | در صورت مشاهده فروش یا دریافت وجه، لطفاً وجه خود را پیگیری کرده و بازپس‌گیری نمایید.</blockquote>\n\n<blockquote>🐞 | اگر در عملکرد ربات با باگ یا مشکلی مواجه شدید، از طریق گیت هاب یا گروه فاکسیما اطلاع رسانی کنید</blockquote>\n\n<blockquote><a href=\"https://github.com/Mmd-Amir/Faoxima\">لینک گیت هاب</a></blockquote>";

if (!function_exists('normalizeXuiSingleSubscriptionBaseUrl')) {

}


if (!function_exists('buildXuiSingleBaseUrl')) {

}


if (!function_exists('hasLikelyXuiSubscriptionId')) {

}


function ensureGuardPanelColumnsReady(PDO $pdo)
{
    $requiredColumns = [
        'api_key' => "VARCHAR(500)",
        'guard_service_ids' => "TEXT",
        'guard_note' => "TEXT",
        'guard_auto_delete_days' => "INT(11)",
        'guard_auto_renewals' => "TEXT",
    ];

    if (function_exists('ensureMarzbanGuardFieldsMigrated')) {
        ensureMarzbanGuardFieldsMigrated();
    } else {
        foreach ($requiredColumns as $column => $datatype) {
            addFieldToTable("marzban_panel", $column, null, $datatype);
        }
    }

    $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marzban_panel' AND COLUMN_NAME IN ($placeholders)"
    );
    $stmt->execute(array_keys($requiredColumns));
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $missingColumns = array_diff(array_keys($requiredColumns), $existingColumns);

    return [
        'status' => empty($missingColumns),
        'missing' => array_values($missingColumns),
    ];
}

function guardFormatServiceList(array $services)
{
    if (empty($services)) {
        return "• لیست سرویس خالی است.";
    }
    $lines = [];
    foreach ($services as $service) {
        $serviceData = is_array($service) ? $service : [];
        $id = isset($serviceData['id']) ? intval($serviceData['id']) : 'نامشخص';
        $title = guardServiceLabel($serviceData);
        $usageRate = null;
        foreach (['usage_rate', 'usageRate'] as $rateKey) {
            if (isset($serviceData[$rateKey]) && is_numeric($serviceData[$rateKey])) {
                $usageRate = $serviceData[$rateKey];
                break;
            }
        }
        $rateLabel = $usageRate !== null ? " [{$usageRate}x]" : '';
        $lines[] = "• id={$id} | {$title}{$rateLabel}";
    }
    return implode("\n", $lines);
}

function guardExtractUsageRateValue(array $service)
{
    foreach (['usage_rate', 'usageRate'] as $rateKey) {
        if (isset($service[$rateKey]) && is_numeric($service[$rateKey])) {
            return floatval($service[$rateKey]);
        }
    }
    return null;
}

function guardFormatUsageRateLabel($rate)
{
    $value = is_numeric($rate) ? floatval($rate) : 1.0;
    $precision = (floor($value) == $value) ? 1 : 2;
    $formatted = number_format($value, $precision, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    if (strpos($formatted, '.') === false) {
        $formatted .= '.0';
    }
    return $formatted;
}

function syncSuiInboundsWithProxies($panelId)
{
    if (empty($panelId)) {
        return;
    }

    $panelData = select("marzban_panel", "id,type,proxies,inbounds", "id", $panelId, "select");
    if (!is_array($panelData) || ($panelData['type'] ?? '') !== 's_ui') {
        return;
    }

    $proxies = $panelData['proxies'] ?? null;
    if ($proxies === null || $proxies === '') {
        return;
    }

    $inbounds = $panelData['inbounds'] ?? null;
    if ($inbounds === null || $inbounds === '' || $inbounds !== $proxies) {
        update("marzban_panel", "inbounds", $proxies, "id", $panelData['id']);
    }
}

function guardBuildServiceButtonLabel(array $service, $isSelected)
{
    $label = guardServiceLabel($service);
    $rateLabel = guardFormatUsageRateLabel(guardExtractUsageRateValue($service));
    $statusIcon = $isSelected ? '✅' : '❌';
    return "[{$rateLabel}x] {$label} {$statusIcon}";
}

function guardBuildServiceSummaryLabel(array $service)
{
    $label = guardServiceLabel($service);
    $rateLabel = guardFormatUsageRateLabel(guardExtractUsageRateValue($service));
    return "{$label} [{$rateLabel}x]";
}

function guardNormalizeSelectedServiceIds($selectedIds, array $availableIds, $fallbackToAll = false)
{
    $normalized = [];
    $hasAll = false;
    if (is_array($selectedIds)) {
        foreach ($selectedIds as $id) {
            if ($id === 'all' || $id === '0' || $id === 0) {
                $hasAll = true;
                continue;
            }
            if (is_numeric($id)) {
                $normalized[] = intval($id);
            }
        }
    }
    $normalized = array_values(array_intersect(array_unique($normalized), $availableIds));
    if ($hasAll) {
        return $availableIds;
    }
    if ($fallbackToAll && empty($normalized)) {
        return $availableIds;
    }
    return $normalized;
}

function guardBuildServiceSelectionSummary(array $services, array $selectedIds, $selectAll = false)
{
    $availableIds = guardExtractServiceIdsFromList($services);
    $selectedIds = guardNormalizeSelectedServiceIds($selectedIds, $availableIds, false);
    if ($selectAll || (!empty($availableIds) && count($selectedIds) === count($availableIds))) {
        return "همه سرویس‌ها";
    }
    $labels = [];
    foreach ($services as $service) {
        $id = isset($service['id']) ? intval($service['id']) : 0;
        if ($id !== 0 && in_array($id, $selectedIds, true)) {
            $labels[] = guardBuildServiceSummaryLabel($service);
        }
    }
    return !empty($labels) ? implode(' ، ', $labels) : "هیچ سرویسی انتخاب نشده است.";
}

function guardBuildServiceSelectionMessage(array $services, array $selectedIds, $selectAll = false)
{
    $baseLines = [
        "✨ انتخاب سرویس‌های قابل ساخت توسط ربات",
        "لطفاً مشخص کنید ربات مجاز به ساخت کدام سرویس‌ها باشد.",
        "پس از اعمال تغییرات، دکمه «ذخیره و اعمال» را بزنید. برای خروج بدون ثبت از دکمه اختصاصی استفاده کنید.",
        "",
        "📌 توجه:",
        "حداقل یک سرویس باید انتخاب شود.",
    ];
    $allState = $selectAll ? "✅ همه سرویس‌ها فعال است" : "❌ همه سرویس‌ها غیرفعال است";
    $summary = guardBuildServiceSelectionSummary($services, $selectedIds, $selectAll);
    return implode("\n", $baseLines) . "\n\nحالت سرویس‌ها: {$allState}\n\nانتخاب فعلی:\n{$summary}";
}

function guardBuildServiceSelectionKeyboard(array $services, array $selectedIds, $mode, $selectAll = false)
{
    $availableIds = guardExtractServiceIdsFromList($services);
    $selectedIds = guardNormalizeSelectedServiceIds($selectedIds, $availableIds, false);
    $buttons = [];
    foreach ($services as $service) {
        if (!isset($service['id'])) {
            continue;
        }
        $id = intval($service['id']);
        $isSelected = $selectAll || in_array($id, $selectedIds, true);
        $buttons[] = [
            'text' => guardBuildServiceButtonLabel($service, $isSelected),
            'callback_data' => "guardservice:{$mode}:toggle:{$id}",
        ];
    }
    $keyboard = ['inline_keyboard' => []];
    while (count($buttons) > 0) {
        $keyboard['inline_keyboard'][] = array_splice($buttons, 0, 2);
    }
    $keyboard['inline_keyboard'][] = [
        [
            'text' => $selectAll ? "✅ همه سرویس‌ها" : "❌ همه سرویس‌ها",
            'callback_data' => "guardservice:{$mode}:toggle_all",
        ],
    ];
    $keyboard['inline_keyboard'][] = [
        ['text' => "💾 ذخیره و اعمال", 'callback_data' => "guardservice:{$mode}:save"],
    ];
    $keyboard['inline_keyboard'][] = [
        ['text' => "↩️ خروج بدون ذخیره", 'callback_data' => "guardservice:{$mode}:close"],
    ];
    return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
}

function guardEncodeServiceSelectionForStorage(array $selectedIds, array $availableIds, $selectAll = false)
{
    $selectedIds = guardNormalizeSelectedServiceIds($selectedIds, $availableIds, false);
    $allSelected = $selectAll || (!empty($availableIds) && count(array_diff($availableIds, $selectedIds)) === 0);
    if ($allSelected) {
        return "0";
    }
    return json_encode(array_values(array_unique($selectedIds)));
}

function guardExtractPanelNameFromState(array $state)
{
    foreach (['panel', 'panel_name', 'namepanel'] as $key) {
        if (!empty($state[$key]) && is_string($state[$key])) {
            return trim($state[$key]);
        }
    }
    return !empty($state['guard_service_selection']['panel'])
        ? trim((string) $state['guard_service_selection']['panel'])
        : null;
}

function guardResolveUserPanelName(array $user)
{
    if (!isset($user['Processing_value'])) {
        return null;
    }
    $raw = $user['Processing_value'];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        foreach (['panel', 'panel_name', 'namepanel'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                return trim($decoded[$key]);
            }
        }
        if (!empty($decoded['guard_service_selection']['panel'])) {
            return trim((string) $decoded['guard_service_selection']['panel']);
        }
    }

    $trimmed = trim((string) $raw);
    return $trimmed === '' || $trimmed === '0' ? null : $trimmed;
}

function guardRestorePanelSelection($userId, array $state)
{
    $panelName = guardExtractPanelNameFromState($state);
    if (!empty($state['mode']) && $state['mode'] === 'edit' && $panelName !== null) {
        update("user", "Processing_value", $panelName, "id", $userId);
    }
}

function guardFormatAutoRenewalsForSummary(array $entries)
{
    if (empty($entries)) {
        return '0';
    }
    $formatted = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $expireDays = isset($entry['expire_days']) ? intval($entry['expire_days']) : 0;
        $usageGb = isset($entry['usage_gb']) ? floatval($entry['usage_gb']) : 0;
        $usageGbFormatted = rtrim(rtrim(number_format($usageGb, 2, '.', ''), '0'), '.');
        if ($usageGbFormatted === '') {
            $usageGbFormatted = '0';
        }
        $resetUsage = (!empty($entry['reset_usage']) || (!empty($entry['reset']) && $entry['reset'] === true)) ? '1' : '0';
        $formatted[] = "{$expireDays},{$usageGbFormatted},{$resetUsage}";
    }
    return !empty($formatted) ? implode(' | ', $formatted) : '0';
}

function guardExtractSavedGuardSettings(array $panel)
{
    return [
        'note' => $panel['guard_note'] ?? '',
        'auto_delete_days' => max(0, intval($panel['guard_auto_delete_days'] ?? 0)),
        'auto_renewals' => guardDecodeAutoRenewalsConfig($panel['guard_auto_renewals'] ?? []),
    ];
}

function guardBuildGuardSettingsState(array $panel, $messageId = null)
{
    $savedSettings = guardExtractSavedGuardSettings($panel);

    return [
        'panel' => $panel['name_panel'] ?? null,
        'note' => $savedSettings['note'],
        'auto_delete_days' => $savedSettings['auto_delete_days'],
        'auto_renewals' => $savedSettings['auto_renewals'],
        'saved' => $savedSettings,
        'pending_changes' => false,
        'message_id' => $messageId,
    ];
}

function guardPersistGuardSettingsState($fromId, array $state)
{
    update("user", "Processing_value_one", json_encode($state, JSON_UNESCAPED_UNICODE), "id", $fromId);
}

function guardLoadGuardSettingsState(array $user, array $panel, $messageId = null)
{
    $state = [];
    if (isset($user['Processing_value_one'])) {
        $decoded = json_decode($user['Processing_value_one'], true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }
    if (!is_array($state) || empty($state)) {
        $userId = $user['id'] ?? null;
        if ($userId !== null) {
            $freshUser = select("user", "Processing_value_one", "id", $userId, "select");
            if (is_array($freshUser) && isset($freshUser['Processing_value_one'])) {
                $decoded = json_decode($freshUser['Processing_value_one'], true);
                if (is_array($decoded)) {
                    $state = $decoded;
                }
            }
        }
    }

    $panelName = $panel['name_panel'] ?? null;
    $savedFromPanel = guardExtractSavedGuardSettings($panel);
    $hasPendingChanges = !empty($state['pending_changes']);

    if (!is_array($state) || ($state['panel'] ?? null) !== $panelName) {
        $state = guardBuildGuardSettingsState($panel, $messageId);
    } else {
        $state['panel'] = $panelName;
        $state['saved'] = guardExtractSavedGuardSettings($panel);
        $state['message_id'] = $messageId ?? ($state['message_id'] ?? null);

        if ($hasPendingChanges) {
            $state['note'] = array_key_exists('note', $state) ? (string) $state['note'] : $savedFromPanel['note'];
            $state['auto_delete_days'] = max(0, intval($state['auto_delete_days'] ?? $savedFromPanel['auto_delete_days']));
            $state['auto_renewals'] = guardDecodeAutoRenewalsConfig($state['auto_renewals'] ?? $savedFromPanel['auto_renewals']);
            $state['pending_changes'] = true;
        } else {
            $state['note'] = $savedFromPanel['note'];
            $state['auto_delete_days'] = $savedFromPanel['auto_delete_days'];
            $state['auto_renewals'] = $savedFromPanel['auto_renewals'];
            $state['pending_changes'] = false;
        }
    }

    return $state;
}

function guardBuildGuardSettingsMessage(array $state, $includeSavedNotice = false)
{
    $note = trim((string) ($state['note'] ?? ''));
    $safeNote = $note === '' ? '-' : htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $autoDelete = max(0, intval($state['auto_delete_days'] ?? 0));
    $autoRenewals = guardFormatAutoRenewalsForSummary($state['auto_renewals'] ?? []);
    $lines = [
        "🎛️ تنظیمات سرویس",
        "",
        "📝 توضیحات : {$safeNote}",
        "🗑 حذف خودکار : {$autoDelete}",
        "👤 تمدید خودکار : {$autoRenewals}",
        "",
    ];
    if (!empty($state['pending_changes'])) {
        $lines[] = "💾 تغییرات ذخیره نشده است. برای ثبت، دکمه «✅ ذخیره» را بزنید.";
        $lines[] = "";
    }
    if ($includeSavedNotice) {
        $lines[] = "✅ تنظیمات سرویس Guard ذخیره شد.";
        $lines[] = "";
    }
    $lines[] = "برای ویرایش روی هر گزینه بزنید 👇";
    return implode("\n", $lines);
}

function guardBuildGuardSettingsKeyboard()
{
    return json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔖 | ویرایش توضیحات", 'callback_data' => "guardsettings:note"],
            ],
            [
                ['text' => "🗑️ | ویرایش حذف خودکار", 'callback_data' => "guardsettings:auto_delete"],
            ],
            [
                ['text' => "🙎🏻‍♂️ | ویرایش تمدید خودکار کاربر", 'callback_data' => "guardsettings:auto_renew"],
            ],
            [
                ['text' => "✅ ذخیره", 'callback_data' => "guardsettings:save"],
            ],
            [
                ['text' => "❌ | بستن", 'callback_data' => "guardsettings:back"],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

function guardRenderGuardSettingsSummary($chatId, array $state, $includeSavedNotice = false)
{
    $keyboard = guardBuildGuardSettingsKeyboard();
    $messageText = guardBuildGuardSettingsMessage($state, $includeSavedNotice);
    $messageId = $state['message_id'] ?? null;
    if ($messageId !== null) {
        deletemessage($chatId, $messageId);
    }
    $response = sendmessage($chatId, $messageText, $keyboard, 'HTML');
    return $response['result']['message_id'] ?? $messageId;
}

function guardRenderGuardSettingsPrompt($chatId, array $state, $promptText)
{
    $keyboard = guardBuildGuardSettingsKeyboard();
    $messageId = $state['message_id'] ?? null;
    if ($messageId !== null) {
        deletemessage($chatId, $messageId);
    }
    $response = sendmessage($chatId, $promptText, $keyboard, 'HTML');
    return $response['result']['message_id'] ?? $messageId;
}

function guardParseServiceSelectionInput($input, array $services)
{
    $input = trim((string) $input);
    $availableIds = guardExtractServiceIdsFromList($services);
    if (empty($availableIds)) {
        return [
            'status' => false,
            'msg' => 'لیست سرویس خالی است.'
        ];
    }
    if ($input === '') {
        return [
            'status' => false,
            'msg' => 'ورودی خالی است.'
        ];
    }
    $lower = strtolower($input);
    if (in_array($lower, ['0', 'all', 'skip', 'none'], true)) {
        return [
            'status' => true,
            'service_ids' => $availableIds
        ];
    }
    $parts = preg_split('/\s*,\s*/', $input);
    $selected = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (!ctype_digit($part)) {
            return [
                'status' => false,
                'msg' => 'شناسه سرویس نامعتبر است.'
            ];
        }
        $id = intval($part);
        if (!in_array($id, $availableIds, true)) {
            return [
                'status' => false,
                'msg' => "سرویس {$id} در لیست موجود نیست."
            ];
        }
        $selected[] = $id;
    }
    $selected = array_values(array_unique($selected));
    if (empty($selected)) {
        return [
            'status' => false,
            'msg' => 'هیچ سرویس معتبری ارسال نشد.'
        ];
    }

    return [
        'status' => true,
        'service_ids' => $selected
    ];
}

function guardFormatConnectionResult(array $result)
{
    global $textbotlang;
    $statusCode = $result['response']['status'] ?? null;
    if (!empty($result['status'])) {
        $adminName = guardExtractAdminName($result['data'] ?? []);
        $adminLabel = $adminName !== '' ? " ({$adminName})" : '';
        return trim(($textbotlang['Admin']['managepanel']['guard']['connection_ok'] ?? "✅ اتصال برقرار است") . $adminLabel);
    }
    if (in_array($statusCode, [401, 403], true)) {
        return "❌ عدم دسترسی (401/403) → API Key اشتباه";
    }
    $errorMsg = $result['msg'] ?? '';
    $prefix = $textbotlang['Admin']['managepanel']['guard']['connection_error'] ?? "❌ خطای اتصال";
    if ($errorMsg !== '') {
        return "{$prefix} → {$errorMsg}";
    }
    return $prefix;
}


if (!function_exists('sendAdminFinanceMenu')) {
function sendAdminFinanceMenu($chatId, $message = null)
{
    global $textbotlang;
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    if ($arzireyali1 != 'onSwapinoBot' && $arzireyali1 != 'offSwapinoBot') {
        update('PaySetting', 'ValuePay', 'onSwapinoBot', 'NamePay', 'statusSwapWallet');
        $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    }
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $zarinpey = getPaySettingValue('zarinpeystatus', 'offzarinpey');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');

    $statusOn = $textbotlang['Admin']['Status']['statuson'] ?? 'فعال';
    $statusOff = $textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال';
    $cartotcartstatus = $cartotcart === 'oncard' ? $statusOn : $statusOff;
    $plisiostatus = $plisio === 'onnowpayment' ? $statusOn : $statusOff;
    $arzireyali1status = $arzireyali1 === 'onSwapinoBot' ? $statusOn : $statusOff;
    $arzireyali2status = $arzireyali2 === 'onternado' ? $statusOn : $statusOff;
    $aqayepardakhtstatus = $aqayepardakht === 'onaqayepardakht' ? $statusOn : $statusOff;
    $zarinpalstatus = $zarinpal === 'onzarinpal' ? $statusOn : $statusOff;
    $zarinpeystatus = $zarinpey === 'onzarinpey' ? $statusOn : $statusOff;
    $affilnecurrencystatus = $affilnecurrency === 'ondigi' ? $statusOn : $statusOff;
    $arzireyali3text = $arzireyali3 === 'oniranpay3' ? $statusOn : $statusOff;
    $paymentstar = (string)$paymentsstartelegram === '1' ? $statusOn : $statusOff;
    $now_payment_status = (string)$payment_status_nowpayment === '1' ? $statusOn : $statusOff;

    $keyboard = json_encode(['inline_keyboard' => [
        [
            ['text' => 'عملیات', 'callback_data' => 'actions'],
            ['text' => $textbotlang['Admin']['Status']['statussubject'] ?? 'وضعیت', 'callback_data' => 'subjectde'],
            ['text' => $textbotlang['Admin']['Status']['subject'] ?? 'موضوع', 'callback_data' => 'subject'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'cartsetting'],
            ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
            ['text' => '🔌 کارت به کارت', 'callback_data' => 'carttocart'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'plisiosetting'],
            ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
            ['text' => '📌 plisio', 'callback_data' => 'plisio'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'nowpaymentsetting'],
            ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
            ['text' => '📌 nowpayment', 'callback_data' => 'nowpayment'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'iranpay1setting'],
            ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
            ['text' => '📌 ارزی ریالی اول', 'callback_data' => 'arzireyali1'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'iranpay2setting'],
            ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
            ['text' => '📌 ارزی ریالی دوم', 'callback_data' => 'arzireyali2'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'iranpay3setting'],
            ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
            ['text' => '📌ارزی ریالی سوم', 'callback_data' => 'oniranpay3'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'zarinpeysetting'],
            ['text' => $zarinpeystatus, 'callback_data' => "editpayment-zarinpey-$zarinpey"],
            ['text' => '🟠 زرین پی', 'callback_data' => 'zarinpey'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'aqayepardakhtsetting'],
            ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
            ['text' => '🔵 آقای پرداخت', 'callback_data' => 'aqayepardakht'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'zarinpalsetting'],
            ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
            ['text' => '🟡 زرین پال', 'callback_data' => 'zarinpal'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'affilnecurrencysetting'],
            ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
            ['text' => '💵ارزی آفلاین', 'callback_data' => 'affilnecurrency'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'startelegram'],
            ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
            ['text' => '💫Star Telegram', 'callback_data' => 'none'],
        ],
        [
            ['text' => '⬆️ حداکثر شارژ موجودی', 'callback_data' => 'maxbalanceaccount'],
            ['text' => '⬇️ حداقل شارژ موجودی', 'callback_data' => 'mainbalanceaccount'],
        ],
        [
            ['text' => '💼 آدرس ولت', 'callback_data' => 'walletaddress'],
        ],
        [
            ['text' => '❌ بستن', 'callback_data' => 'close_stat'],
        ],
    ]], JSON_UNESCAPED_UNICODE);

    $text = $message ?: "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.\n\n⚠️ تیم فاکسیما هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد";
    sendmessage($chatId, $text, $keyboard, 'HTML');
}
}


if (!in_array($from_id, $admin_ids))
    return;

$domainhostsEscaped = htmlspecialchars($domainhosts, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$miniAppInstructionText = <<<HTML
📌 آموزش فعالسازی مینی اپ در ربات BotFather

/mybots > Select Bot > Bot Setting >  Configure Mini App > Enable Mini App  > Edit Mini App URL

مراحل بالا را طی کنید سپس آدرس زیر را ارسال نمایید :

<code>https://{$domainhostsEscaped}/app/</code>

➖➖➖➖➖➖➖➖➖➖➖➖
⚙️ تنظیم کرون‌جاب در هاست

فقط <b>یک کرون</b> کافی است — بقیه فرآیندها به‌صورت خودکار از همین کرون اجرا می‌شوند:

<b>⏱ هر ۱ دقیقه یک بار</b>
<code> curl -s https://{$domainhostsEscaped}/cron/cron.php &gt; /dev/null 2&gt;&amp;1</code>
HTML;


if (!function_exists('nm_getBroadcastStatus')) {
    function nm_getBroadcastStatus() {
        $infoFile      = 'cronbot/info';
        $usersFileTxt  = 'cronbot/users.txt';
        $usersFileJson = 'cronbot/users.json';
        if (!is_file($infoFile)) {
            return null;
        }
        $infoContent = @file_get_contents($infoFile);
        if ($infoContent === false || $infoContent === '') {
            return null;
        }
        $info = json_decode($infoContent, true);
        if (!is_array($info)) {
            return null;
        }


        $remaining = 0;
        if (is_file($usersFileTxt)) {
            $fh = @fopen($usersFileTxt, 'r');
            if ($fh) {
                while (!feof($fh)) {
                    $chunk = fread($fh, 65536);
                    if ($chunk === false) break;
                    $remaining += substr_count($chunk, "\n");
                }
                fclose($fh);
            }
        } elseif (is_file($usersFileJson)) {
            $raw = @file_get_contents($usersFileJson);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $remaining = count($decoded);
            }
        }
        $stats = isset($info['stats']) && is_array($info['stats']) ? $info['stats'] : [];
        $stats += [
            'total'          => 0,
            'success'        => 0,
            'blocked'        => 0,
            'deleted'        => 0,
            'failed'         => 0,
            'chat_not_found' => 0,
            'started_at'     => 0,
        ];
        $totalSent = (int) $stats['success']
                   + (int) $stats['blocked']
                   + (int) $stats['failed']
                   + (int) $stats['chat_not_found'];
        $total = (int) $stats['total'];
        if ($total <= 0) {

            $total = $totalSent + $remaining;
        }

        if ($remaining === 0 && $totalSent === 0 && $total === 0) {
            return null;
        }
        return [
            'type'           => isset($info['type']) ? (string) $info['type'] : '',
            'total'          => $total,
            'sent'           => $totalSent,
            'remaining'      => $remaining,
            'success'        => (int) $stats['success'],
            'blocked'        => (int) $stats['blocked'],
            'deleted'        => (int) $stats['deleted'],
            'failed'         => (int) $stats['failed'],
            'chat_not_found' => (int) $stats['chat_not_found'],
            'started_at'     => (int) $stats['started_at'],
            'finished'       => ($remaining === 0),
        ];
    }
}
if (!function_exists('nm_buildBroadcastStatusText')) {
    function nm_buildBroadcastStatusText(array $status) {
        $typeMap = [
            'sendmessage'    => 'ارسال همگانی',
            'forwardmessage' => 'فوروارد همگانی',
            'xdaynotmessage' => 'پیام به کاربران غیرفعال',
            'unpinmessage'   => 'لغو پیام پین شده',
        ];
        $typeName  = isset($typeMap[$status['type']]) ? $typeMap[$status['type']] : $status['type'];
        $total     = (int) $status['total'];
        $sent      = (int) $status['sent'];
        $remaining = (int) $status['remaining'];
        $progress  = $total > 0 ? min(100, (int) floor(($sent / $total) * 100)) : 0;

        $cells  = 10;
        $filled = $total > 0 ? (int) floor(($sent / $total) * $cells) : 0;
        $bar    = str_repeat('█', $filled) . str_repeat('░', max(0, $cells - $filled));
        $t  = "⏳ <b>یک عملیات ارسال پیام در حال انجام است</b>\n";
        $t .= "—————————————————\n";
        $t .= "⚙️ نوع عملیات : <b>{$typeName}</b>\n\n";
        $t .= "👥 تعداد کل کاربران : <b>" . number_format($total)     . "</b>\n";
        $t .= "🚀 ارسال‌شده : <b>"        . number_format($sent)      . "</b>\n";
        $t .= "📊 باقی‌مانده در صف : <b>" . number_format($remaining) . "</b>\n\n";
        $t .= "📈 پیشرفت : <b>{$progress}%</b>\n<code>{$bar}</code>\n";
        $details = [];
        if ($status['success']        > 0) $details[] = '✅ موفق: '   . number_format($status['success']);
        if ($status['blocked']        > 0) $details[] = '🚫 بلاک: '    . number_format($status['blocked']);
        if ($status['chat_not_found'] > 0) $details[] = '📵 بدون چت: ' . number_format($status['chat_not_found']);
        if ($status['deleted']        > 0) $details[] = '🗑 حذف‌شده: '  . number_format($status['deleted']);
        if ($status['failed']         > 0) $details[] = '❌ خطا: '     . number_format($status['failed']);
        if (!empty($details)) {
            $t .= "\n📋 جزئیات : " . implode(' | ', $details) . "\n";
        }
        if ($status['started_at'] > 0) {
            $elapsed = max(0, time() - (int) $status['started_at']);
            $t .= "⏱ زمان سپری‌شده : <code>" . gmdate('H:i:s', $elapsed) . "</code>\n";
        }
        $t .= "\n🕒 آخرین بروزرسانی : <code>" . date('H:i:s') . "</code>";
        $t .= "\n💡 برای دیدن آخرین آمار روی «🔄 بروزرسانی» بزنید.";
        return $t;
    }
}
if (!function_exists('nm_buildBroadcastStatusKeyboard')) {
    function nm_buildBroadcastStatusKeyboard() {
        return json_encode([
            'inline_keyboard' => [
                [['text' => "🔄 بروزرسانی",       'callback_data' => 'broadcast_status_refresh']],
                [['text' => "❌ لغو عملیات",       'callback_data' => 'cancel_sendmessage']],
                [['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser']],
            ]
        ]);
    }
}

// ── Admin inline-keyboard → $text normalisation ───────────────────────────
// When admin presses an inline keyboard button (converted from reply keyboard),
// $datain has the callback_data but $text is empty. Map it back to the button
// text so ALL existing $text == "..." routing comparisons work unchanged.
// Fix: run mapping even if $text was already set to $datain by the early assignment above
if (!empty($datain) && in_array($from_id, $admin_ids ?? [])) {
    $_rx_adm_cb_map = [
        // admin_main
        'admin_status'      => $textbotlang['Admin']['Status']['btn'],
        'admin_managepanel' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'],
        'admin_addpanel'    => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'],
        'admin_timeprice'   => "⏳ تنظیم سریع قیمت زمان",
        'admin_volprice'    => "🔋 تنظیم سریع قیمت حجم",
        'admin_users'       => $textbotlang['Admin']['btnkeyboardadmin']['managruser'],
        'admin_shop'        => "🏬 تنظیمات فروشگاه",
        'admin_finance'     => "💎 مالی",
        'admin_support'     => "🤙 بخش پشتیبانی",
        'admin_help'        => "📚 بخش آموزش",
        'admin_features'    => "🛠 قابلیت های پنل",
        'admin_settings'    => "⚙️ تنظیمات عمومی",
        'admin_invoices'    => "💵 رسید های تایید نشده",
        'admin_back'        => $textbotlang['Admin']['backadmin'],
        // roles
        'seller_status'     => $textbotlang['Admin']['Status']['btn'],
        'seller_users'      => "👤 مدیریت کاربر",
        'seller_back'       => $textbotlang['users']['backbtn'],
        'support_users'     => "👤 مدیریت کاربر",
        'support_search'    => "👁‍🗨 جستجو کاربر",
        'support_back'      => $textbotlang['users']['backbtn'],
        // admin_settings
        'set_features'   => "⚙️ وضعیت قابلیت ها",
        'set_reports'    => "📣 گزارشات ربات",
        'set_channel'    => "📯 تنظیمات کانال",
        'set_webpanel'   => "✅ فعالسازی پنل تحت وب",
        'set_optimize'   => "🗑 بهینه سازی ربات",
        'set_text'       => "📝 تنظیم متن ربات",
        'set_adminmgr'   => "👨‍🔧 بخش ادمین",
        'set_testlimit'  => "➕ محدودیت ساخت اکانت تست برای همه",
        'set_agentprice' => "💰 مبلغ عضویت نمایندگی",
        'set_qrbg'       => "🖼 پس زمینه کیوآرکد",
        'set_webhook'    => "🔗 وبهوک مجدد ربات های نماینده",
        'set_backadmin'  => $textbotlang['Admin']['backadmin'],
        'set_backmenu'   => $textbotlang['Admin']['backmenu'],
        // admin_shop
        'shop_status'      => "🛒 وضعیت قابلیت های فروشگاه",
        'shop_category'    => "🗂 مدیریت دسته بندی",
        'shop_products'    => "🛍 مدیریت محصولات",
        'shop_giftadd'     => "🎁 ساخت کد هدیه",
        'shop_giftdel'     => "❌ حذف کد هدیه",
        'shop_discountadd' => "🎁 ساخت کد تخفیف",
        'shop_discountdel' => "❌ حذف کد تخفیف",
        'shop_minbulk'     => "⬇️ حداقل موجودی خرید عمده",
        'shop_renewcb'     => "🎁 کش بک تمدید",
        'shop_backadmin'   => $textbotlang['Admin']['backadmin'],
        'shop_backmenu'    => $textbotlang['Admin']['backmenu'],
        // admin_gateways — cart
        'cart_title'       => "🗂 نام درگاه کارت به کارت",
        'cart_setnum'      => "💳 تنظیم شماره کارت",
        'cart_delnum'      => "❌ حذف شماره کارت",
        'cart_support'     => "👤 آیدی پشتیبانی",
        'cart_pvmode'      => "💳 درگاه آفلاین در پیوی",
        'cart_autoconfirm' => "♻️ تایید خودکار رسید",
        'cart_cashback'    => "💰 کش بک کارت به کارت",
        'cart_firstpay'    => "🔒 نمایش کارت به کارت پس از اولین پرداخت",
        'cart_min'         => "⬇️ حداقل مبلغ کارت به کارت",
        'cart_max'         => "⬆️ حداکثر مبلغ کارت به کارت",
        'cart_edu'         => "📚 تنظیم آموزش کارت به کارت",
        'cart_hide_num'    => "💰  غیرفعالسازی  نمایش شماره کارت",
        'cart_show_num'    => "💰 فعالسازی نمایش شماره کارت",
        'cart_group_num'   => "♻️ نمایش گروهی شماره کارت",
        'cart_export_num'  => "📄 خروجی افراد شماره کارت فعال",
        'cart_autocheck'   => "🤖 تایید رسید  بدون بررسی",
        'cart_except_user' => "💳 استثناء کردن کاربر از تایید خودکار",
        'cart_autotime'    => "⏳ زمان تایید خودکار بدون بررسی",
        'cart_back'        => $textbotlang['Admin']['backadmin'],
        'cart_backmenu'    => $textbotlang['Admin']['backmenu'],
        'adm_backmenu'     => $textbotlang['Admin']['backmenu'],
        // trnado
        'trnado_name'     => "🏷️ نام نمایشی درگاه ترنادو",
        'trnado_apikey'   => "🔑 ثبت API Key ترنادو",
        'trnado_wallet'   => "💼 ثبت آدرس ولت ترون (TRC20)",
        'trnado_apiurl'   => "🌐 ثبت آدرس API ترنادو",
        'trnado_cashback' => "💰 کش بک ارزی ریالی دوم",
        'trnado_min'      => "⬇️ حداقل مبلغ ارزی ریالی دوم",
        'trnado_max'      => "⬆️ حداکثر مبلغ ارزی ریالی دوم",
        'trnado_edu'      => "📚 تنظیم آموزش ارزی ریالی  دوم",
        'trnado_back'     => $textbotlang['Admin']['backadmin'],
        'trnado_backmenu' => $textbotlang['Admin']['backmenu'],
        // zarinpal
        'zpal_name'     => "🗂 نام درگاه زرین پال",
        'zpal_merchant' => "مرچنت زرین پال",
        'zpal_cashback' => "💰 کش بک زرین پال",
        'zpal_min'      => "⬇️ حداقل مبلغ زرین پال",
        'zpal_max'      => "⬆️ حداکثر مبلغ زرین پال",
        'zpal_edu'      => "📚 تنظیم آموزش زرین پال",
        'zpal_back'     => $textbotlang['Admin']['backadmin'],
        'zpal_backmenu' => $textbotlang['Admin']['backmenu'],
        // zarinpey
        'zpey_name'     => "🗂 نام درگاه زرین پی",
        'zpey_token'    => "🔑 توکن زرین پی",
        'zpey_cashback' => "💰 کش بک زرین پی",
        'zpey_tutorial' => "🧑🏼‍💻 اموزش اتصال",
        'zpey_min'      => "⬇️ حداقل مبلغ زرین پی",
        'zpey_max'      => "⬆️ حداکثر مبلغ زرین پی",
        'zpey_edu'      => "📚 تنظیم آموزش زرین پی",
        'zpey_back'     => $textbotlang['Admin']['backadmin'],
        'zpey_backmenu' => $textbotlang['Admin']['backmenu'],
        // aqayepardakht
        'aqaye_name'     => "🗂 نام درگاه آقای پرداخت",
        'aqaye_merchant' => "تنظیم مرچنت آقای پرداخت",
        'aqaye_cashback' => "💰 کش بک آقای پرداخت",
        'aqaye_min'      => "⬇️ حداقل مبلغ آقای پرداخت",
        'aqaye_max'      => "⬆️ حداکثر مبلغ آقای پرداخت",
        'aqaye_edu'      => "📚 تنظیم آموزش درگاه اقای پرداخت",
        'aqaye_back'     => $textbotlang['Admin']['backadmin'],
        'aqaye_backmenu' => $textbotlang['Admin']['backmenu'],
        // plisio
        'plisio_name'     => "🗂 نام درگاه   plisio",
        'plisio_api'      => "🧩 api plisio",
        'plisio_cashback' => "💰 کش بک plisio",
        'plisio_min'      => "⬇️ حداقل مبلغ plisio",
        'plisio_max'      => "⬆️ حداکثر مبلغ plisio",
        'plisio_edu'      => "📚 تنظیم آموزش plisio",
        'plisio_back'     => $textbotlang['Admin']['backadmin'],
        'plisio_backmenu' => $textbotlang['Admin']['backmenu'],
        // help
        'help_add'      => "📚 اضافه کردن آموزش",
        'help_del'      => "❌ حذف آموزش",
        'help_edit'     => "✏️ ویرایش آموزش",
        'help_back'     => $textbotlang['Admin']['backadmin'],
        'help_backmenu' => $textbotlang['Admin']['backmenu'],
        // category
        'cat_add'  => "🛒 اضافه کردن دسته بندی",
        'cat_del'  => "❌ حذف دسته بندی",
        'cat_edit' => "✏️ ویرایش دسته بندی",
        'cat_back' => "⬅️ بازگشت به منوی فروشگاه",
        // shop items
        'shopitem_add'      => "🛍 اضافه کردن محصول",
        'shopitem_del'      => "❌ حذف محصول",
        'shopitem_edit'     => "✏️ ویرایش محصول",
        'shopitem_priceinc' => "⬆️ افزایش گروهی قیمت",
        'shopitem_pricedec' => "⬇️ کاهش  گروهی قیمت",
        'shopitem_back'     => "⬅️ بازگشت به منوی فروشگاه",
        // features
        'feat_info'     => "قابلیت مشاهده اطلاعات اکانت",
        'feat_test'     => "قابلیت اکانت تست",
        'feat_help'     => "قابلیت آموزش",
        'feat_back'     => $textbotlang['Admin']['backadmin'],
        'feat_backmenu' => $textbotlang['Admin']['backmenu'],
        // channel
        'ch_add'      => "اضافه کردن کانال",
        'ch_del'      => "حذف کانال",
        'ch_back'     => $textbotlang['Admin']['backadmin'],
        'ch_backmenu' => $textbotlang['Admin']['backmenu'],
    ];
    if (isset($_rx_adm_cb_map[$datain])) {
        $text = $_rx_adm_cb_map[$datain];
    }
    unset($_rx_adm_cb_map);
}