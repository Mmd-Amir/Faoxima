<?php

namespace Faoxima\Telegram\Handlers;

final class TariffMenu
{
    public static function show($chat, $callback, $message, $label, callable $enter, callable $send): void
    {
        $enter('tariff', $chat, $callback, $message, ['Tariff_list']);
        $send($chat, $label, null, 'HTML');
    }

    public static function close($chat, $message, $label, $keyboard, callable $delete, callable $send): void
    {
        $delete($chat, $message);
        $send($chat, $label, $keyboard, 'HTML');
    }
}
