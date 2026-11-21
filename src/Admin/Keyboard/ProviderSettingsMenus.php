<?php

namespace Faoxima\Admin\Keyboard;

final class ProviderSettingsMenus
{
    public static function catalog(array $textbotlang, ?array $_rx_pedit_styles): array
    {
        $change_product = json_encode([
            'keyboard' => [
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "قیمت"], 'قیمت', $_rx_pedit_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "حجم"], 'حجم', $_rx_pedit_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "زمان"], 'زمان', $_rx_pedit_styles),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "نام محصول"], 'نام محصول', $_rx_pedit_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "نوع کاربری"], 'نوع کاربری', $_rx_pedit_styles),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "نوع ریست حجم"], 'نوع ریست حجم', $_rx_pedit_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "یادداشت"], 'یادداشت', $_rx_pedit_styles),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "موقعیت محصول"], 'موقعیت محصول', $_rx_pedit_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "دسته بندی"], 'دسته بندی', $_rx_pedit_styles),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🎛 تنظیم اینباند"], '🎛 تنظیم اینباند', $_rx_pedit_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "نمایش برای خرید اول"], 'نمایش برای خرید اول', $_rx_pedit_styles),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "مخفی کردن پنل"], 'مخفی کردن پنل', $_rx_pedit_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "حذف کلی پنل های مخفی"], 'حذف کلی پنل های مخفی', $_rx_pedit_styles),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin']], 'backadmin', $_rx_pedit_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'backmenu', $_rx_pedit_styles),
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

        return [$change_product, $keyboardprotocol, $MethodUsername, $MethodUsernameRemna];
    }

    public static function provider(array $textbotlang): array
    {
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
                    ['text' => "JovadPanel", 'callback_data' => 'typepanel#jovadpanel']
                ],
                [
                    ['text' => "فروش دستی" , 'callback_data' => 'typepanel#Manualsale']
                ],
                [
                    ['text' => $textbotlang['Admin']['backadmin'] , 'callback_data' => 'admin']
                ]
            ],
        ]);
        $keyboardpanelcategory = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "🌐 پنل‌های عمومی (رایگان)", 'callback_data' => 'panelcat#public']
                ],
                [
                    ['text' => "💳 پنل‌های اشتراکی (پولی)", 'callback_data' => 'panelcat#subscription']
                ],
                [
                    ['text' => "📦 فروش دستی", 'callback_data' => 'typepanel#Manualsale']
                ],
                [
                    ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'admin']
                ]
            ],
        ]);
        $keyboardtypepanelpublic = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "Marzban", 'callback_data' => 'typepanel#marzban'],
                    ['text' => "Rebecca", 'callback_data' => 'typepanel#rebecca'],
                    ['text' => "PasarGuard", 'callback_data' => "typepanel#pasargard"]
                ],
                [
                    ['text' => "Remnawave", 'callback_data' => 'typepanel#remnawave'],
                    ['text' => '3x-ui', 'callback_data' => 'typepanel#x-ui_single']
                ],
                [
                    ['text' => "🔙 بازگشت به دسته‌بندی پنل‌ها", 'callback_data' => 'panelcat#root']
                ]
            ],
        ]);
        $keyboardtypepanelsubscription = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "Guard", 'callback_data' => 'typepanel#guard'],
                    ['text' => "JovadPanel", 'callback_data' => 'typepanel#jovadpanel']
                ],
                [
                    ['text' => "🔙 بازگشت به دسته‌بندی پنل‌ها", 'callback_data' => 'panelcat#root']
                ]
            ],
        ]);

        return [$Methodextend, $keyboardtimereset, $keyboardtypepanel, $keyboardpanelcategory, $keyboardtypepanelpublic, $keyboardtypepanelsubscription];
    }
}
