<?php

namespace Faoxima\Admin\Keyboard;

/**
 * Handler stage. Inherited by value: $rxAdminPanelBtn, $rxFxPricingRow, $textbotlang.
 * Inherited or exported by reference: $optionGuard, $optionJovad, $optionManualsale, $optionPasarGuard, $optionRebecca, $optionX_ui_single, $optionwg.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderPanels
{
    public static function handle(
        &$optionGuard,
        &$optionJovad,
        &$optionManualsale,
        &$optionPasarGuard,
        &$optionRebecca,
        &$optionX_ui_single,
        &$optionwg,
        $rxAdminPanelBtn,
        $rxFxPricingRow,
        $textbotlang
    ) {
        $optionGuard = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_guard')],
                [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_guard')],
                [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_guard'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_guard', 'danger')],
                [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_guard'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_guard')],
                [$rxAdminPanelBtn("⚙️ تنظیم سرویس ها", 'admin_panel_guard')],
                [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_guard'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_guard')],
                [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_guard'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_guard')],
                [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_guard')],
                $rxFxPricingRow('admin_panel_guard'),
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
        $optionRebecca = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_rebecca')],
                [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_rebecca')],
                [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_rebecca'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_rebecca', 'danger')],
                [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_rebecca'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_rebecca')],
                [$rxAdminPanelBtn("⚙️ سرویس پیش‌فرض", 'admin_panel_rebecca')],
                [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_rebecca'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_rebecca')],
                [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_rebecca'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_rebecca')],
                [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_rebecca')],
                $rxFxPricingRow('admin_panel_rebecca'),
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
        $optionJovad = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_jovadpanel'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_jovadpanel', 'danger')],
                [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_jovadpanel'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_jovadpanel'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_jovadpanel'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("👥 تعداد دستگاه اشتراک نامحدود", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("📡 کانال‌های پیش‌فرض", 'admin_panel_jovadpanel')],
                $rxFxPricingRow('admin_panel_jovadpanel'),
                [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_jovadpanel'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_jovadpanel'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_jovadpanel'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_jovadpanel'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_jovadpanel')],
                [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_jovadpanel', 'danger')],
                [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]));
        $optionPasarGuard = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_pasarguard')],
                [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_pasarguard')],
                [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_pasarguard'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_pasarguard', 'danger')],
                [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_pasarguard'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_pasarguard')],
                [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_pasarguard'), $rxAdminPanelBtn("⚙️ پروتکل اینباند", 'admin_panel_pasarguard')],
                [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_pasarguard'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_pasarguard')],
                [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_pasarguard'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_pasarguard')],
                [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_pasarguard')],
                $rxFxPricingRow('admin_panel_pasarguard'),
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
        $optionwg = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_wg')],
                [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_wg')],
                [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_wg'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_wg', 'danger')],
                [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_wg'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_wg')],
                [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_wg'), $rxAdminPanelBtn("💎 شناسه اینباند", 'admin_panel_wg')],
                [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_wg'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_wg')],
                [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_wg'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_wg')],
                [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_wg')],
                $rxFxPricingRow('admin_panel_wg'),
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
        $optionManualsale = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_manualsale')],
                [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_manualsale')],
                [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_manualsale'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_manualsale', 'danger')],
                [$rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_manualsale'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_manualsale')],
                [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_manualsale'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_manualsale')],
                [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_manualsale')],
                $rxFxPricingRow('admin_panel_manualsale'),
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]));
        $optionX_ui_single = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_x_ui_single')],
                [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_x_ui_single')],
                [$rxAdminPanelBtn("🔄 تغییر حالت API", 'admin_panel_x_ui_single')],
                [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_x_ui_single', 'danger')],
                [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("👤 ویرایش نام", 'admin_panel_x_ui_single')],
                [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_x_ui_single')],
                [$rxAdminPanelBtn("💎 شناسه اینباند", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_x_ui_single')],
                [$rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_x_ui_single'), $rxAdminPanelBtn('🔗 دامنه لینک ساب', 'admin_panel_x_ui_single')],
                [$rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_x_ui_single')],
                [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_x_ui_single')],
                $rxFxPricingRow('admin_panel_x_ui_single'),
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

        return 1;
    }
}
