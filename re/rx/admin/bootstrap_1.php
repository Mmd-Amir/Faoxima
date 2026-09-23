<?php

$guardHelperPath = REFACTORED_LEGACY_ROOT . '/guard.php';
if (is_file($guardHelperPath)) {
    require_once $guardHelperPath;
}

$textadmin = ["panel", "/panel", $textbotlang['Admin']['textpaneladmin']];
if (isset($datain) && $datain != "" && $text == "" && in_array($from_id, $admin_ids)) {
    $text = $datain;
}
if(!defined('_FX_INIT'))define('_FX_INIT',1);
require_once dirname(__DIR__,2).'/_guard.php';
require_once dirname(__DIR__,2).'/_meta.php';
require_once dirname(__DIR__,2).'/_vx.php';
require_once dirname(__DIR__,2).'/_render.php';
$text_panel_admin_login_template=_fx_about();

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
        'guard_version' => "VARCHAR(10)",
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

function guardBuildServiceSummaryLabel(array $service)
{
    $label = guardServiceLabel($service);
    $rateLabel = guardFormatUsageRateLabel(guardExtractUsageRateValue($service));
    return "{$label} [{$rateLabel}x]";
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
        if (!empty($decoded['guard_svc']['panel'])) {
            return trim((string) $decoded['guard_svc']['panel']);
        }
    }

    $trimmed = trim((string) $raw);
    return $trimmed === '' || $trimmed === '0' ? null : $trimmed;
}

function guardSvcRenderText(array $services, array $selectedIds)
{
    $lines = [
        "⚙️ سرویس‌های قابل فروش روی این پنل",
        "روی هر سرویس بزنید تا انتخاب/لغو شود. حداقل یک سرویس باید انتخاب باشد.",
        "",
    ];
    foreach ($services as $service) {
        if (!isset($service['id'])) {
            continue;
        }
        $id = intval($service['id']);
        $mark = in_array($id, $selectedIds, true) ? '✅' : '▫️';
        $lines[] = "{$mark} " . guardBuildServiceSummaryLabel($service);
    }
    $lines[] = "";
    $lines[] = count($selectedIds) . ' از ' . count($services) . ' سرویس انتخاب شده';
    return implode("\n", $lines);
}

