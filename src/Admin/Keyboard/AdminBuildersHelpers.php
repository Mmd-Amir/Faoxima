<?php

namespace Faoxima\Admin\Keyboard;

final class AdminBuildersHelpers
{
    public static function buildPaymentGatewayKeyboard(array $textbotlang)
{
    $cartotcart = \Faoxima\Core\Database\Db::getPaySettingValue('Cartstatus', 'offcard');
    $plisio = \Faoxima\Core\Database\Db::getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali2 = \Faoxima\Core\Database\Db::getPaySettingValue('statustarnado', 'offternado');
    $tonpay_status_raw = \Faoxima\Core\Database\Db::getPaySettingValue('statustonpay', 'offtonpay');
    $rialit_status_raw = \Faoxima\Core\Database\Db::getPaySettingValue('statusrialit', 'offrialit');
    $cubepay_status_raw = \Faoxima\Core\Database\Db::getPaySettingValue('statuscubepay', 'offcubepay');
    $blupal_status_raw = \Faoxima\Core\Database\Db::getPaySettingValue('statusblupal', 'offblupal');
    $atlaspay_status_raw = \Faoxima\Core\Database\Db::getPaySettingValue('statusatlaspay', 'offatlaspay');
    $zarinpal = \Faoxima\Core\Database\Db::getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $affilnecurrency = \Faoxima\Core\Database\Db::getPaySettingValue('digistatus', 'offdigi');
    $paymentsstartelegram = \Faoxima\Core\Database\Db::getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = \Faoxima\Core\Database\Db::getPaySettingValue('statusnowpayment', '0');

    $statusOn = $textbotlang['Admin']['Status']['statuson'] ?? 'فعال';
    $statusOff = $textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال';
    $cartotcartstatus = $cartotcart === 'oncard' ? $statusOn : $statusOff;
    $plisiostatus = $plisio === 'onnowpayment' ? $statusOn : $statusOff;
    $arzireyali2status = $arzireyali2 === 'onternado' ? $statusOn : $statusOff;
    $tonpaystatus = $tonpay_status_raw === 'ontonpay' ? $statusOn : $statusOff;
    $rialitstatus = $rialit_status_raw === 'onrialit' ? $statusOn : $statusOff;
    $cubepaystatus = $cubepay_status_raw === 'oncubepay' ? $statusOn : $statusOff;
    $blupalstatus = $blupal_status_raw === 'onblupal' ? $statusOn : $statusOff;
    $variza_status_raw = \Faoxima\Core\Database\Db::getPaySettingValue('statusvariza', 'offvariza');
    $varizastatus = $variza_status_raw === 'onvariza' ? $statusOn : $statusOff;
    $abangateway_status_raw = \Faoxima\Core\Database\Db::getPaySettingValue('statusabangateway', 'offabangateway');
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
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'rialitsetting'],
            ['text' => $rialitstatus, 'callback_data' => "editpayment-rialit-$rialit_status_raw"],
            ['text' => '💚 ریالیت', 'callback_data' => 'rialit'],
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

    public static function sendAdminFinanceMenu($chatId, $message = null)
{
    global $textbotlang;
    $keyboard = self::buildPaymentGatewayKeyboard($textbotlang);
    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($chatId, 'finance');
    }
    $text = $message ?: "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.\n\n⚠️ تیم فاکسیما هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد";
    sendmessage($chatId, $text, $keyboard, 'HTML');
}

    public static function buildUserListKeyboard(array $rows, array $textbotlang, $nextCallback, $prevCallback = null, $includeBackButton = true, $includeCloseButton = false, $closeCallback = 'close_listusers', $backCallback = 'backlistuser', $manageLabel = null)
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

    public static function buildPanelFeatureKeyboard(array $panel, array $customvlume, array $textbotlang)
{
    $pOn  = (string)($textbotlang['Admin']['Status']['statuson']  ?? 'فعال');
    $pOff = (string)($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusconfig     = ($panel['config']      === 'onconfig')          ? $pOn : $pOff;
    $statussublink    = ($panel['sublink']      === 'onsublink')         ? $pOn : $pOff;
    $autofilevalue    = in_array(($panel['auto_file'] ?? ''), ['onautofile', 'offautofile'], true) ? $panel['auto_file'] : 'offautofile';
    $statusautofile   = ($autofilevalue === 'onautofile') ? $pOn : $pOff;
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
    if ($panel['type'] != "Manualsale") {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusautofile, 'callback_data' => "editpanel-autofile-{$autofilevalue}-{$panel['code_panel']}"],
            ['text' => "📎 فایل خودکار", 'callback_data' => "none"],
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
        (in_array($panel['type'], ["x-ui_single"]) && method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_panel_uses_token') && \Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($panel))
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
    foreach (\Faoxima\Services\Panels\PanelFeatures::panel_feature_keys() as $rxFeatIndex => $rxFeatKey) {
        if ($panel['type'] == "Manualsale" && in_array($rxFeatKey, $rxManualsaleHiddenFeatures)) {
            continue;
        }
        $rxFeatEnabled = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($panel, $rxFeatKey);
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

    public static function getIpLoginState()
{
    $setting_row = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select");
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

    public static function buildIpLoginKeyboard(array $ip_list, $iplogin_unlimited)
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

    public static function parseIpLoginRaw($raw)
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

    public static function getAdminIpLoginState($idAdmin)
{
    $row = \Faoxima\Core\Database\Db::select("admin", "iplogin", "id_admin", $idAdmin, "select");
    $raw = is_array($row) ? ($row['iplogin'] ?? null) : null;
    return self::parseIpLoginRaw($raw);
}

    public static function buildAdminIpLoginKeyboard($idAdmin, array $ip_list, $iplogin_unlimited)
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

    public static function buildWebpanelIpLoginKeyboard($idAdmin, array $ip_list, $iplogin_unlimited)
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
