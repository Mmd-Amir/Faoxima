<?php

    $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => "❌ بستن لیست", 'callback_data' => "colselist"], 'colselist', $_rx_pay_styles)
    ];
    $step_payment = json_encode($step_payment);
$keyboardhelpadmin = rx_kb_encode([
        [
            rx_kb_style(['text' => "📚 افزودن آموزش", 'callback_data' => 'help_add'], 'help_add', $_rx_helpa_styles),
            rx_kb_style(['text' => "❌ حذف آموزش", 'callback_data' => 'help_del'], 'help_del', $_rx_helpa_styles)
        ],
        [rx_kb_style(['text' => "✏️ ویرایش آموزش", 'callback_data' => 'help_edit'], 'help_edit', $_rx_helpa_styles),
         rx_kb_style(['text' => "📥 دریافت پیش‌فرض‌ها", 'callback_data' => 'help_load_default'], 'help_load_default', $_rx_helpa_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'help_back'], 'help_back', $_rx_helpa_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'help_backmenu'], 'help_backmenu', $_rx_helpa_styles)
        ]
    ]);
$shopkeyboard = rx_kb_encode([
        [
            rx_kb_style(['text' => "🗂 مدیریت دسته‌بندی", 'callback_data' => 'shop_category'], 'shop_category', $_rx_shp_styles),
            rx_kb_style(['text' => "🛍 مدیریت محصولات", 'callback_data' => 'shop_products'], 'shop_products', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => "🎁 ساخت کد هدیه", 'callback_data' => 'shop_giftadd'], 'shop_giftadd', $_rx_shp_styles),
            rx_kb_style(['text' => "❌ حذف کد هدیه", 'callback_data' => 'shop_giftdel'], 'shop_giftdel', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => "🎁 ساخت کد تخفیف", 'callback_data' => 'shop_discountadd'], 'shop_discountadd', $_rx_shp_styles),
            rx_kb_style(['text' => "❌ حذف کد تخفیف", 'callback_data' => 'shop_discountdel'], 'shop_discountdel', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => "⬇️ کف خرید عمده", 'callback_data' => 'shop_minbulk'], 'shop_minbulk', $_rx_shp_styles),
            rx_kb_style(['text' => "🎁 کش بک تمدید", 'callback_data' => 'shop_renewcb'], 'shop_renewcb', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'shop_backadmin'], 'shop_backadmin', $_rx_shp_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'shop_backmenu'], 'shop_backmenu', $_rx_shp_styles)
        ]
    ]);
$keyboard_Category_manage = rx_kb_encode([
        [
            rx_kb_style(['text' => "🛒 افزودن دسته‌بندی", 'callback_data' => 'cat_add'], 'cat_add', $_rx_cat_styles),
            rx_kb_style(['text' => "❌ حذف دسته بندی", 'callback_data' => 'cat_del'], 'cat_del', $_rx_cat_styles)
        ],
        [rx_kb_style(['text' => "✏️ ویرایش دسته بندی", 'callback_data' => 'cat_edit'], 'cat_edit', $_rx_cat_styles)],
        [rx_kb_style(['text' => "⬅️ بازگشت به منوی فروشگاه", 'callback_data' => 'cat_back'], 'cat_back', $_rx_cat_styles)]
    ]);
$keyboard_shop_manage = rx_kb_encode([
        [
            rx_kb_style(['text' => "🛍 افزودن محصول", 'callback_data' => 'shopitem_add'], 'shopitem_add', $_rx_prod_styles),
            rx_kb_style(['text' => "❌ حذف محصول", 'callback_data' => 'shopitem_del'], 'shopitem_del', $_rx_prod_styles)
        ],
        [rx_kb_style(['text' => "✏️ ویرایش محصول", 'callback_data' => 'shopitem_edit'], 'shopitem_edit', $_rx_prod_styles)],
        [
            rx_kb_style(['text' => "⬆️ افزایش قیمت", 'callback_data' => 'shopitem_priceinc'], 'shopitem_priceinc', $_rx_prod_styles),
            rx_kb_style(['text' => "⬇️ کاهش گروهی قیمت", 'callback_data' => 'shopitem_pricedec'], 'shopitem_pricedec', $_rx_prod_styles)
        ],
        [rx_kb_style(['text' => "⬅️ بازگشت به منوی فروشگاه", 'callback_data' => 'shopitem_back'], 'shopitem_back', $_rx_prod_styles)]
    ]);
