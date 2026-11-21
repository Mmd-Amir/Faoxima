<?php

namespace Faoxima\Telegram\Conversation;

final class TicketMedia
{
    public static function accepts($message, $photo, $video): bool
    {
        return ($photo || $video) && !isset($message['animation'])
            && !isset($message['sticker'])
            && !isset($message['document'])
            && !isset($message['voice'])
            && !isset($message['audio'])
            && !isset($message['forward_date'])
            && !isset($message['forward_origin'])
            && !isset($message['forward_from']);
    }
}
