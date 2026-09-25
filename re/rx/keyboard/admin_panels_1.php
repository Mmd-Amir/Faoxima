<?php

$optionGuard = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_guard'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_guard', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_guard'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️ تنظیم سرویس ها", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_guard'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_guard'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_guard'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_guard')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_guard', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionRebecca = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_rebecca'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_rebecca', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_rebecca'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("⚙️ سرویس پیش‌فرض", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_rebecca'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_rebecca'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_rebecca'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_rebecca'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_rebecca'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_rebecca'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_rebecca', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionPasarGuard = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_pasarguard'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_pasarguard', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_pasarguard'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_pasarguard'), $rxAdminPanelBtn("⚙️ پروتکل اینباند", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_pasarguard'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_pasarguard'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_pasarguard'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_pasarguard'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_pasarguard'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_pasarguard'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_pasarguard', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionwg = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_wg')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_wg'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_wg', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_wg'), $rxAdminPanelBtn("💎 شناسه اینباند", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_wg'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_wg'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_wg'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_wg')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_wg', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionManualsale = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_manualsale'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_manualsale', 'danger')],
        [$rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_manualsale'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_manualsale')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionX_ui_single = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🔄 تغییر حالت API", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_x_ui_single', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("👤 ویرایش نام", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("💎 شناسه اینباند", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_x_ui_single'), $rxAdminPanelBtn('🔗 دامنه لینک ساب', 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_x_ui_single', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
