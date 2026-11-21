<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $datain, $datatextbot, $from_id, $keyboard, $message_id, $telegramRoute, $textbotlang.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Tariffs
{
    public static function handle(
        $datain,
        $datatextbot,
        $from_id,
        $keyboard,
        $message_id,
        $telegramRoute,
        $textbotlang
    ) {
        switch ($telegramRoute) {
        case 'tariffs':
            \Faoxima\Telegram\Handlers\TariffMenu::show($from_id, $datain, $message_id, $datatextbot['text_dec_Tariff_list'], [\Faoxima\Telegram\Media\SectionMedia::class, 'rx_section_media_enter'], 'sendmessage');
            break;
        case 'close_list':
            \Faoxima\Telegram\Handlers\TariffMenu::close($from_id, $message_id, $textbotlang['users']['back'], $keyboard, 'deletemessage', 'sendmessage');
            break;
        }

        return true;
    }
}
