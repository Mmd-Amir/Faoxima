<?php

namespace Faoxima\Admin\Keyboard;

final class GatewayMenus
{
    public static function build(array $textbotlang): array
    {
        $Startelegram = json_encode([
            'keyboard' => [
                [['text' => "🏷️ نام نمایشی درگاه استار"]],
                [['text' => "💰 کش بک استار"],['text' => "📚 آموزش استار"]],
                [['text' => "⬇️ کف استار"],['text' => "⬆️ سقف استار"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);
        $keyboardchangelimit = json_encode([
            'keyboard' => [
                [['text' => "🆓 محدودیت رایگان"],['text' => "↙️ محدودیت کلی"]],
                [['text' => "🔄 ریست محدودیت کل کاربران"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);
        $nowpayment_setting_keyboard = json_encode([
            'keyboard' => [
                [['text' => "API NOWPAYMENT"],['text' => "🔐 IPN Secret nowpayment"]],
                [['text' => "🏷️ نام نمایشی درگاه nowpayment"],['text' => "💰 کش بک nowpayment"]],
                [['text' => "📚 آموزش nowpayment"]],
                [['text' => "⬇️ کف nowpayment"],['text' => "⬆️ سقف nowpayment"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);
        $autoconfirm_advanced_keyboard = json_encode([
            'keyboard' => [
                [['text' => "🚫 استثناء کاربران"],['text' => "✅ کاربران معتمد"]],
                [['text' => $textbotlang['Admin']['backadmin']]]
            ],
            'resize_keyboard' => true
        ]);
        $Exception_auto_cart_keyboard = json_encode([
            'keyboard' => [
                [['text' => "➕ استثناء کردن کاربر"],['text' => "❌ حذف کاربر از لیست"]],
                [['text' => "👁 نمایش لیست استثناء"]],
                [['text' => "◀️ بازگشت به تنظیمات پیشرفته"]]
            ],
            'resize_keyboard' => true
        ]);
        $Trust_auto_cart_keyboard = json_encode([
            'keyboard' => [
                [['text' => "➕ افزودن به معتمدین"],['text' => "❌ حذف از معتمدین"]],
                [['text' => "👁 نمایش لیست معتمدین"]],
                [['text' => "🔁 وضعیت حالت معتمدین"]],
                [['text' => "◀️ بازگشت به تنظیمات پیشرفته"]]
            ],
            'resize_keyboard' => true
        ]);

        return [$Startelegram, $keyboardchangelimit, $nowpayment_setting_keyboard, $autoconfirm_advanced_keyboard, $Exception_auto_cart_keyboard, $Trust_auto_cart_keyboard];
    }

    public static function finalize(array $keyboards): array
    {
        return array_map(static fn ($keyboard) => is_string($keyboard) && $keyboard !== ''
            ? ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb($keyboard, true)
            : $keyboard, $keyboards);
    }

    public static function reports(): string|false
    {
        return json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => "⏱️ آمار کل", 'callback_data' => 'stat_all_bot'],
                        ],[
                            ['text' => "⏱️ یک ساعت اخیر", 'callback_data' => 'hoursago_stat'],
                        ],
                        [
                            ['text' => "⛅️ امروز", 'callback_data' => 'today_stat'],
                            ['text' => "☀️ دیروز", 'callback_data' => 'yesterday_stat'],
                        ],
                        [
                            ['text' => "☀️ ماه فعلی ", 'callback_data' => 'month_current_stat'],
                            ['text' => "⛅️ ماه قبل", 'callback_data' => 'month_old_stat'],
                        ],
                        [
                            ['text' => "🗓 مشاهده آمار در تاریخ مشخص", 'callback_data' => 'view_stat_time'],
                        ],
                        [
                            ['text' => "❌ بستن", 'callback_data' => 'close_stat'],
                        ]
                    ]
                ]);
    }
}
