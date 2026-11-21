<?php

namespace Faoxima\Telegram\Conversation;

final class InitializeHelpers
{
    public static function tk_album_send($chat, $topic, $media)
    {
        if (!is_array($media) || count($media) === 0) return;
        if (count($media) === 1) {
            $m = $media[0];
            $p = ['chat_id' => $chat, $m['type'] => $m['file_id']];
            if ($topic) $p['message_thread_id'] = $topic;
            telegram($m['type'] === 'video' ? 'sendvideo' : 'sendphoto', $p);
            return;
        }
        $arr = [];
        foreach ($media as $m) { $arr[] = ['type' => $m['type'], 'media' => $m['file_id']]; }
        $p = ['chat_id' => $chat, 'media' => $arr];
        if ($topic) $p['message_thread_id'] = $topic;
        telegram('sendMediaGroup', $p);
    }
}
