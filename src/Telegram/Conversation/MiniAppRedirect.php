<?php

namespace Faoxima\Telegram\Conversation;

final class MiniAppRedirect
{
    public static function forced($settings, $sender, array $adminIds): bool
    {
        return is_array($settings)
            && (string) ($settings['forced_miniapp_mode'] ?? '0') === '1'
            && !in_array((string) $sender, $adminIds, true);
    }

    public static function tickets($settings): bool
    {
        return (string) ($settings['miniapp_ticket_mode'] ?? '0') === '1';
    }

    public static function show(
        $chat,
        $message,
        $firstName,
        $domain,
        string $template,
        string $appPath,
        callable $edit,
        callable $send,
        ?callable $acknowledge = null,
    ): void {
        $first = trim((string) $firstName);
        $text = str_replace('{first_name}', $first !== '' ? $first : 'کاربر', $template);
        $host = rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
        $keyboard = $host !== '' ? json_encode(['inline_keyboard' => [[[
            'text' => '🚀 باز کردن مینی‌اپ',
            'web_app' => ['url' => 'https://' . $host . $appPath],
        ]]]], JSON_UNESCAPED_UNICODE) : null;

        if ($acknowledge !== null) {
            $acknowledge();
        }
        if (intval($message) > 0) {
            $edit($chat, $message, $text, $keyboard, 'HTML');
        } else {
            $send($chat, $text, $keyboard, 'HTML');
        }
    }
}