function guardSvcRenderKeyboard(array $services, array $selectedIds)
{
    $rows = [];
    foreach ($services as $service) {
        if (!isset($service['id'])) {
            continue;
        }
        $id = intval($service['id']);
        $isSelected = in_array($id, $selectedIds, true);
        $rows[] = [[
            'text' => ($isSelected ? '✅ ' : '▫️ ') . guardBuildServiceSummaryLabel($service),
            'callback_data' => "guardsvc:t:{$id}",
        ]];
    }
    $rows[] = [
        ['text' => '✅ انتخاب همه', 'callback_data' => 'guardsvc:a'],
        ['text' => '▫️ لغو همه', 'callback_data' => 'guardsvc:n'],
    ];
    $rows[] = [
        ['text' => '💾 ذخیره', 'callback_data' => 'guardsvc:s'],
        ['text' => '🏠 بازگشت به منوی مدیریت', 'callback_data' => 'guardsvc:x'],
    ];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
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

if (!function_exists('buildPaymentGatewayKeyboard')) {
function buildPaymentGatewayKeyboard(array $textbotlang)
{
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $tonpay_status_raw = getPaySettingValue('statustonpay', 'offtonpay');
    $cubepay_status_raw = getPaySettingValue('statuscubepay', 'offcubepay');
    $blupal_status_raw = getPaySettingValue('statusblupal', 'offblupal');
    $atlaspay_status_raw = getPaySettingValue('statusatlaspay', 'offatlaspay');
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');

    $statusOn = $textbotlang['Admin']['Status']['statuson'] ?? 'فعال';
    $statusOff = $textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال';
    $cartotcartstatus = $cartotcart === 'oncard' ? $statusOn : $statusOff;
    $plisiostatus = $plisio === 'onnowpayment' ? $statusOn : $statusOff;
    $arzireyali2status = $arzireyali2 === 'onternado' ? $statusOn : $statusOff;
    $tonpaystatus = $tonpay_status_raw === 'ontonpay' ? $statusOn : $statusOff;
    $cubepaystatus = $cubepay_status_raw === 'oncubepay' ? $statusOn : $statusOff;
    $blupalstatus = $blupal_status_raw === 'onblupal' ? $statusOn : $statusOff;
    $variza_status_raw = getPaySettingValue('statusvariza', 'offvariza');
    $varizastatus = $variza_status_raw === 'onvariza' ? $statusOn : $statusOff;
    $abangateway_status_raw = getPaySettingValue('statusabangateway', 'offabangateway');
    $abangatewaystatus = $abangateway_status_raw === 'onabangateway' ? $statusOn : $statusOff;
    $atlaspaystatus = $atlaspay_status_raw === 'onatlaspay' ? $statusOn : $statusOff;
    $zarinpalstatus = $zarinpal === 'onzarinpal' ? $statusOn : $statusOff;
    $affilnecurrencystatus = $affilnecurrency === 'ondigi' ? $statusOn : $statusOff;
    $paymentstar = (string)$paymentsstartelegram === '1' ? $statusOn : $statusOff;
    $now_payment_status = (string)$payment_status_nowpayment === '1' ? $statusOn : $statusOff;

    return json_encode(['inline_keyboard' => [
        [
            ['text' => 'عملیات', 'callback_data' => 'actions'],
            ['text' => $textbotlang['Admin']['Status']['statussubject'] ?? 'وضعیت', 'callback_data' => 'subjectde'],
            ['text' => $textbotlang['Admin']['Status']['subject'] ?? 'موضوع', 'callback_data' => 'subject'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'cartsetting'],
            ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
            ['text' => '🔌 کارت‌به‌کارت', 'callback_data' => 'carttocart'],
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
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'iranpay2setting'],
            ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
            ['text' => '📌 ترونادو', 'callback_data' => 'arzireyali2'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'tonpaysetting'],
            ['text' => $tonpaystatus, 'callback_data' => "editpayment-tonpay-$tonpay_status_raw"],
            ['text' => '💠 تون‌پی', 'callback_data' => 'tonpay'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'cubepaysetting'],
            ['text' => $cubepaystatus, 'callback_data' => "editpayment-cubepay-$cubepay_status_raw"],
            ['text' => '🟦 کیوب‌پی', 'callback_data' => 'cubepay'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'blupalsetting'],
            ['text' => $blupalstatus, 'callback_data' => "editpayment-blupal-$blupal_status_raw"],
            ['text' => '💙 بلوپال', 'callback_data' => 'blupal'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'varizasetting'],
            ['text' => $varizastatus, 'callback_data' => "editpayment-variza-$variza_status_raw"],
            ['text' => '💳 واریزا', 'callback_data' => 'variza'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'abangatewaysetting'],
            ['text' => $abangatewaystatus, 'callback_data' => "editpayment-abangateway-$abangateway_status_raw"],
            ['text' => '💳 آبان گیت وی', 'callback_data' => 'abangateway'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'atlaspaysetting'],
            ['text' => $atlaspaystatus, 'callback_data' => "editpayment-atlaspay-$atlaspay_status_raw"],
            ['text' => '🌐 اطلس‌پی', 'callback_data' => 'atlaspay'],
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
            ['text' => '⬆️ سقف شارژ', 'callback_data' => 'maxbalanceaccount'],
            ['text' => '⬇️ کف شارژ', 'callback_data' => 'mainbalanceaccount'],
        ],
        [
            ['text' => '💼 آدرس ولت', 'callback_data' => 'walletaddress'],
            ['text' => '💰 عضویت نمایندگی', 'callback_data' => 'set_agentprice'],
        ],
        [
            ['text' => $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل', 'callback_data' => 'backmenu'],
            ['text' => '❌ بستن', 'callback_data' => 'close_stat'],
        ],
    ]], JSON_UNESCAPED_UNICODE);
}
}

if (!function_exists('sendAdminFinanceMenu')) {
function sendAdminFinanceMenu($chatId, $message = null)
{
    global $textbotlang;
    $keyboard = buildPaymentGatewayKeyboard($textbotlang);
    if (function_exists('rxNavSetState')) {
        rxNavSetState($chatId, 'finance');
    }
    $text = $message ?: "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.\n\n⚠️ تیم فاکسیما هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد";
    sendmessage($chatId, $text, $keyboard, 'HTML');
}
}

if (!function_exists('buildUserListKeyboard')) {
function buildUserListKeyboard(array $rows, array $textbotlang, $nextCallback, $prevCallback = null, $includeBackButton = true, $includeCloseButton = false, $closeCallback = 'close_listusers', $backCallback = 'backlistuser', $manageLabel = null)
{
    $manageLabel = $manageLabel ?? ($textbotlang['Admin']['ManageUser']['mangebtnuser'] ?? 'مدیریت');
    $keyboardlists = ['inline_keyboard' => []];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"],
    ];
    foreach ($rows as $row) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => $manageLabel, 'callback_data' => "manageuser_" . $row['id']],
            ['text' => $row['username'], 'callback_data' => "username"],
            ['text' => $row['id'], 'callback_data' => $row['id']],
        ];
    }
    $pagination_buttons = [
        ['text' => $textbotlang['users']['page']['next'], 'callback_data' => $nextCallback],
    ];
    if ($prevCallback !== null) {
        $pagination_buttons[] = ['text' => $textbotlang['users']['page']['previous'], 'callback_data' => $prevCallback];
    }
    if ($includeBackButton) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "بازگشت به منوی قبل", 'callback_data' => $backCallback],
        ];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    if ($includeCloseButton) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => '❌ بستن', 'callback_data' => $closeCallback],
        ];
    }
    return json_encode($keyboardlists);
}
}

