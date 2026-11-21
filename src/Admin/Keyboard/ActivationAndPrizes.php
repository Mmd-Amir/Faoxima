<?php

namespace Faoxima\Admin\Keyboard;

/**
 * Handler stage. Inherited by value: $rxAdminPanelBtn, $rxFxPricingRow, $textbotlang.
 * Inherited or exported by reference: $lottery, $option_remnawave, $wheelkeyboard.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ActivationAndPrizes
{
    public static function handle(
        &$lottery,
        &$option_remnawave,
        $rxAdminPanelBtn,
        $rxFxPricingRow,
        $textbotlang,
        &$wheelkeyboard
    ) {
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
        ]);$option_remnawave = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
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
                $rxFxPricingRow('admin_panel_remnawave'),
                [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_remnawave'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_remnawave')],
                [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_remnawave'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_remnawave')],
                [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_remnawave'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_remnawave')],
                [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_remnawave'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_remnawave')],
                [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_remnawave')],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]));

        return 1;
    }
}
