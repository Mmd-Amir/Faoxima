<?php

namespace Faoxima\Telegram\Keyboard;

final class NavigationLayouts
{
    public static function onboarding($backLabel, array $styles, callable $style): array
    {
        $rules = $style(['text' => '✅ قوانین را می پذیرم', 'callback_data' => 'acceptrule'], 'rules_accept', $styles);
        return [
            json_encode(['inline_keyboard' => [[$rules]]]),
            json_encode([
                'keyboard' => [
                    [$style(['text' => '☎️ ارسال شماره تلفن', 'request_contact' => true], 'contact_phone', $styles)],
                    [$style(['text' => $backLabel, 'callback_data' => 'contact_back'], 'contact_back', $styles)],
                ],
                'resize_keyboard' => true,
            ]),
        ];
    }

    public static function username($backLabel, array $accountStyles, array $navigationStyles, callable $style): array
    {
        $back = $style(['text' => $backLabel, 'callback_data' => 'backuser'], 'backuser', $accountStyles);
        $random = $style(['text' => '🎲 ساخت رندوم خودکار', 'callback_data' => 'gen_random_uname'], 'gen_random_uname', $navigationStyles);
        return [
            json_encode(['inline_keyboard' => [[$back]]]),
            json_encode(['inline_keyboard' => [[$random], [$back]]]),
        ];
    }

    public static function customUsername($backAdminLabel, $backMenuLabel): string|false
    {
        return json_encode([
            'keyboard' => [
                [['text' => '🎲 ساخت رندوم خودکار']],
                [['text' => $backAdminLabel], ['text' => $backMenuLabel, 'callback_data' => 'adm_backmenu']],
            ],
            'resize_keyboard' => true,
            'input_field_placeholder' => 'متن دلخواه را وارد کنید یا روی دکمه ساخت رندوم بزنید',
        ]);
    }
}
