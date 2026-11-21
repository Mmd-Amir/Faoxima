<?php

namespace Faoxima\Telegram\Context;

final readonly class UpdateContext
{
    public function __construct(public array $update)
    {
    }

    public function sender(): array
    {
        $update = $this->update;
        $fromId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? $update['inline_query']['from']['id'] ?? 0;
        $time = $update['message']['date'] ?? $update['callback_query']['date'] ?? $update['inline_query']['date'] ?? 0;
        $isBot = $update['message']['from']['is_bot'] ?? false;
        $chatMember = $update['chat_member'] ?? null;
        $language = strtolower($update['message']['from']['language_code'] ?? $update['callback_query']['from']['language_code'] ?? 'fa');
        $chatType = $update['message']['chat']['type'] ?? $update['callback_query']['message']['chat']['type'] ?? '';
        $text = $update['message']['text'] ?? '';
        if (isset($update['pre_checkout_query'])) {
            $chatType = 'private';
            $fromId = $update['pre_checkout_query']['from']['id'];
        }
        return [$fromId, $time, $isBot, $chatMember, $language, $chatType, $text];
    }

    public function message(?callable $extractRichText = null): array
    {
        $update = $this->update;
        $inlineText = $update['callback_query']['message']['text'] ?? '';
        if ($inlineText === '' && isset($update['callback_query']['message']['rich_message']) && $extractRichText !== null) {
            $inlineText = $extractRichText($update['callback_query']['message']['rich_message']);
        }
        $messageId = self::numericId($update['message']['message_id'] ?? $update['callback_query']['message']['message_id'] ?? 0);
        $time = $update['message']['date'] ?? $update['callback_query']['date'] ?? 0;
        $photo = $update['message']['photo'] ?? 0;
        $document = $update['message']['document'] ?? 0;
        $documentId = $update['message']['document']['file_id'] ?? 0;
        $photoId = $photo ? end($photo)['file_id'] : '';
        $caption = $update['message']['caption'] ?? '';
        $video = $update['message']['video'] ?? 0;
        $videoId = $video ? $video['file_id'] : 0;
        $forwardId = self::numericId($update['message']['reply_to_message']['forward_from']['id'] ?? 0);
        return [
            'inline_text' => $inlineText,
            'message_id' => $messageId,
            'time' => $time,
            'photo' => $photo,
            'document' => $document,
            'document_id' => $documentId,
            'photo_id' => $photoId,
            'caption' => $caption,
            'video' => $video,
            'video_id' => $videoId,
            'forward_id' => $forwardId,
        ];
    }

    public function callbackData(): mixed
    {
        return $this->update['callback_query']['data'] ?? '';
    }

    public function callbackId(): string|int
    {
        return self::queryId($this->update['callback_query']['id'] ?? 0);
    }

    public function inlineQueryId(): string|int
    {
        return self::queryId($this->update['inline_query']['id'] ?? 0);
    }

    private static function queryId($value): string|int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^\d{1,32}$/', (string) $value)) {
            return '';
        }
        return $value;
    }

    public function contactId(): int
    {
        return self::numericId($this->update['message']['contact']['user_id'] ?? 0);
    }

    public function profile(): array
    {
        $message = $this->update['message'] ?? [];
        $callbackSender = $this->update['callback_query']['from'] ?? [];
        $inlineSender = $this->update['inline_query']['from'] ?? [];

        return [
            $message['from']['last_name'] ?? $callbackSender['last_name'] ?? $inlineSender['last_name'] ?? '',
            $message['from']['first_name'] ?? $callbackSender['first_name'] ?? $inlineSender['first_name'] ?? '',
            $message['from']['username'] ?? $callbackSender['username'] ?? 'NOT_USERNAME',
            $message['contact']['phone_number'] ?? 0,
        ];
    }

    public function chatId(): int
    {
        return self::numericId($this->update['message']['chat']['id']
            ?? $this->update['callback_query']['message']['chat']['id']
            ?? 0);
    }

    public static function isNumericId($value): bool
    {
        return is_numeric($value) && (string)(int) $value === (string) $value;
    }

    private static function numericId($value): int
    {
        if (!self::isNumericId($value)) {
            return 0;
        }
        return (int) $value;
    }
}