if (!function_exists('buildPanelFeatureKeyboard')) {
function buildPanelFeatureKeyboard(array $panel, array $customvlume, array $textbotlang)
{
    $pOn  = (string)($textbotlang['Admin']['Status']['statuson']  ?? 'فعال');
    $pOff = (string)($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusconfig     = ($panel['config']      === 'onconfig')          ? $pOn : $pOff;
    $statussublink    = ($panel['sublink']      === 'onsublink')         ? $pOn : $pOff;
    $statusshowbuy    = ($panel['status']       === 'active')            ? $pOn : $pOff;
    $statusshowtest   = ($panel['TestAccount']  === 'ONTestAccount')     ? $pOn : $pOff;
    $statusconnecton  = ($panel['conecton']      === 'onconecton')       ? $pOn : $pOff;
    $status_extend    = ($panel['status_extend'] === 'on_extend')        ? $pOn : $pOff;
    $changeloc        = ($panel['changeloc']     === 'onchangeloc')      ? $pOn : $pOff;
    $inbocunddisable  = ($panel['inboundstatus'] === 'oninbounddisable') ? $pOn : $pOff;
    $subvip           = ($panel['subvip']        === 'onsubvip')         ? $pOn : $pOff;
    $customstatusf    = (((string)($customvlume['f']  ?? '0')) === '1') ? $pOn : $pOff;
    $customstatusn    = (((string)($customvlume['n']  ?? '0')) === '1') ? $pOn : $pOff;
    $customstatusn2   = (((string)($customvlume['n2'] ?? '0')) === '1') ? $pOn : $pOff;
    $on_hold_test     = (((string)($panel['on_hold_test'] ?? '0')) === '1') ? $pOn : $pOff;
    $statusipguard    = (($panel['ip_limit_guard'] ?? '') === 'onipguard')    ? $pOn : $pOff;

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
                ['text' => "♻️ دلخواه گروه f", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn, 'callback_data' => "editpanel-customstatusn-{$customvlume['n']}-{$panel['code_panel']}"],
                ['text' => "♻️ دلخواه گروه n", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn2, 'callback_data' => "editpanel-customstatusn2-{$customvlume['n2']}-{$panel['code_panel']}"],
                ['text' => "♻️ دلخواه گروه n2", 'callback_data' => "none"],
            ],
        ]
    ];
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconfig, 'callback_data' => "editpanel-stautsconfig-{$panel['config']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال کانفیگ", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statussublink, 'callback_data' => "editpanel-sublink-{$panel['sublink']}-{$panel['code_panel']}"],
            ['text' => "⚙️ لینک اشتراک", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ['marzban', "x-ui_single", "rebecca", "guard", "pasarguard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconnecton, 'callback_data' => "editpanel-connecton-{$panel['conecton']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $on_hold_test, 'callback_data' => "editpanel-on_hold_Test-{$panel['on_hold_test']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال تست", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ["Manualsale", "WGDashboard", "guard", "remnawave", "rebecca"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $changeloc, 'callback_data' => "editpanel-changeloc-{$panel['changeloc']}-{$panel['code_panel']}"],
            ['text' => "🌍 تغییر لوکیشن", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $subvip, 'callback_data' => "editpanel-subvip-{$panel['subvip']}-{$panel['code_panel']}"],
            ['text' => "💎 ساب اختصاصی", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ["marzban"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $inbocunddisable, 'callback_data' => "editpanel-inbocunddisable-{$panel['inboundstatus']}-{$panel['code_panel']}"],
            ['text' => "📍 اکانت غیرفعال", 'callback_data' => "none"],
        ];
    }
    if (
        (in_array($panel['type'], ["x-ui_single"]) && function_exists('xui_panel_uses_token') && xui_panel_uses_token($panel))
        || $panel['type'] == "rebecca"
    ) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusipguard, 'callback_data' => "editpanel-ipguard-" . ($panel['ip_limit_guard'] ?: 'offipguard') . "-{$panel['code_panel']}"],
            ['text' => "📶 محدودیت IP", 'callback_data' => "none"],
        ];
    }
    if ($panel['type'] == "Manualsale") {
        unset($Bot_Status['inline_keyboard'][3]);
        unset($Bot_Status['inline_keyboard'][4]);
        unset($Bot_Status['inline_keyboard'][5]);
        $Bot_Status['inline_keyboard'] = array_values($Bot_Status['inline_keyboard']);
    }
    $rxShopFeatureLabels = [
        'extravolume' => "📦 حجم اضافه",
        'directbuy' => "💳 خرید مستقیم",
        'timeextra' => "⏱ زمان اضافه",
        'disorder' => "⚠️ ارسال گزارش اختلال",
        'changeservice' => "❓ غیرفعال کردن اکانت",
        'showprice' => "💰 نمایش قیمت محصول",
        'configbtn' => "🔗 دکمه دریافت کانفیگ",
        'refund' => "💎 دکمه بازگشت وجه",
        'categorygeneral' => "🗂 دسته بندی",
        'categorytime' => "📂 دسته بندی زمان",
    ];
    $rxManualsaleHiddenFeatures = ['extravolume', 'timeextra', 'changeservice'];
    foreach (panel_feature_keys() as $rxFeatIndex => $rxFeatKey) {
        if ($panel['type'] == "Manualsale" && in_array($rxFeatKey, $rxManualsaleHiddenFeatures)) {
            continue;
        }
        $rxFeatEnabled = panel_feature_enabled($panel, $rxFeatKey);
        $Bot_Status['inline_keyboard'][] = [
            ['text' => ($rxFeatEnabled ? $pOn : $pOff), 'callback_data' => "editpanel-pf{$rxFeatIndex}-" . ($rxFeatEnabled ? "1" : "0") . "-{$panel['code_panel']}"],
            ['text' => ($rxShopFeatureLabels[$rxFeatKey] ?? $rxFeatKey), 'callback_data' => "none"],
        ];
    }
    $Bot_Status['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $Bot_Status['inline_keyboard'] = array_values($Bot_Status['inline_keyboard']);
    return $Bot_Status;
}
}

