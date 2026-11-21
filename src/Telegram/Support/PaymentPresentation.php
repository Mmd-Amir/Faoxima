<?php

namespace Faoxima\Telegram\Support;

final class PaymentPresentation
{
    public function __construct(
        public mixed &$language,
        public mixed &$renewalKeyboard,
        public mixed &$keyboard,
        public mixed &$confirmationKeyboard,
        public mixed &$requestUser,
        public mixed &$messageId,
        public mixed &$templates,
        public mixed $update
    ) {
    }

    public function receipt(array $payment): array
    {
        $chat = trim((string) ($payment['report_chat_id'] ?? ''));
        $message = (int) ($payment['report_message_id'] ?? 0);
        $thread = (int) ($payment['report_thread_id'] ?? 0);
        if ($chat !== '' && $message > 0) {
            $this->messageId = $message;
        } else {
            $callbackMessage = $this->update['callback_query']['message'] ?? [];
            $chat = $callbackMessage['chat']['id'] ?? $this->requestUser;
            $thread = (int) ($callbackMessage['message_thread_id'] ?? 0);
        }
        return [
            'chat_id' => $chat,
            'message_id' => &$this->messageId,
            'keyboard' => &$this->confirmationKeyboard,
            'thread_id' => $thread,
        ];
    }
}
