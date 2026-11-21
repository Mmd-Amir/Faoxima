<?php

namespace Faoxima\Admin\Keyboard;

/**
 * Handler stage. Inherited by value: $__rxDecoded, $textbotlang.
 * Inherited or exported by reference: $optionMarzban, $rxAdminPanelBtn, $rxFxPricingRow.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderMenuRendering
{
    public static function handle(
        $__rxDecoded,
        &$optionMarzban,
        &$rxAdminPanelBtn,
        &$rxFxPricingRow,
        $textbotlang
    ) {
        $rxAdminPanelStylesAll = [];
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $__rxRow = \Faoxima\Core\Database\Db::select('setting', 'keyboard_styles_all', null, null, 'select');
            if (is_array($__rxRow) && !empty($__rxRow['keyboard_styles_all'])) {
                $__rxDecoded = json_decode($__rxRow['keyboard_styles_all'], true);
                if (is_array($__rxDecoded)) { $rxAdminPanelStylesAll = $__rxDecoded; }
            }
            unset($__rxRow, $__rxDecoded);
        }

















        $rxAdminPanelBtn = function (string $text, string $menuKey, string $default = 'default') use ($rxAdminPanelStylesAll) {
            $btn = [
                'text' => $text,
                'callback_data' => \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_makeAdminPanelCallback($text),
            ];
            return $btn;
        };
        $rxFxPricingRow = function (string $menuKey) use ($rxAdminPanelBtn) {
            return [$rxAdminPanelBtn(\Faoxima\Orders\Pricing\ExchangePricing::fxAdminButtonLabel(), $menuKey)];
        };

        $optionMarzban = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_marzban')],
                [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_marzban')],
                [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_marzban'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_marzban', 'danger')],
                [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_marzban'), $rxAdminPanelBtn("👤 ویرایش نام", 'admin_panel_marzban')],
                [$rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_marzban')],
                [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_marzban'), $rxAdminPanelBtn("⚙️ پروتکل اینباند", 'admin_panel_marzban')],
                [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_marzban'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_marzban')],
                [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_marzban')],
                [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_marzban')],
                $rxFxPricingRow('admin_panel_marzban'),
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

        return 1;
    }
}