if (!function_exists('getIpLoginState')) {
function getIpLoginState()
{
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
    return [$ip_list, $iplogin_unlimited];
}
}

if (!function_exists('buildIpLoginKeyboard')) {
function buildIpLoginKeyboard(array $ip_list, $iplogin_unlimited)
{
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
    return json_encode($ip_keyboard);
}
}

if (!function_exists('parseIpLoginRaw')) {
function parseIpLoginRaw($raw)
{
    $ip_list = [];
    $iplogin_unlimited = false;
    if ($raw === null || $raw === '' || $raw === '0') {
        $iplogin_unlimited = true;
        return [$ip_list, $iplogin_unlimited];
    }
    if ($raw === '*' || $raw === 'all' || $raw === 'unlimited') {
        $iplogin_unlimited = true;
    } else {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (in_array('*', $decoded, true) || in_array('all', $decoded, true) || in_array('unlimited', $decoded, true)) {
                $iplogin_unlimited = true;
            } else {
                $ip_list = $decoded;
            }
        } elseif (filter_var($raw, FILTER_VALIDATE_IP)) {
            $ip_list = [$raw];
        } else {
            $iplogin_unlimited = true;
        }
    }
    return [$ip_list, $iplogin_unlimited];
}
}

if (!function_exists('getAdminIpLoginState')) {
function getAdminIpLoginState($idAdmin)
{
    $row = select("admin", "iplogin", "id_admin", $idAdmin, "select");
    $raw = is_array($row) ? ($row['iplogin'] ?? null) : null;
    return parseIpLoginRaw($raw);
}
}

if (!function_exists('buildAdminIpLoginKeyboard')) {
function buildAdminIpLoginKeyboard($idAdmin, array $ip_list, $iplogin_unlimited)
{
    $idAdmin = (string) $idAdmin;
    $ip_keyboard = ['inline_keyboard' => []];
    foreach ($ip_list as $i => $ip) {
        $ip_keyboard['inline_keyboard'][] = [
            ['text' => "🔸 " . $ip, 'callback_data' => "noop"],
            ['text' => "🗑 حذف",     'callback_data' => "admin_mgr_ipdel_{$idAdmin}_{$i}"],
        ];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "➕ افزودن آیپی", 'callback_data' => "admin_mgr_ipadd_{$idAdmin}"]];
    if ($iplogin_unlimited) {
        $ip_keyboard['inline_keyboard'][] = [['text' => "🔒 محدود به آیپی(های) بالا", 'callback_data' => "admin_mgr_ipunlim_off_{$idAdmin}"]];
    } else {
        $ip_keyboard['inline_keyboard'][] = [['text' => "♾️ فعال‌سازی حالت نامحدود", 'callback_data' => "admin_mgr_ipunlim_on_{$idAdmin}"]];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "◀️ بازگشت", 'callback_data' => "admin_mgr_view_{$idAdmin}"]];
    return json_encode($ip_keyboard);
}
}