$_rx_rules_btn = rx_kb_style(['text' => "✅ قوانین را می پذیرم", 'callback_data' => "acceptrule"], 'rules_accept', $_rx_nav_styles);
$confrimrolls = json_encode(['inline_keyboard' => [[$_rx_rules_btn]]]);
$request_contact = json_encode([
    'keyboard' => [
        [rx_kb_style(['text' => "☎️ ارسال شماره تلفن", 'request_contact' => true], 'contact_phone', $_rx_nav_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'contact_back'], 'contact_back', $_rx_nav_styles)]
    ],
    'resize_keyboard' => true
]);
$Feature_status = rx_kb_encode([
        [rx_kb_style(['text' => "⚙️ مشاهده اطلاعات اکانت", 'callback_data' => 'feat_info'], 'feat_info', $_rx_feat_styles)],
        [
            rx_kb_style(['text' => "🧪 اکانت تست", 'callback_data' => 'feat_test'], 'feat_test', $_rx_feat_styles),
            rx_kb_style(['text' => "📚 قابلیت آموزش", 'callback_data' => 'feat_help'], 'feat_help', $_rx_feat_styles)
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'feat_back'], 'feat_back', $_rx_feat_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'feat_backmenu'], 'feat_backmenu', $_rx_feat_styles)
        ]
    ]);
if (!function_exists('rx_get_channel_keyboard')) {
    function rx_get_channel_keyboard($forceInline = null)
    {
        global $pdo, $textbotlang, $_rx_chan_styles;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [];
        $rows[] = [
            rx_kb_style(['text' => "➕ افزودن کانال", 'callback_data' => 'ch_add'], 'ch_add', $styles)
        ];
        if (isset($pdo) && ($pdo instanceof PDO)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM channels ORDER BY id ASC");
                $stmt->execute();
                $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($channels)) {
                    foreach ($channels as $chan) {
                        $chanId = isset($chan['id']) && $chan['id'] !== '' ? $chan['id'] : $chan['link'];
                        $btnTitle = !empty($chan['remark']) ? $chan['remark'] : (!empty($chan['link']) ? $chan['link'] : 'کانال ' . $chanId);
                        $rows[] = [
                            rx_kb_style([
                                'text' => "📢 " . $btnTitle,
                                'callback_data' => "ch_manage_" . $chanId
                            ], 'ch_manage_' . $chanId, $styles)
                        ];
                    }
                }
            } catch (Throwable $e) {
                error_log('[rx_get_channel_keyboard] ' . $e->getMessage());
            }
        }
        $rows[] = [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'ch_backmenu'], 'ch_backmenu', $styles)
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

if (!function_exists('rx_get_channel_manage_keyboard')) {
    function rx_get_channel_manage_keyboard($channelId, $forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $channelId = (int)$channelId;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                rx_kb_style(['text' => "✏️ ویرایش عنوان دکمه", 'callback_data' => "ch_editremark_" . $channelId], 'ch_editremark', $styles),
                rx_kb_style(['text' => "🔗 ویرایش لینک عضویت", 'callback_data' => "ch_editjoin_" . $channelId], 'ch_editjoin', $styles)
            ],
            [
                rx_kb_style(['text' => "🆔 ویرایش آیدی کانال", 'callback_data' => "ch_editlink_" . $channelId], 'ch_editlink', $styles),
                rx_kb_style(['text' => "❌ حذف کانال", 'callback_data' => "ch_del_" . $channelId], 'ch_del', $styles)
            ],
            [
                rx_kb_style(['text' => "🔙 بازگشت به لیست کانال‌ها", 'callback_data' => "set_channel"], 'ch_list', $styles),
                rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles)
            ]
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

if (!function_exists('rx_get_channel_edit_back_keyboard')) {
    function rx_get_channel_edit_back_keyboard($channelId, $forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $channelId = (int)$channelId;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => "ch_manage_" . $channelId], 'ch_manage', $styles),
                rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles)
            ]
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

if (!function_exists('rx_get_channel_wizard_back_keyboard')) {
    function rx_get_channel_wizard_back_keyboard($forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'set_channel'], 'ch_list', $styles),
                rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles)
            ]
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

if (!function_exists('rx_get_backadmin_keyboard')) {
    function rx_get_backadmin_keyboard($forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles),
                rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'ch_backmenu', $styles)
            ]
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

