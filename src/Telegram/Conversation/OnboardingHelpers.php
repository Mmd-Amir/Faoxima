<?php

namespace Faoxima\Telegram\Conversation;

final class OnboardingHelpers
{
    public static function rx_config_deliver_list($chatId, $messageId, $text, $keyboard)
    {
        $result = Editmessagetext($chatId, $messageId, $text, $keyboard);
        $ok = is_array($result) && !empty($result['ok']);
        if (!$ok && is_array($result) && stripos((string) ($result['description'] ?? ''), 'message is not modified') !== false) {
            return true;
        }
        if (!$ok) {
            $result = sendmessage($chatId, $text, $keyboard, 'html');
            $ok = is_array($result) && !empty($result['ok']);
        }
        if (!$ok) {
            sendmessage($chatId, \Faoxima\Telegram\Support\MessageTemplates::get('service_config_list_failed'), null, 'html');
        }
        return $ok;
    }
}