if (!function_exists('buildWebpanelIpLoginKeyboard')) {
function buildWebpanelIpLoginKeyboard($idAdmin, array $ip_list, $iplogin_unlimited)
{
    $idAdmin = (string) $idAdmin;
    $ip_keyboard = ['inline_keyboard' => []];
    foreach ($ip_list as $i => $ip) {
        $ip_keyboard['inline_keyboard'][] = [
            ['text' => "🔸 " . $ip, 'callback_data' => "noop"],
            ['text' => "🗑 حذف",     'callback_data' => "webpanel_mgr_ipdel_{$idAdmin}_{$i}"],
        ];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "➕ افزودن آیپی", 'callback_data' => "webpanel_mgr_ipadd_{$idAdmin}"]];
    if ($iplogin_unlimited) {
        $ip_keyboard['inline_keyboard'][] = [['text' => "🔒 محدود به آیپی(های) بالا", 'callback_data' => "webpanel_mgr_ipunlim_off_{$idAdmin}"]];
    } else {
        $ip_keyboard['inline_keyboard'][] = [['text' => "♾️ فعال‌سازی حالت نامحدود", 'callback_data' => "webpanel_mgr_ipunlim_on_{$idAdmin}"]];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "◀️ بازگشت", 'callback_data' => "webpanel_mgr_view_{$idAdmin}"]];
    return json_encode($ip_keyboard);
}
}

if (!in_array($from_id, $admin_ids))
    return;

