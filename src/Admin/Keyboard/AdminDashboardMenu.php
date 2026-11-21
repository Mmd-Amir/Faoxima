<?php

namespace Faoxima\Admin\Keyboard;

final class AdminDashboardMenu
{
    public static function build($role, array $textbotlang, ?array $_rx_adm_styles, ?array $_rx_rol_styles): ?string
    {
        if ($role === "administrator") {
            return \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🚦 آمادگی فروش", 'callback_data' => 'admin_readiness'], 'admin_readiness', $_rx_adm_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['Status']['btn'], 'callback_data' => 'admin_status'], 'admin_status', $_rx_adm_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📁 مدیریت پنل‌ها و سرورها", 'callback_data' => 'admin_panels'], 'admin_panels', $_rx_adm_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏬 تنظیمات فروشگاه", 'callback_data' => 'admin_shop'], 'admin_shop', $_rx_adm_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💎 مالی و گزارشات", 'callback_data' => 'admin_finance'], 'admin_finance', $_rx_adm_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "👥 مدیریت کاربران", 'callback_data' => 'admin_usershub'], 'admin_usershub', $_rx_adm_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🤙 بخش پشتیبانی", 'callback_data' => 'admin_support'], 'admin_support', $_rx_adm_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 بخش آموزش", 'callback_data' => 'admin_help'], 'admin_help', $_rx_adm_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📢 کانال و اطلاع‌رسانی", 'callback_data' => 'admin_channelhub'], 'admin_channelhub', $_rx_adm_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⚙️ تنظیمات فنی و ربات", 'callback_data' => 'admin_settings'], 'admin_settings', $_rx_adm_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'admin_back'], 'admin_back', $_rx_adm_styles)]
            ]);
        }
        if ($role === "Seller") {
            return \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['Status']['btn'], 'callback_data' => 'seller_status'], 'seller_status', $_rx_rol_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "👤 مدیریت کاربر", 'callback_data' => 'seller_users'], 'seller_users', $_rx_rol_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'seller_back'], 'seller_back', $_rx_rol_styles)]
            ]);
        }
        if ($role === "support") {
            return \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "👤 مدیریت کاربر", 'callback_data' => 'support_users'], 'support_users', $_rx_rol_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "👁‍🗨 جستجو کاربر", 'callback_data' => 'support_search'], 'support_search', $_rx_rol_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'support_back'], 'support_back', $_rx_rol_styles)]
            ]);
        }
        return null;
    }
}
