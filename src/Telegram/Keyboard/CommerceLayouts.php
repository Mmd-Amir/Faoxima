<?php

namespace Faoxima\Telegram\Keyboard;

final class CommerceLayouts
{
    public static function purchase($backLabel, array $styles, callable $style): array
    {
        $pay = ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_pay_and_get'), 'callback_data' => 'confirmandgetservice'];
        $discount = ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_discount_code'), 'callback_data' => 'aptdc'];
        $back = ['text' => $backLabel, 'callback_data' => 'backuser', '_rxcolor' => 'navigation:confirm_back'];

        return [
            json_encode(['inline_keyboard' => [
                [$style($pay, 'confirm_pay', $styles)],
                [$style($discount, 'confirm_discount', $styles)],
                [$style($back, 'confirm_back', $styles)],
            ]]),
            json_encode(['inline_keyboard' => [
                [$style($pay, 'confirm_pay', $styles)],
                [$style($back, 'confirm_back', $styles)],
            ]]),
        ];
    }

    public static function agent($backLabel, bool $inline, bool $canChooseName): string|false
    {
        $buy = ['text' => '🗂 خرید انبوه'];
        $name = ['text' => '👤 نام دلخواه'];
        $back = ['text' => $backLabel];
        if ($inline) {
            $buy['callback_data'] = 'kharidanbuh';
            $name['callback_data'] = 'selectname';
            $back['callback_data'] = 'backuser';
        }

        $key = $inline ? 'inline_keyboard' : 'keyboard';
        return json_encode([
            $key => [$canChooseName ? [$buy, $name] : [$buy], [$back]],
            'resize_keyboard' => true,
        ]);
    }
}