$channelkeyboard = rx_get_channel_keyboard();
$_rx_back_btn = rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_acc_styles);
$backuser = json_encode(['inline_keyboard' => [[$_rx_back_btn]]]);
$_rx_genrandom_btn = rx_kb_style(['text' => '🎲 ساخت رندوم خودکار', 'callback_data' => 'gen_random_uname'], 'gen_random_uname', $_rx_nav_styles);
$usernamePromptKb = json_encode(['inline_keyboard' => [[$_rx_genrandom_btn], [$_rx_back_btn]]]);
$backadmin = rx_get_backadmin_keyboard();
$getNameCustomKb = json_encode([
    'keyboard' => [
        [['text' => '🎲 ساخت رندوم خودکار']],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' =>"متن دلخواه را وارد کنید یا روی دکمه ساخت رندوم بزنید"
]);

$stmt = $pdo->prepare("SHOW TABLES LIKE 'marzban_panel'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$namepanel = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> ''");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rxPanelName = trim((string)$row['name_panel']);
        if ($rxPanelName === '' || $rxPanelName[0] === '{' || $rxPanelName[0] === '[') continue;
        $namepanel[] = [$row['name_panel']];
    }
    $list_marzban_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($namepanel as $button) {
        $list_marzban_panel['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_marzban_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
    ];
    $json_list_marzban_panel = json_encode($list_marzban_panel);

    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> ''");
    $stmt->execute();
    $list_marzban_panel_edit_product = ['inline_keyboard' => []];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rxPanelName = trim((string)$row['name_panel']);
        if ($rxPanelName === '' || $rxPanelName[0] === '{' || $rxPanelName[0] === '[') continue;
        $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>$row['name_panel'],'callback_data' => 'locationedit_'.$row['code_panel']]];
    }
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"همه پنل ها",'callback_data' => 'locationedit_all']];
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"▶️ بازگشت به منوی قبل",'callback_data' => 'backproductadmin']];
    $list_marzban_panel_edit_product = json_encode($list_marzban_panel_edit_product);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'channels'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_channels = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM channels");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_channels[] = [$row['link']];
    }
    $list_channels_join = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_channels as $button) {
        $list_channels_join['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_channels_join['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
    ];
    $list_channels_joins = json_encode($list_channels_join);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'card_number'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_card = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM card_number");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_card[] = [$row['cardnumber']];
    }
    $list_card_remove = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_card as $button) {
        $list_card_remove['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_card_remove['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
    ];
    $list_card_remove = json_encode($list_card_remove);
}

    $stmt = $pdo->prepare("SHOW TABLES LIKE 'help'");
    $stmt->execute();
    $result = $stmt->fetchAll();
    $table_exists = count($result) > 0;
    if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    $helpkey = [];
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $helpkey[] = [$row['name_os']];
        }
        $help_arrke = [
            'keyboard' => [],
            'resize_keyboard' => true,
        ];
        foreach ($helpkey as $button) {
            $help_arrke['keyboard'][] = [
                ['text' => $button[0]]
            ];
        }
                $help_arrke['keyboard'][] = [
            ['text' => $textbotlang['Admin']['backmenu']],
        ];
        $json_list_helpkey = json_encode($help_arrke);
}

    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    $helpcwtgory = ['inline_keyboard' => []];
    $datahelp = [];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if(in_array($result['category'],$datahelp))continue;
        if($result['category'] == null)continue;
        $datahelp[] = $result['category'];
            $helpcwtgory['inline_keyboard'][] = [['text' => $result['category'], 'callback_data' => "helpctgoryـ{$result['category']}"]
            ];
        }