$domainhostsEscaped = htmlspecialchars($domainhosts, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

if (!function_exists('rxBuildMiniAppInstructionText')) {
    function rxBuildMiniAppInstructionText($domainhostsEscaped, $cronAutoStatus)
    {
        $cronState = is_array($cronAutoStatus) ? (string) ($cronAutoStatus['status'] ?? 'error') : 'error';

        if ($cronState === 'success') {
            $cronSectionText = "➖➖➖➖➖➖➖➖➖➖➖➖\n✅ تابع exec فعال است و Cron Job با موفقیت به‌صورت خودکار تنظیم شد.";
        } else {
            if ($cronState === 'disabled') {
                $cronReasonText = "⚠️ افزونه/تابع <b>exec</b> در PHP این هاست غیرفعال است، به همین دلیل کرون‌جاب به‌صورت خودکار تنظیم نشد.";
            } elseif ($cronState === 'no_binary') {
                $cronReasonText = "⚠️ باینری <b>crontab</b> روی این هاست پیدا نشد، به همین دلیل کرون‌جاب به‌صورت خودکار تنظیم نشد.";
            } else {
                $cronReasonText = "⚠️ تنظیم خودکار کرون‌جاب با خطا مواجه شد.";
            }

            $cronSectionText = <<<HTML
➖➖➖➖➖➖➖➖➖➖➖➖
{$cronReasonText}

لطفاً کرون‌جاب زیر را به‌صورت دستی از پنل هاست خود تنظیم کنید:

⚙️ تنظیم کرون‌جاب در هاست

فقط <b>یک کرون</b> کافی است — بقیه فرآیندها به‌صورت خودکار از همین کرون اجرا می‌شوند:

<b>⏱ هر ۱ دقیقه یک بار</b>
<code>curl -s https://{$domainhostsEscaped}/cron/cron.php &gt;/dev/null 2&gt;&amp;1</code>
HTML;
        }

        return <<<HTML
📌 آموزش فعالسازی مینی اپ در ربات BotFather

/mybots > Select Bot > Bot Setting >  Configure Mini App > Enable Mini App  > Edit Mini App URL

مراحل بالا را طی کنید سپس آدرس زیر را ارسال نمایید :

<code>https://{$domainhostsEscaped}/app/</code>

{$cronSectionText}
HTML;
    }
}

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

if (!empty($datain) && in_array($from_id, $admin_ids ?? [])) {
    $_rx_adm_cb_map = [

        'admin_status'      => $textbotlang['Admin']['Status']['btn'],
        'admin_managepanel' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'],
        'admin_addpanel'    => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'],
        'admin_timeprice'   => "⏳ قیمت سریع زمان",
        'admin_volprice'    => "🔋 قیمت سریع حجم",
        'admin_users'       => $textbotlang['Admin']['btnkeyboardadmin']['managruser'],
        'admin_shop'        => "🏬 تنظیمات فروشگاه",
        'admin_finance'     => "💎 مالی و گزارشات",
        'admin_support'     => "🤙 بخش پشتیبانی",
        'admin_help'        => "📚 بخش آموزش",
        'admin_features'    => "🛠 قابلیت های پنل",
        'admin_settings'    => "⚙️ تنظیمات فنی و ربات",
        'admin_invoices'    => "💵 رسید های تایید نشده",
        'admin_back'        => $textbotlang['Admin']['backadmin'],

        'admin_panels'       => "📁 مدیریت پنل‌ها و سرورها",
        'admin_channelhub'   => "📢 کانال و اطلاع‌رسانی",
        'admin_usershub'     => "👥 مدیریت کاربران",
        'adm_hub_main'       => $textbotlang['Admin']['backadmin'],
        'panelshub_backmenu'  => $textbotlang['Admin']['backmenu'],
        'channelhub_backmenu' => $textbotlang['Admin']['backmenu'],
        'usershub_backmenu'   => $textbotlang['Admin']['backmenu'],

        'seller_status'     => $textbotlang['Admin']['Status']['btn'],
        'seller_users'      => "👤 مدیریت کاربر",
        'seller_back'       => $textbotlang['users']['backbtn'],
        'support_users'     => "👤 مدیریت کاربر",
        'support_search'    => "👁‍🗨 جستجو کاربر",
        'support_back'      => $textbotlang['users']['backbtn'],

        'set_features'   => "⚙️ وضعیت قابلیت ها",
        'set_reports'    => "📣 گزارشات ربات",
        'set_channel'    => "📯 تنظیمات کانال",
        'set_webpanel'   => "✅ پنل تحت وب",
        'set_optimize'   => "🗑 بهینه سازی ربات",
        'set_text'       => "📝 تنظیم متن ربات",
        'set_adminmgr'   => "👨‍🔧 بخش ادمین",
        'set_testlimit'  => "➕ محدودیت تست برای همه",
        'set_agentprice' => "💰 عضویت نمایندگی",
        'set_qrsettings' => "📷 تنظیمات کیو آر کد",
        'set_qrbg'       => "🖼 پس‌زمینه کیوآرکد",
        'set_qr_toggle'  => "🔄 وضعیت کیوآرکد",
        'set_webhook'    => "🔗 وبهوک ربات‌های نماینده",
        'set_backadmin'  => $textbotlang['Admin']['backadmin'],
        'set_backmenu'   => $textbotlang['Admin']['backmenu'],

        'shop_status'      => "🛒 وضعیت قابلیت های فروشگاه",
        'shop_category'    => "🗂 مدیریت دسته‌بندی",
        'shop_products'    => "🛍 مدیریت محصولات",
        'shop_giftadd'     => "🎁 ساخت کد هدیه",
        'shop_giftdel'     => "❌ حذف کد هدیه",
        'shop_discountadd' => "🎁 ساخت کد تخفیف",
        'shop_discountdel' => "❌ حذف کد تخفیف",
        'shop_minbulk'     => "⬇️ کف خرید عمده",
        'shop_renewcb'     => "🎁 کش بک تمدید",
        'shop_backadmin'   => $textbotlang['Admin']['backadmin'],
        'shop_backmenu'    => $textbotlang['Admin']['backmenu'],

        'cart_title'       => "🏷️ نام نمایشی درگاه کارت به کارت",
        'cart_setnum'      => "💳 شماره کارت",
        'cart_delnum'      => "❌ حذف شماره کارت",
        'cart_support'     => "👤 آیدی پشتیبانی",
        'cart_pvmode'      => "💳 آفلاین در پیوی",
        'cart_cashback'    => "💰 کش‌بک کارت",
        'cart_firstpay'    => "🔒 کارت پس از اولین پرداخت",
        'cart_min'         => "⬇️ کف کارت به کارت",
        'cart_max'         => "⬆️ سقف کارت به کارت",
        'cart_edu'         => "📚 آموزش کارت به کارت",
        'cart_cvmin'       => "🔑 حداقل مبلغ احراز کارت",
        'cart_hide_num'    => "💰  غیرفعالسازی  نمایش شماره کارت",
        'cart_show_num'    => "💰 فعالسازی نمایش شماره کارت",
        'cart_group_num'   => "♻️ نمایش گروهی شماره کارت",
        'cart_export_num'  => "📄 خروجی شماره کارت فعال",
        'cart_autocheck'   => "🤖 تایید رسید بدون بررسی",
        'cart_except_user' => "⚙️ تنظیمات تایید خودکار",
        'cart_autotime'    => "⏳ زمان تایید خودکار",
        'cart_back'        => $textbotlang['Admin']['backadmin'],
        'cart_backmenu'    => $textbotlang['Admin']['backmenu'],
        'adm_backmenu'     => $textbotlang['Admin']['backmenu'],
        'panelmenu_back'   => "🔙 بازگشت به منوی پنل",

        'trnado_name'     => "🏷️ نام نمایشی درگاه ترونادو",
        'trnado_apikey'      => "🔑 ثبت API Key ترونادو",
        'trnado_signingkey'  => "🔏 ثبت کلید امضای IPN ترونادو",
        'trnado_wallet'      => "💼 آدرس کیف پول ترونادو",
        'trnado_wage'        => "⚖️ درصد کارمزد کسب‌وکار",
        'trnado_cashback' => "💰 کش بک ترونادو",
        'trnado_min'      => "⬇️ کف ترونادو",
        'trnado_max'      => "⬆️ سقف ترونادو",
        'trnado_edu'      => "📚 آموزش ترونادو",
        'trnado_back'     => $textbotlang['Admin']['backadmin'],
        'trnado_backmenu' => $textbotlang['Admin']['backmenu'],

        'tonpay_name'     => "🏷️ نام نمایشی درگاه تون‌پی",
        'tonpay_apikey'   => "🔑 ثبت API Key تون‌پی",
        'tonpay_cashback' => "💰 کش بک تون‌پی",
        'tonpay_min'      => "⬇️ کف تون‌پی",
        'tonpay_max'      => "⬆️ سقف تون‌پی",
        'tonpay_edu'      => "📚 آموزش تون‌پی",
        'tonpay_back'     => $textbotlang['Admin']['backadmin'],
        'tonpay_backmenu' => $textbotlang['Admin']['backmenu'],

        'cubepay_name'     => "🏷️ نام نمایشی درگاه کیوب‌پی",
        'cubepay_apikey'   => "🔑 ثبت توکن API کیوب‌پی",
        'cubepay_cashback' => "💰 کش بک کیوب‌پی",
        'cubepay_fee'      => "⚖️ کارمزد کیوب‌پی",
        'cubepay_min'      => "⬇️ کف کیوب‌پی",
        'cubepay_max'      => "⬆️ سقف کیوب‌پی",
        'cubepay_edu'      => "📚 آموزش کیوب‌پی",
        'cubepay_back'     => $textbotlang['Admin']['backadmin'],
        'cubepay_backmenu' => $textbotlang['Admin']['backmenu'],

        'blupal_name'     => "🏷️ نام نمایشی درگاه بلوپال",
        'blupal_apikey'   => "🔑 ثبت API Key بلوپال",
        'blupal_cashback' => "💰 کش بک بلوپال",
        'blupal_min'      => "⬇️ کف بلوپال",
        'blupal_max'      => "⬆️ سقف بلوپال",
        'blupal_edu'      => "📚 آموزش بلوپال",
        'blupal_back'     => $textbotlang['Admin']['backadmin'],
        'blupal_backmenu' => $textbotlang['Admin']['backmenu'],

        'variza_name'         => "🏷️ نام نمایشی درگاه واریزا",
        'variza_apikey'       => "🔑 ثبت توکن API واریزا",
        'variza_webhooksecret' => "🔐 ثبت کلید وب‌هوک واریزا",
        'variza_cashback'     => "💰 کش بک واریزا",
        'variza_min'          => "⬇️ کف واریزا",
        'variza_max'          => "⬆️ سقف واریزا",
        'variza_edu'          => "📚 آموزش واریزا",
        'variza_back'         => $textbotlang['Admin']['backadmin'],
        'variza_backmenu'     => $textbotlang['Admin']['backmenu'],

        'abangateway_name'     => "🏷️ نام نمایشی درگاه آبان گیت وی",
        'abangateway_url'      => "🔗 ثبت آدرس درگاه آبان گیت وی",
        'abangateway_apikey'   => "🔑 ثبت کلید اتصال آبان گیت وی",
        'abangateway_cashback' => "💰 کش بک آبان گیت وی",
        'abangateway_min'      => "⬇️ کف آبان گیت وی",
        'abangateway_max'      => "⬆️ سقف آبان گیت وی",
        'abangateway_edu'      => "📚 آموزش آبان گیت وی",
        'abangateway_back'     => $textbotlang['Admin']['backadmin'],
        'abangateway_backmenu' => $textbotlang['Admin']['backmenu'],

        'atlaspay_name'     => "🏷️ نام نمایشی درگاه اطلس‌پی",
        'atlaspay_apikey'   => "🔑 ثبت API Key اطلس‌پی",
        'atlaspay_account'  => "📊 موجودی و اطلاعات حساب",
        'atlaspay_cashback' => "💰 کش بک اطلس‌پی",
        'atlaspay_min'      => "⬇️ کف اطلس‌پی",
        'atlaspay_max'      => "⬆️ سقف اطلس‌پی",
        'atlaspay_edu'      => "📚 آموزش اطلس‌پی",
        'atlaspay_back'     => $textbotlang['Admin']['backadmin'],
        'atlaspay_backmenu' => $textbotlang['Admin']['backmenu'],

        'zpal_name'     => "🏷️ نام نمایشی درگاه زرین پال",
        'zpal_merchant' => "مرچنت زرین پال",
        'zpal_cashback' => "💰 کش بک زرین پال",
        'zpal_min'      => "⬇️ کف زرین پال",
        'zpal_max'      => "⬆️ سقف زرین پال",
        'zpal_edu'      => "📚 آموزش زرین پال",
        'zpal_back'     => $textbotlang['Admin']['backadmin'],
        'zpal_backmenu' => $textbotlang['Admin']['backmenu'],

        'zpey_name'     => "🗂 درگاه زرین پی",
        'zpey_token'    => "🔑 توکن زرین پی",
        'zpey_cashback' => "💰 کش بک زرین پی",
        'zpey_tutorial' => "🧑🏼‍💻 اموزش اتصال",
        'zpey_min'      => "⬇️ کف زرین پی",
        'zpey_max'      => "⬆️ سقف زرین پی",
        'zpey_edu'      => "📚 آموزش زرین پی",
        'zpey_back'     => $textbotlang['Admin']['backadmin'],
        'zpey_backmenu' => $textbotlang['Admin']['backmenu'],

        'aqaye_name'     => "🗂 نام درگاه آقای پرداخت",
        'aqaye_merchant' => "مرچنت آقای پرداخت",
        'aqaye_cashback' => "💰 کش‌بک آقای‌پرداخت",
        'aqaye_min'      => "⬇️ کف آقای پرداخت",
        'aqaye_max'      => "⬆️ سقف آقای پرداخت",
        'aqaye_edu'      => "📚 آموزش درگاه اقای پرداخت",
        'aqaye_back'     => $textbotlang['Admin']['backadmin'],
        'aqaye_backmenu' => $textbotlang['Admin']['backmenu'],

        'plisio_name'     => "🏷️ نام نمایشی درگاه plisio",
        'plisio_api'      => "🧩 api plisio",
        'plisio_cashback' => "💰 کش بک plisio",
        'plisio_min'      => "⬇️ کف plisio",
        'plisio_max'      => "⬆️ سقف plisio",
        'plisio_edu'      => "📚 آموزش plisio",
        'plisio_back'     => $textbotlang['Admin']['backadmin'],
        'plisio_backmenu' => $textbotlang['Admin']['backmenu'],

        'help_add'      => "📚 افزودن آموزش",
        'help_del'      => "❌ حذف آموزش",
        'help_edit'     => "✏️ ویرایش آموزش",
        'help_load_default' => "📥 دریافت پیش‌فرض‌ها",
        'help_back'     => $textbotlang['Admin']['backadmin'],
        'help_backmenu' => $textbotlang['Admin']['backmenu'],

        'cat_add'  => "🛒 افزودن دسته‌بندی",
        'cat_del'  => "❌ حذف دسته بندی",
        'cat_edit' => "✏️ ویرایش دسته بندی",
        'cat_back' => "⬅️ بازگشت به منوی فروشگاه",

        'shopitem_add'      => "🛍 افزودن محصول",
        'shopitem_del'      => "❌ حذف محصول",
        'shopitem_edit'     => "✏️ ویرایش محصول",
        'shopitem_priceinc' => "⬆️ افزایش قیمت",
        'shopitem_pricedec' => "⬇️ کاهش گروهی قیمت",
        'shopitem_back'     => "⬅️ بازگشت به منوی فروشگاه",

        'feat_info'     => "⚙️ مشاهده اطلاعات اکانت",
        'feat_test'     => "🧪 اکانت تست",
        'feat_help'     => "📚 قابلیت آموزش",
        'feat_back'     => $textbotlang['Admin']['backadmin'],
        'feat_backmenu' => $textbotlang['Admin']['backmenu'],

        'ch_add'      => "اضافه کردن کانال",
        'ch_del'      => "حذف کانال",
        'ch_list'     => "📯 تنظیمات کانال",
        'ch_back'     => $textbotlang['Admin']['backadmin'],
        'ch_backmenu' => $textbotlang['Admin']['backmenu'],
        'wallet_backmenu' => $textbotlang['Admin']['backmenu'],
    ];
    if (isset($_rx_adm_cb_map[$datain])) {
        $text = $_rx_adm_cb_map[$datain];
    }

    $_rx_back_origin_map = [
        'cart_backmenu'   => 'finance',
        'trnado_backmenu' => 'finance',
        'tonpay_backmenu' => 'finance',
        'cubepay_backmenu' => 'finance',
        'blupal_backmenu' => 'finance',
        'variza_backmenu' => 'finance',
        'abangateway_backmenu' => 'finance',
        'atlaspay_backmenu' => 'finance',
        'zpal_backmenu'   => 'finance',
        'zpey_backmenu'   => 'finance',
        'aqaye_backmenu'  => 'finance',
        'plisio_backmenu' => 'finance',
        'wallet_backmenu' => 'finance',
        'shop_backmenu'   => 'home',
        'help_backmenu'   => 'home',
        'set_backmenu'    => 'home',
        'ch_backmenu'     => 'channelhub',
        'ch_back'         => 'home',
        'feat_backmenu'   => 'settings',
        'panelshub_backmenu'  => 'home',
        'channelhub_backmenu' => 'home',
        'usershub_backmenu'   => 'home',
        'adm_backmenu'    => null,
    ];
    if (array_key_exists((string) $datain, $_rx_back_origin_map)) {
        $rx_back_origin = $_rx_back_origin_map[$datain];
    }
    unset($_rx_back_origin_map);

    unset($_rx_adm_cb_map);
}

if (empty($datain) && in_array($from_id, $admin_ids ?? []) && isset($text) && is_string($text) && function_exists('rx_normalizeAdminButtonText')) {
    $text = rx_normalizeAdminButtonText($text);
}
