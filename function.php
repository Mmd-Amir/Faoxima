<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}

/*
 * The legacy business layer is also used by a few web-panel operations. Its
 * notification helpers remain as inert compatibility adapters so those
 * operations cannot send external messages or fail with an undefined function.
 */
if (!function_exists('telegram')) {
    function telegram($method, $datas = [], $token = null)
    {
        return false;
    }
}
if (!function_exists('sendmessage')) {
    function sendmessage($chat_id, $text, $keyboard = null, $parse_mode = null, $bot_token = null)
    {
        return false;
    }
}
if (!function_exists('Editmessagetext')) {
    function Editmessagetext($chat_id, $message_id, $text, $keyboard = null, $parse_mode = 'HTML')
    {
        return false;
    }
}
if (!function_exists('deletemessage')) {
    function deletemessage($chat_id, $message_id)
    {
        return false;
    }
}
if (!function_exists('sendDocument')) {
    function sendDocument($chat_id, $documentPath, $caption = '')
    {
        return false;
    }
}
if (!function_exists('stripReplyStyleEmoji')) {
    function stripReplyStyleEmoji(string $text): string
    {
        return $text;
    }
}
if (!function_exists('getInfoCardStatus')) {
    function getInfoCardStatus(): bool
    {
        return false;
    }
}
if (!function_exists('getInfoCardColor')) {
    function getInfoCardColor(): string
    {
        return 'yellow';
    }
}
if (!function_exists('createServiceInfoCard')) {
    function createServiceInfoCard(array $params, string $color = 'yellow', ?string $outputPath = null)
    {
        return null;
    }
}

require __DIR__ . '/re/function.php';