$helpcwtgory['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$json_list_helpـcategory = json_encode($helpcwtgory);

    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all')");
    $stmt->bindParam(':agent', $users['agent']);
    $stmt->execute();
    $list_marzban_panel_users = ['inline_keyboard' => []];
    $panelcount = select("marzban_panel","*","status","active","count");
    if ($panelcount > 10) {
        $temp_row = [];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rx_h = json_decode((string)($result['hide_user'] ?? ''), true); if (is_array($rx_h) && in_array($from_id, $rx_h)) continue;
            if ($result['type'] == "Manualsale") {
                $manualStmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
                $manualStmt->bindParam(':codepanel', $result['code_panel']);
                $manualStmt->execute();
                $configexits = $manualStmt->rowCount();
                if (intval($configexits) == 0) continue;
            }
            if ($users['step'] == "getusernameinfo") {
                $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "locationnotuser_{$result['code_panel']}"];
            } else {
                $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "location_{$result['code_panel']}"];
            }
            if (count($temp_row) == 2) {
                $list_marzban_panel_users['inline_keyboard'][] = $temp_row;
                $temp_row = [];
            }
        }
        if (!empty($temp_row)) {
            $list_marzban_panel_users['inline_keyboard'][] = $temp_row;
        }
    } else {
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($result['type'] == "Manualsale") {
                $stmts = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
                $stmts->bindParam(':codepanel', $result['code_panel']);
                $stmts->execute();
                $configexits = $stmts->rowCount();
                if (intval($configexits) == 0) continue;
            }
            $rx_h = json_decode((string)($result['hide_user'] ?? ''), true); if (is_array($rx_h) && in_array($from_id, $rx_h)) continue;
            if ($users['step'] == "getusernameinfo") {
                $list_marzban_panel_users['inline_keyboard'][] = [
                    ['text' => $result['name_panel'], 'callback_data' => "locationnotuser_{$result['code_panel']}"]
                ];
            } else {
                $list_marzban_panel_users['inline_keyboard'][] = [[
                    'text' => $result['name_panel'],
                    'callback_data' => "location_{$result['code_panel']}"
                ]];
            }
        }
    }
$statusnote = false;
if($setting['statusnamecustom'] == 'onnamecustom')$statusnote = true;
if($setting['statusnoteforf'] == "0" && $users['agent'] == "f")$statusnote = false;
    if($statusnote){
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "buyback"],
];
}else{
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
}
$list_marzban_panel_user = json_encode($list_marzban_panel_users);


    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all')");
    $stmt->bindParam(':agent', $users['agent']);
    $stmt->execute();
    $list_marzban_panel_users_om = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rx_h = json_decode((string)($result['hide_user'] ?? ''), true); if (is_array($rx_h) and in_array($from_id, $rx_h)) continue;
            $list_marzban_panel_users_om['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "locationom_{$result['code_panel']}"]
            ];
    }
$list_marzban_panel_users_om['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_marzban_panel_userom = json_encode($list_marzban_panel_users_om);


    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :ag OR agent = 'all') AND name_panel != :exclude");
    $stmt->execute([':ag' => (string)($users['agent'] ?? ''), ':exclude' => (string)($users['Processing_value_four'] ?? '')]);
    $list_marzban_panel_users_change = ['inline_keyboard' => []];
    $panelcount = select("marzban_panel","*","status","active","count");
    if($panelcount > 10){
        $temp_row = [];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($result['hide_user'] != null && in_array($from_id, json_decode($result['hide_user'], true))) continue;

            $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"];
        if (count($temp_row) == 2) {
            $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
            $temp_row = [];
        }
    }
if (!empty($temp_row)) {
    $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
}
    }else{
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rx_h = json_decode((string)($result['hide_user'] ?? ''), true); if (is_array($rx_h) and in_array($from_id, $rx_h)) continue;
            $list_marzban_panel_users_change['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"]
            ];
    }
    }
$list_marzban_panel_users_change['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backorder"],
];
$list_marzban_panel_userschange = json_encode($list_marzban_panel_users_change);


    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE TestAccount = 'ONTestAccount' AND (agent = :ag OR agent = 'all')");
    $stmt->execute([':ag' => (string)($users['agent'] ?? '')]);
    $list_marzban_panel_usertest = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rx_h = json_decode((string)($result['hide_user'] ?? ''), true); if (is_array($rx_h) and in_array($from_id, $rx_h)) continue;
            $list_marzban_panel_usertest['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "locationtest_{$result['code_panel']}"]
            ];
    }
$list_marzban_panel_usertest['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_marzban_usertest = json_encode($list_marzban_panel_usertest);


$textbot = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);

$stmt = $pdo->prepare("SHOW TABLES LIKE 'protocol'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $getdataprotocol = select("protocol","*",null,null,"fetchAll");
    $protocol = [];
    foreach($getdataprotocol as $result)
    {
        $protocol[] = [['text'=>$result['NameProtocol']]];
    }
    $protocol[] = [['text'=>$textbotlang['Admin']['backadmin']]];
    $keyboardprotocollist = json_encode(['resize_keyboard'=>true,'keyboard'=> $protocol]);
 }