if($setting['statussupportpv'] == "onpvsupport"){
    $supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                ['text' => "🎟 پیام پشتیبانی", 'url' => "https://t.me/{$setting['id_support']}"    ],
            ],[
                ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
            ],

        ]
    ]);
}else{
$supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                ['text' => "🎟 پیام پشتیبانی", 'callback_data' => "support"],
            ],[
                ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
            ],

        ]
    ]);
}
$adminrule = json_encode([
    'keyboard' => [
        [['text' => "administrator"],['text' => "Seller"],['text' => "support"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$rxAffiliatesSettingsRow = select("affiliates", "*", null, null, "select");
$rxAffiliatesMenuMode = (is_array($rxAffiliatesSettingsRow) && ($rxAffiliatesSettingsRow['menu_keyboard_mode'] ?? '') === 'inline_menu') ? 'inline_menu' : 'reply_menu';

$affiliatesReplyRows = [
    [['text' => "🖼 مشاهده بنر فعال"]],
    [['text' => "🏞 تنظیم عکس بنر"], ['text' => "✏️ ویرایش متن بنر"]],
    [['text' => "🗑 حذف بنر"], ['text' => "♻️ بازگردانی بنر پیش‌فرض"]],
    [['text' => "🧮 تنظیم درصد زیرمجموعه"], ['text' => "🌟 مبلغ هدیه استارت"]],
    [['text' => "🎁 هدیه استارت"], ['text' => "🎁 پورسانت بعد از خرید"]],
    [['text' => "🎉 پورسانت فقط برای خرید اول"]],
    [['text' => "🛡 حفاظت از سواستفاده"]],
    [['text' => "📊 گزارشات زیرمجموعه"]],
    [['text' => $textbotlang['Admin']['backmenu']], ['text' => $textbotlang['Admin']['backadmin']]]
];

$affiliatesInlineRows = [
    [['text' => "🖼 مشاهده بنر فعال", 'callback_data' => "affiliates_view_banner"]],
    [['text' => "🏞 تنظیم عکس بنر", 'callback_data' => "affiliates_edit_banner_image"], ['text' => "✏️ ویرایش متن بنر", 'callback_data' => "affiliates_edit_banner_text"]],
    [['text' => "🗑 حذف بنر", 'callback_data' => "affiliates_delete_banner"], ['text' => "♻️ بازگردانی بنر پیش‌فرض", 'callback_data' => "affiliates_reset_banner"]],
    [['text' => "🧮 تنظیم درصد زیرمجموعه", 'callback_data' => "affiliates_set_percentage"], ['text' => "🌟 مبلغ هدیه استارت", 'callback_data' => "affiliates_set_start_amount"]],
    [['text' => "🎁 هدیه استارت", 'callback_data' => "affiliates_toggle_start_gift"], ['text' => "🎁 پورسانت بعد از خرید", 'callback_data' => "affiliates_toggle_commission"]],
    [['text' => "🎉 پورسانت فقط برای خرید اول", 'callback_data' => "affiliates_toggle_first_buy_only"]],
    [['text' => "🛡 حفاظت از سواستفاده", 'callback_data' => "affiliates_antifraud_menu"]],
    [['text' => "📊 گزارشات زیرمجموعه", 'callback_data' => "affiliates_reports"]],
    [['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => "backmenu"]]
];

$affiliates = $rxAffiliatesMenuMode === 'inline_menu'
    ? json_encode(['inline_keyboard' => $affiliatesInlineRows])
    : json_encode(['keyboard' => $affiliatesReplyRows, 'resize_keyboard' => true]);

$affiliatesAntiFraud = json_encode([
    'keyboard' => [
        [['text' => "☎️ الزام تایید شماره"]],
        [['text' => "⏳ حداقل سن اکانت"]],
        [['text' => "📅 سقف روزانه معرفی"], ['text' => "🗓 سقف ماهانه معرفی"]],
        [['text' => $textbotlang['Admin']['backmenu']], ['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
$keyboardexportdata =  json_encode([
    'keyboard' => [
        [['text' => "خروجی کاربران"],['text' => "خروجی سفارشات"]],
        [['text' => "خروجی گرفتن پرداخت ها"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$helpedit =  json_encode([
    'keyboard' => [
        [['text' =>"ویرایش نام آموزش"],['text' =>"ویرایش توضیحات"]],
        [['text' => "ویرایش رسانه"],['text' => "ویرایش دسته بندی"]],
        [['text' => "ویرایش لینک برنامه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$Methodextend = json_encode([
    'keyboard' => [
        [['text' => "ریست حجم و زمان"]],
        [['text' => "اضافه شدن زمان و حجم به ماه بعد"]],
        [['text'=> "ریست زمان و اضافه کردن حجم قبلی"]],
        [['text' => "ریست شدن حجم و اضافه شدن زمان"]],
        [['text' => "اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده"]],
        [['text' => "رزرو اشتراک"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtimereset = json_encode([
    'keyboard' => [
        [['text' => "no_reset"],['text' => "day"],['text' => "week"]],
        [['text' => "month"],['text' => "year"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtypepanel = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "Marzban", 'callback_data' => 'typepanel#marzban'],
            ['text' => "Rebecca", 'callback_data' => 'typepanel#rebecca'],
            ['text' => "PasarGuard" , 'callback_data' => "typepanel#pasargard"]
        ],
        [
            ['text' => "Remnawave", 'callback_data' => 'typepanel#remnawave'],
            ['text' => "Guard", 'callback_data' => 'typepanel#guard'],
            ['text' => '3x-ui', 'callback_data' => 'typepanel#x-ui_single']
        ],
        [
            ['text' => "فروش دستی" , 'callback_data' => 'typepanel#Manualsale']
        ],
        [
            ['text' => $textbotlang['Admin']['backadmin'] , 'callback_data' => 'admin']
        ]
    ],
]);
$keyboardswitchtype = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "Marzban", 'callback_data' => 'switchtype#marzban'],
            ['text' => "Rebecca", 'callback_data' => 'switchtype#rebecca'],
            ['text' => "PasarGuard" , 'callback_data' => "switchtype#pasargard"]
        ],
        [
            ['text' => "Remnawave", 'callback_data' => 'switchtype#remnawave'],
            ['text' => "Guard", 'callback_data' => 'switchtype#guard'],
            ['text' => '3x-ui', 'callback_data' => 'switchtype#x-ui_single']
        ],
        [
            ['text' => $textbotlang['Admin']['backadmin'] , 'callback_data' => 'admin']
        ]
    ],
]);

$panelechekc = select("marzban_panel","*","MethodUsername","متن دلخواه نماینده + عدد ترتیبی","count");
if($setting['inlinebtnmain'] == "oninline"){
    $keyboardagent = [
    'inline_keyboard' => [
        [
            ['text' => "🗂 خرید انبوه", 'callback_data' => "kharidanbuh"],
            ['text' => "👤 نام دلخواه", 'callback_data' => "selectname"]
        ],
        [
            ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
        ]
    ],
    'resize_keyboard' => true
];
if($panelechekc == 0){
    unset($keyboardagent['inline_keyboard'][0][1]);
}
}else{
$keyboardagent = [
    'keyboard' => [
        [['text' => "🗂 خرید انبوه"],['text' => "👤 نام دلخواه"]],
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true
];
if($panelechekc == 0){
    unset($keyboardagent['keyboard'][0][1]);
}
}
$keyboardagent = json_encode($keyboardagent);

$tronnowpayments = json_encode([
    'keyboard' => [
        [['text' => "🏷️ نام نمایشی درگاه رمز ارز آفلاین"]],
        [['text' => "⬇️ کف رمزارز آفلاین"],['text' => "⬆️ سقف رمزارز آفلاین"]],
        [['text' => "📚 آموزش  ارزی افلاین"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionathmarzban = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("🔧 کانفیگ دستی", 'admin_panel_athmarzban'), $rxAdminPanelBtn("🖥 مدیریت نود ها", 'admin_panel_athmarzban')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionathx_ui = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("🔧 کانفیگ دستی", 'admin_panel_athx_ui')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$configedit = json_encode([
    'keyboard' => [
        [['text' => "مخشصات کانفیگ"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$iranpaykeyboard = json_encode([
    'keyboard' => [
        [['text' => "api  درگاه ارزی ریالی"]],
        [['text' => "🗂 نام درگاه ریالی سوم"]],
        [['text' => "⬇️ کف ریالی سوم"],['text' => "⬆️ سقف ریالی سوم"]],
        [['text' => "💰 کش بک ریالی سوم"]],
        [['text' => "📚 آموزش ریالی سوم"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$supportcenter = json_encode([
    'keyboard' => [
        [['text' => "👤 تنظیم آیدی پشتیبانی"]],
        [['text' => "🔼 اضافه کردن دپارتمان"],['text' => "🔽 حذف کردن دپارتمان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$stmt = $pdo->prepare("SHOW TABLES LIKE 'departman'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$departeman = [];

$departemans = [
    'keyboard' => [],
    'resize_keyboard' => true,
];

if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM departman");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departeman[] = [$row['name_departman']];
    }
    foreach ($departeman as $button) {
        $departemans['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
}

$departemans['keyboard'][] = [
    ['text' => $textbotlang['Admin']['backadmin']],
    ['text' => $textbotlang['Admin']['backmenu']]
];

$departemanslist = json_encode($departemans);


$list_departman = ['inline_keyboard' => []];

if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM departman");
    $stmt->execute();
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_departman['inline_keyboard'][] = [[
            'text' => $result['name_departman'],
            'callback_data' => "departman_{$result['id']}"
        ]];
    }
}

$list_departman['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_departman = json_encode($list_departman);
$active_panell =  json_encode([
    'keyboard' => [
        [['text' => "📣 گزارشات ربات"]],
    ],
    'resize_keyboard' => true
]);
$lottery =  json_encode([
    'keyboard' => [
        [['text' => "1️⃣ تنظیم جایزه نفر اول"],['text' => "2️⃣ تنظیم جایزه نفر دوم"]],
        [['text' => "3️⃣ تنظیم جایزه نفر سوم"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$wheelkeyboard =  json_encode([
    'keyboard' => [
        [['text' => "🎲 مبلغ برنده شدن کاربر"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);$option_remnawave = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🔄 تست اتصال مجدد", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_remnawave'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_remnawave', 'danger')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_remnawave'), $rxAdminPanelBtn("🔑 تنظیم توکن API", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🛡 تنظیم شناسه Squad", 'admin_panel_remnawave'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🖥 داشبورد نودها", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_remnawave'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_remnawave'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_remnawave'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_remnawave'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_remnawave'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_remnawave')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
