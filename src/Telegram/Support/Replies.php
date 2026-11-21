<?php

namespace Faoxima\Telegram\Support;

use FxTelegramCallbackScope;
use Throwable;

final class Replies
{
    public static function rx_auth_skip_user($user)
    {
        return false;
    }

    public static function rxSettingFlag($key, $default = true)
    {
        global $setting;
        $value = null;
        if (is_array($setting ?? null) && array_key_exists($key, $setting)) {
            $value = $setting[$key];
        } elseif (\Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                $row = \Faoxima\Core\Database\Db::select('setting', $key, null, null, 'select');
                if (is_array($row) && array_key_exists($key, $row)) {
                    $value = $row[$key];
                } elseif (is_string($row) || is_numeric($row)) {
                    $value = $row;
                }
            } catch (Throwable $e) {
                $value = null;
            }
        }
        if ($value === null || $value === '') {
            return (bool)$default;
        }
        return !in_array(strtolower(trim((string)$value)), ['0', 'off', 'false', 'no'], true);
    }

    public static function nm_replyOrEdit($chatId, $text, $keyboard = null, $parseMode = 'HTML')
    {
        global $message_id, $callback_query_id;
        $isCallback = !empty($callback_query_id) && !empty($message_id);
        if ($isCallback && function_exists('Editmessagetext')) {

            $isInlineKbd = false;
            if (is_string($keyboard) && $keyboard !== '') {
                $decoded = json_decode($keyboard, true);
                if (is_array($decoded) && isset($decoded['inline_keyboard'])) $isInlineKbd = true;
            } elseif (is_array($keyboard) && isset($keyboard['inline_keyboard'])) {
                $isInlineKbd = true;
            } elseif ($keyboard === null) {
                $isInlineKbd = true;
            }
            if ($isInlineKbd) {
                try {
                    $rx_edit_result = Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);

                    if (is_array($rx_edit_result) && !empty($rx_edit_result['ok'])) {
                        return true;
                    }
                    $rx_edit_description = is_array($rx_edit_result) ? strtolower((string) ($rx_edit_result['description'] ?? '')) : '';
                    if ($rx_edit_description !== '' && strpos($rx_edit_description, 'message is not modified') !== false) {
                        return true;
                    }
                    if (is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState']) && \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rx_edit_result) === 'unknown') {
                        error_log('nm_replyOrEdit Editmessagetext outcome unknown; fallback suppressed to avoid duplicate delivery');
                        return false;
                    }
                    if (is_array($rx_edit_result) && isset($rx_edit_result['description'])) {
                        error_log('nm_replyOrEdit Editmessagetext not ok: ' . $rx_edit_result['description']);
                    }
                } catch (Throwable $e) {
                    error_log('nm_replyOrEdit Editmessagetext failed: ' . $e->getMessage());
                }
            }
        }
        sendmessage($chatId, $text, $keyboard, $parseMode);
        return false;
    }

    public static function nm_adminInstantReply($chatId, $text, $keyboard = null, $parseMode = 'HTML')
    {
        global $message_id, $callback_query_id;

        $alreadyHandled = !empty($message_id)
                          && isset($GLOBALS['rx_admin_instant_deleted'])
                          && $GLOBALS['rx_admin_instant_deleted'] === $message_id;

        $isCallback = !empty($callback_query_id) && !empty($message_id);
        $isInlineKbd = false;
        if (is_string($keyboard) && $keyboard !== '') {
            $decoded = json_decode($keyboard, true);
            if (is_array($decoded) && isset($decoded['inline_keyboard'])) $isInlineKbd = true;
        } elseif (is_array($keyboard) && isset($keyboard['inline_keyboard'])) {
            $isInlineKbd = true;
        }
        if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavTrackKeyboard') && (string) $chatId === (string) ($GLOBALS['from_id'] ?? '') && function_exists('rx_isAdminChat') && rx_isAdminChat($chatId)) {
            \Faoxima\Telegram\Navigation\NavParents::rxNavTrackKeyboard($chatId, $keyboard);
        }
        $rx_edit_failed = false;
        $rxCallbackAnswered = false;

        if ($isCallback && $isInlineKbd && !$alreadyHandled && function_exists('Editmessagetext')) {
            try {
                if (class_exists('FxTelegramCallbackScope', false)) {
                    $rx_edit_result = FxTelegramCallbackScope::run((string) $callback_query_id,
                        static function () use ($chatId, $message_id, $text, $keyboard, $parseMode) {
                            return Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);
                        }, $rxCallbackAnswered);
                } else {
                    $rx_edit_result = Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);
                }

                if (is_array($rx_edit_result) && !empty($rx_edit_result['ok'])) {

                    $GLOBALS['rx_admin_instant_deleted'] = $message_id;

                    if (!$rxCallbackAnswered && !empty($callback_query_id) && function_exists('telegram')) {
                        try {
                            telegram('answerCallbackQuery', [
                                'callback_query_id' => $callback_query_id,
                                'cache_time' => 1,
                            ]);
                        } catch (Throwable $e) {}
                    }
                    return true;
                }
                $rx_edit_failed = true;
                if (is_array($rx_edit_result) && isset($rx_edit_result['description'])) {
                    $rx_edit_desc = (string) $rx_edit_result['description'];
                    $rx_edit_benign = ['message to edit not found','message to delete not found','message is not modified','query is too old','MESSAGE_ID_INVALID'];
                    $rx_edit_skip_log = false;
                    foreach ($rx_edit_benign as $rx_edit_n) {
                        if (stripos($rx_edit_desc, $rx_edit_n) !== false) { $rx_edit_skip_log = true; break; }
                    }
                    if (!$rx_edit_skip_log) {
                        error_log('nm_adminInstantReply Editmessagetext not ok: ' . $rx_edit_desc);
                    }
                }
            } catch (Throwable $e) {
                $rx_edit_failed = true;
                error_log('nm_adminInstantReply Editmessagetext failed: ' . $e->getMessage());
            }
        }

        if ($rx_edit_failed) {
            if (!$rxCallbackAnswered && !empty($callback_query_id) && function_exists('telegram')) {
                try {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'cache_time' => 1,
                    ]);
                } catch (Throwable $e) {}
            }
            return sendmessage($chatId, $text, $keyboard, $parseMode);
        }

        if ($isCallback) {
            if (!empty($message_id) && !$alreadyHandled && function_exists('deletemessage')) {
                try { @deletemessage($chatId, $message_id); } catch (Throwable $e) {}
                $GLOBALS['rx_admin_instant_deleted'] = $message_id;
            }
            if (!empty($callback_query_id) && function_exists('telegram')) {
                try {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'cache_time' => 1,
                    ]);
                } catch (Throwable $e) {}
            }
            return sendmessage($chatId, $text, $keyboard, $parseMode);
        }

        return sendmessage($chatId, $text, $keyboard, $parseMode);
    }

    public static function rx_replyGroupIdCommand($update, $from_id, $Chat_type)
    {
        global $textbotlang, $datatextbot;
        if ($Chat_type !== 'supergroup') return false;
        if (!isset($update['message']) || !is_array($update['message'])) return false;

        $rxText = (string) ($update['message']['text'] ?? '');
        $rxCmd = trim($rxText);
        $rxAtPos = strpos($rxCmd, '@');
        if ($rxAtPos !== false) $rxCmd = substr($rxCmd, 0, $rxAtPos);
        $rxCmd = mb_strtolower($rxCmd);

        $rxTriggers = ['آیدی', 'ایدی', '/getid', '/getgroupid'];
        if (!in_array($rxCmd, $rxTriggers, true)) return false;

        $chat = $update['message']['chat'] ?? [];
        $rxChatId = isset($chat['id']) ? (string) $chat['id'] : '';
        if ($rxChatId === '' || $rxChatId === '0') return true;
        $rxThreadId = $update['message']['message_thread_id'] ?? null;

        $rxIsBotAdmin = function_exists('rx_isAdminChat') && rx_isAdminChat($from_id);
        if (!$rxIsBotAdmin) {
            $rxSenderStatus = telegram('getChatMember', [
                'chat_id' => $rxChatId,
                'user_id' => $from_id,
            ]);
            $rxSenderRole = $rxSenderStatus['result']['status'] ?? '';
            if (!in_array($rxSenderRole, ['creator', 'administrator'], true)) {
                return true;
            }
        }

        $rxIsForum = !empty($chat['is_forum']);
        if (!$rxIsForum) {
            $rxPayload = [
                'chat_id' => $rxChatId,
                'text' => $datatextbot['dyn_group_setup_topic_not_active'] ?? "⚠️ تاپیک گروه فعال نیست\n\nلطفاً ابتدا از بخش تنظیمات گروه، گزینه تاپیک را فعال کنید؛ سپس مجدداً عبارت «آیدی» یا «ایدی» را داخل گروه ارسال نمایید.",
            ];
            if ($rxThreadId) $rxPayload['message_thread_id'] = $rxThreadId;
            telegram('sendmessage', $rxPayload);
            return true;
        }

        global $APIKEY;
        $rxBotUserId = (int) explode(':', (string) $APIKEY)[0];
        $rxBotMember = $rxBotUserId > 0 ? telegram('getChatMember', [
            'chat_id' => $rxChatId,
            'user_id' => $rxBotUserId,
        ]) : null;
        $rxBotRole = $rxBotMember['result']['status'] ?? '';
        if (!in_array($rxBotRole, ['creator', 'administrator'], true)) {
            $rxPayload = [
                'chat_id' => $rxChatId,
                'text' => $datatextbot['dyn_group_setup_insufficient_access'] ?? "⚠️ دسترسی ربات کافی نیست\n\nلطفاً ابتدا ربات را در این گروه ادمین کنید و دسترسی‌های موردنیاز را در اختیار آن قرار دهید؛ سپس دوباره عبارت «آیدی» یا «ایدی» را ارسال نمایید.",
            ];
            if ($rxThreadId) $rxPayload['message_thread_id'] = $rxThreadId;
            telegram('sendmessage', $rxPayload);
            return true;
        }

        $rxPayload = [
            'chat_id' => $rxChatId,
            'text' => sprintf($datatextbot['dyn_group_setup_chat_id_received'] ?? "✅ آیدی عددی گروه با موفقیت دریافت شد\n\nآیدی عددی این گروه:\n%s\n\nلطفاً این شناسه را کپی کرده و در بخش تنظیم گروه گزارشات ربات ارسال نمایید. 📋", $rxChatId),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '📋 کپی آیدی گروه', 'copy_text' => ['text' => $rxChatId]]],
                ],
            ]),
        ];
        if ($rxThreadId) $rxPayload['message_thread_id'] = $rxThreadId;
        telegram('sendmessage', $rxPayload);
        return true;
    }
}