$stmt = $pdo->prepare("SHOW TABLES LIKE 'product'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $product = [];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE FIND_IN_SET(:text, Location) > 0 or Location = '/all' ");
    $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    // Back row appended last so it renders at the bottom of the product
    // picker. Same fix as the gift-code/discount-code delete lists below.
    $list_product['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_product_list_admin = json_encode($list_product);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'Discount'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Discount = [];
    $stmt = $pdo->prepare("SELECT * FROM Discount");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $Discount[] = [$row['code']];
    }
    $list_Discount = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($Discount as $button) {
        $list_Discount['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    // Back row goes LAST so it renders at the bottom of the reply keyboard
    // (matches the convention used by $list_Inbound below + every static
    // admin keyboard). Previously this was inserted before the foreach loop
    // which forced the back row to the top of the gift-code delete list.
    $list_Discount['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Discount_list_admin = json_encode($list_Discount);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'Inbound'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Inboundkeyboard = [];
    $stmt = $pdo->prepare("SELECT * FROM Inbound WHERE location = :Processing_value AND protocol = :text");
    $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
    $stmt->bindParam(':Processing_value', $users['Processing_value']  , PDO::PARAM_STR);
    $stmt->execute();
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $Inboundkeyboard[] = [$row['NameInbound']];
}

}
    $list_Inbound = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($Inboundkeyboard as $button) {
        $list_Inbound['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_Inbound['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Inbound_list_admin = json_encode($list_Inbound);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'DiscountSell'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $DiscountSell = [];
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $DiscountSell[] = [$row['codeDiscount']];
    }
    $list_Discountsell = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($DiscountSell as $button) {
        $list_Discountsell['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    // Back row appended last so it renders at the bottom (same fix as the
    // gift-code list above — the original code prepended this row before
    // the foreach loop, forcing it to the top of the discount-code delete
    // keyboard).
    $list_Discountsell['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Discount_list_admin_sell = json_encode($list_Discountsell);
}
$payment = json_encode([
    'inline_keyboard' => [
        [rx_kb_style(['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"], 'confirm_pay', $_rx_nav_styles)],
        [rx_kb_style(['text' => "🎁 ثبت کد تخفیف", 'callback_data' => "aptdc"], 'confirm_discount', $_rx_nav_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_acc_styles)]
    ]
]);
$paymentom = json_encode([
    'inline_keyboard' => [
        [rx_kb_style(['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"], 'confirm_pay', $_rx_nav_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_acc_styles)]
    ]
]);
$change_product = json_encode([
    'keyboard' => [
        [
            rx_kb_style(['text' => "قیمت"], 'قیمت', $_rx_pedit_styles),
            rx_kb_style(['text' => "حجم"], 'حجم', $_rx_pedit_styles),
            rx_kb_style(['text' => "زمان"], 'زمان', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "نام محصول"], 'نام محصول', $_rx_pedit_styles),
            rx_kb_style(['text' => "نوع کاربری"], 'نوع کاربری', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "نوع ریست حجم"], 'نوع ریست حجم', $_rx_pedit_styles),
            rx_kb_style(['text' => "یادداشت"], 'یادداشت', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "موقعیت محصول"], 'موقعیت محصول', $_rx_pedit_styles),
            rx_kb_style(['text' => "دسته بندی"], 'دسته بندی', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "🎛 تنظیم اینباند"], '🎛 تنظیم اینباند', $_rx_pedit_styles),
            rx_kb_style(['text' => "نمایش برای خرید اول"], 'نمایش برای خرید اول', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "مخفی کردن پنل"], 'مخفی کردن پنل', $_rx_pedit_styles),
            rx_kb_style(['text' => "حذف کلی پنل های مخفی"], 'حذف کلی پنل های مخفی', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin']], 'backadmin', $_rx_pedit_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'backmenu', $_rx_pedit_styles),
        ]
    ],
    'resize_keyboard' => true
]);

$keyboardprotocol = json_encode([
    'keyboard' => [
        [['text' => "vless"],['text' => "vmess"],['text' => "trojan"]],
        [['text' => "shadowsocks"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);
$MethodUsername = json_encode([
    'keyboard' => [
        [['text' => "آیدی عددی + حروف و عدد رندوم"]],
        [['text' => "نام کاربری + حروف و عدد رندوم"]],
        [['text' => "نام کاربری دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه کاربر + رندوم"]],
        [['text' => "متن دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد ترتیبی"]],
        [['text' => "نام کاربری + عدد به ترتیب"]],
        [['text' => "آیدی عددی+عدد ترتیبی"]],
        [['text' => "متن دلخواه نماینده + عدد ترتیبی"]],
        [['text' => "نام کاربری دلخواه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);
$MethodUsernameRemna = json_encode([
    'keyboard' => [
        [['text' => "آیدی عددی"]],
        [['text' => "آیدی عددی + حروف و عدد رندوم"]],
        [['text' => "نام کاربری + حروف و عدد رندوم"]],
        [['text' => "نام کاربری دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه کاربر + رندوم"]],
        [['text' => "متن دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد ترتیبی"]],
        [['text' => "نام کاربری + عدد به ترتیب"]],
        [['text' => "آیدی عددی+عدد ترتیبی"]],
        [['text' => "متن دلخواه نماینده + عدد ترتیبی"]],
        [['text' => "نام کاربری دلخواه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);

$rxAdminPanelStylesAll = [];
if (function_exists('select')) {
    $__rxRow = select('setting', 'keyboard_styles_all', null, null, 'select');
    if (is_array($__rxRow) && !empty($__rxRow['keyboard_styles_all'])) {
        $__rxDecoded = json_decode($__rxRow['keyboard_styles_all'], true);
        if (is_array($__rxDecoded)) { $rxAdminPanelStylesAll = $__rxDecoded; }
    }
    unset($__rxRow, $__rxDecoded);
}










if (!function_exists('rx_adminPanelCallbackMapFile')) {
    function rx_adminPanelCallbackMapFile(): string {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
        return rtrim($base, '/') . '/rx_admin_panel_callback_map.json';
    }
}
if (!function_exists('rx_makeAdminPanelCallback')) {
    function rx_makeAdminPanelCallback(string $text): string {

        $short = 'apn:' . $text;
        if (strlen($short) <= 64) return $short;


        $code = 'apnh:' . substr(hash('sha256', $text), 0, 32);
        $file = rx_adminPanelCallbackMapFile();
        $map = [];
        if (is_file($file)) {
            $dec = json_decode((string) @file_get_contents($file), true);
            if (is_array($dec)) $map = $dec;
        }
        $map[$code] = $text;
        @file_put_contents($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $code;
    }
}
if (!function_exists('rx_resolveAdminPanelCallback')) {
    function rx_resolveAdminPanelCallback(string $callbackData): ?string {
        if (strpos($callbackData, 'apn:') === 0) {
            return substr($callbackData, 4);
        }
        if (strpos($callbackData, 'apnh:') === 0) {
            $file = rx_adminPanelCallbackMapFile();
            if (!is_file($file)) return null;
            $map = json_decode((string) @file_get_contents($file), true);
            return (is_array($map) && isset($map[$callbackData])) ? (string)$map[$callbackData] : null;
        }
        return null;
    }
}







if (!function_exists('rx_finalizeInlineAdminKb')) {
    function rx_finalizeInlineAdminKb(string $json, bool $rxMarkPlain = true): string {
        $kb = json_decode($json, true);
        if (!is_array($kb)) return $json;

        $rows = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $rows = $kb['keyboard'];
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $rows = $kb['inline_keyboard'];
        }
        if ($rows === null) return $json;

        $newRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $newRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn) || !isset($btn['text'])) continue;

                if (!isset($btn['callback_data']) && !isset($btn['url'])
                    && !isset($btn['login_url']) && !isset($btn['switch_inline_query'])
                    && !isset($btn['switch_inline_query_current_chat']) && !isset($btn['web_app'])) {
                    $btn['callback_data'] = rx_makeAdminPanelCallback((string)$btn['text']);
                }

                unset($btn['request_contact'], $btn['request_location'], $btn['request_poll'], $btn['request_users'], $btn['request_chat']);
                if ($rxMarkPlain) { $btn['_rxap'] = 1; } else { $btn['_rxkeep'] = 1; }
                $newRow[] = $btn;
            }
            if (!empty($newRow)) $newRows[] = $newRow;
        }
        return json_encode(['inline_keyboard' => $newRows], JSON_UNESCAPED_UNICODE);
    }
}
$rxAdminPanelBtn = function (string $text, string $menuKey, string $default = 'default') use ($rxAdminPanelStylesAll) {
    $btn = [
        'text' => $text,
        'callback_data' => rx_makeAdminPanelCallback($text),
    ];
    return $btn;
};

$optionMarzban = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_marzban'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_marzban', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_marzban'), $rxAdminPanelBtn("👤 ویرایش نام", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_marzban'), $rxAdminPanelBtn("⚙️ پروتکل اینباند", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_marzban'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_marzban'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_marzban'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_marzban'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_marzban', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));