<?php

namespace Faoxima\Admin\Keyboard;

final class SettingsMenus
{
    public static function build(array $textbotlang, ?array $_rx_set_styles, ?array $_rx_adm_styles): array
    {
        $settingPanelRows = [
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⚙️ وضعیت قابلیت ها", 'callback_data' => 'set_features'], 'set_features', $_rx_set_styles)],
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🗑 بهینه سازی ربات", 'callback_data' => 'set_optimize'], 'set_optimize', $_rx_set_styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "✅ پنل تحت وب", 'callback_data' => 'set_webpanel'], 'set_webpanel', $_rx_set_styles)
            ],
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📝 تنظیم متن ربات", 'callback_data' => 'set_text'], 'set_text', $_rx_set_styles)],
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📷 تنظیمات کیو آر کد", 'callback_data' => 'set_qrsettings'], 'set_qrsettings', $_rx_set_styles)],
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔗 وبهوک ربات‌های نماینده", 'callback_data' => 'set_webhook'], 'set_webhook', $_rx_set_styles)],
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'set_backadmin'], 'set_backadmin', $_rx_set_styles)
            ],
        ];

        $setting_panel = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode($settingPanelRows);

        $adminPanelsMenu = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'], 'callback_data' => 'admin_managepanel'], 'admin_managepanel', $_rx_adm_styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'], 'callback_data' => 'admin_addpanel'], 'admin_addpanel', $_rx_adm_styles)
            ],
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⏳ قیمت سریع زمان", 'callback_data' => 'admin_timeprice'], 'admin_timeprice', $_rx_adm_styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔋 قیمت سریع حجم", 'callback_data' => 'admin_volprice'], 'admin_volprice', $_rx_adm_styles)
            ],
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => 'panelshub_backmenu'], 'panelshub_backmenu', $_rx_adm_styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏠 منوی مدیریت", 'callback_data' => 'adm_hub_main'], 'panelshub_backadmin', $_rx_adm_styles)
            ],
        ]);

        $adminChannelMenu = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📯 تنظیمات کانال", 'callback_data' => 'set_channel'], 'set_channel', $_rx_set_styles)],
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📣 گزارشات ربات", 'callback_data' => 'set_reports'], 'set_reports', $_rx_set_styles)],
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => 'channelhub_backmenu'], 'channelhub_backmenu', $_rx_set_styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏠 منوی مدیریت", 'callback_data' => 'adm_hub_main'], 'channelhub_backadmin', $_rx_set_styles)
            ],
        ]);

        $adminUsersMenu = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managruser'], 'callback_data' => 'admin_users'], 'admin_users', $_rx_adm_styles)],
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "👨‍🔧 بخش ادمین", 'callback_data' => 'set_adminmgr'], 'set_adminmgr', $_rx_set_styles)],
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💵 رسید های تایید نشده", 'callback_data' => 'admin_invoices'], 'admin_invoices', $_rx_adm_styles)],
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => 'usershub_backmenu'], 'usershub_backmenu', $_rx_adm_styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏠 منوی مدیریت", 'callback_data' => 'adm_hub_main'], 'usershub_backadmin', $_rx_adm_styles)
            ],
        ]);

        return [$setting_panel, $adminPanelsMenu, $adminChannelMenu, $adminUsersMenu];
    }
}
