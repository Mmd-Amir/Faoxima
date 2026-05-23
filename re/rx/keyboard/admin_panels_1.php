<?php

$optionGuard = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_guard'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_guard', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_guard'), $rxAdminPanelBtn("⁉️ وضعیت اتصال به پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️ تنظیم سرویس ها", 'admin_panel_guard'), $rxAdminPanelBtn("🎛️ تنظیمات سرویس", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_guard'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_guard'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_guard'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_guard'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_guard'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_guard')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_guard', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionibsng = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_ibsng'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_ibsng', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_ibsng'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_ibsng'), $rxAdminPanelBtn('🎛 تنظیم نام گروه', 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_ibsng'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_ibsng'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_ibsng'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_ibsng'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_ibsng'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_ibsng'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_ibsng'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_ibsng', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$option_mikrotik = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_mikrotik'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_mikrotik', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_mikrotik'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_mikrotik'), $rxAdminPanelBtn('🎛 تنظیم نام گروه', 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_mikrotik'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_mikrotik'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_mikrotik'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_mikrotik'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_mikrotik'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_mikrotik'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_mikrotik'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_mikrotik', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$options_ui = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_s_ui'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_s_ui', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_s_ui'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_s_ui'), $rxAdminPanelBtn("⚙️ تنظیم پروتکل و اینباند", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_s_ui'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_s_ui'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_s_ui'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_s_ui'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_s_ui'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_s_ui'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_s_ui'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_s_ui'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_s_ui', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionwg = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_wg')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_wg'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_wg', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_wg'), $rxAdminPanelBtn("💎 تنظیم شناسه اینباند", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_wg'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_wg'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_wg'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_wg'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_wg'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_wg')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_wg', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionmarzneshin = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_marzneshin'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_marzneshin', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_marzneshin'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_marzneshin'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("⚙️ تنظیمات سرویس", 'admin_panel_marzneshin'), $rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_marzneshin'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_marzneshin'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_marzneshin'), $rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_marzneshin'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_marzneshin'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_marzneshin'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_marzneshin', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionManualsale = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_manualsale'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_manualsale', 'danger')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_manualsale'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("➕ اضافه کردن کانفیگ", 'admin_panel_manualsale', 'success'), $rxAdminPanelBtn("❌ حذف کانفیگ ", 'admin_panel_manualsale', 'danger')],
        [$rxAdminPanelBtn("✏️ ویرایش کانفیگ", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_manualsale'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_manualsale', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionX_ui_single = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_x_ui_single', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("💎 تنظیم شناسه اینباند", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_x_ui_single'), $rxAdminPanelBtn('🔗 دامنه لینک ساب', 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_x_ui_single', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionalireza_single = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_alireza_single'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_alireza_single', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_alireza_single'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_alireza_single'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("💎 تنظیم شناسه اینباند", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn('🔗 دامنه لینک ساب', 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_alireza_single'), $rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_alireza_single'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_alireza_single'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_alireza_single'), $rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_alireza_single'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_alireza_single'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_alireza_single'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_alireza_single', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionhiddfy = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_hiddify'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_hiddify', 'danger')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_hiddify'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn('🔗 دامنه لینک ساب', 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_hiddify'), $rxAdminPanelBtn("🔗 uuid admin", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_hiddify'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_hiddify'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_hiddify'), $rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_hiddify'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_hiddify'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_hiddify'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_hiddify', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
if($setting['statussupportpv'] == "onpvsupport"){
    $supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                ['text' => "🎟 ارسال پیام به پشتیبانی", 'url' => "https://t.me/{$setting['id_support']}"    ],
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
                ['text' => "🎟 ارسال پیام به پشتیبانی", 'callback_data' => "support"],
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
$affiliates =  json_encode([
    'keyboard' => [
        [['text' => "🧮 تنظیم درصد زیرمجموعه"]],
        [['text' => "🏞 تنظیم بنر زیرمجموعه گیری"]],
        [['text' => "🎁 پورسانت بعد از خرید"],['text' => "🎁 هدیه استارت"]],
        [['text' => "🎉 پورسانت فقط برای خرید اول"]],
        [['text' => "🌟 مبلغ هدیه استارت"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
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
        [['text' =>"ویرایش نام"],['text' =>"ویرایش توضیحات"]],
        [['text' => "ویرایش رسانه"],['text' => "ویرایش دسته بندی"]],
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
            ['text' => "مرزبان" , 'callback_data' => "typepanel#marzban"],
            ['text' => "🎛 پاسارگارد" , 'callback_data' => "typepanel#pasargard"]
        ],
        [
            ['text' => "مرزنشین" , 'callback_data' => "typepanel#marzneshin"],
            ['text' => "هیدیفای" , 'callback_data' => 'typepanel#hiddify']
        ],
        [
            ['text' => 'ثنایی تک پورت', 'callback_data' => 'typepanel#x-ui_single'],
            ['text' => 'علیرضا تک پورت' , 'callback_data' => 'typepanel#alireza_single']
        ],
        [
            ['text' => "فروش دستی" , 'callback_data' => 'typepanel#Manualsale'],
            ['text' => "Guard (GuardCore)", 'callback_data' => 'typepanel#guard']
        ],
        [
            ['text' => "WGDashboard", 'callback_data' => 'typepanel#WGDashboard'],
            ['text' => "s_ui", 'callback_data' => 'typepanel#s_ui']
        ],
        [
            ['text' => "ibsng", 'callback_data' => 'typepanel#ibsng'],
            ['text' => "میکروتیک", 'callback_data' => 'typepanel#mikrotik']
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
            ['text' => "👤 انتخاب نام دلخواه", 'callback_data' => "selectname"]
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
        [['text' => "🗂 خرید انبوه"],['text' => "👤 انتخاب نام دلخواه"]],
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true
];
if($panelechekc == 0){
    unset($keyboardagent['keyboard'][0][1]);
}
}
$keyboardagent = json_encode($keyboardagent);
$Swapinokey = json_encode([
    'keyboard' => [
        [['text' => "تنظیم api"]],
        [['text' => "🗂 نام درگاه ارزی ریالی"]],
        [['text' => "💰 کش بک ارزی ریالی"],['text' => "📚 تنظیم آموزش ارزی ریالی اول"]],
        [['text' => "⬇️ حداقل مبلغ ارزی ریالی"],['text' => "⬆️ حداکثر مبلغ ارزی ریالی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$tronnowpayments = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه رمز ارز آفلاین"]],
        [['text' => "⬇️ حداقل مبلغ رمزارز آفلاین"],['text' => "⬆️ حداکثر مبلغ رمزارز آفلاین"]],
        [['text' => "📚 تنظیم آموزش  ارزی افلاین"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionathmarzban = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("🔧 ساخت کانفیگ دستی", 'admin_panel_athmarzban'), $rxAdminPanelBtn("🖥 مدیریت نود ها", 'admin_panel_athmarzban')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionathx_ui = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("🔧 ساخت کانفیگ دستی", 'admin_panel_athx_ui')],
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
        [['text' => "🗂 نام درگاه ارزی ریالی سوم"]],
        [['text' => "⬇️ حداقل مبلغ ارزی ریالی سوم"],['text' => "⬆️ حداکثر مبلغ ارزی ریالی سوم"]],
        [['text' => "💰 کش بک ارزی ریالی سوم"]],
        [['text' => "📚 تنظیم آموزش ارزی ریالی سوم"]],
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
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
$wheelkeyboard =  json_encode([
    'keyboard' => [
        [['text' => "🎲 مبلغ برنده شدن کاربر"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);