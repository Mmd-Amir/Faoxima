<?php

namespace Faoxima\Telegram\Conversation;

use PDO;

final class HomeNavigation
{
    public static function reset(PDO $pdo, $fromId, callable $clearUserCache, callable $step): void
    {
        $stmt = $pdo->prepare("UPDATE user SET Processing_value = '0', Processing_value_one = '0', Processing_value_tow = '0', Processing_value_four = '0' WHERE id = ?");
        $stmt->execute([$fromId]);
        $clearUserCache();
        $step('home', $fromId);
    }

    public static function deliver(
        $fromId,
        $callbackId,
        $label,
        $keyboard,
        callable $sendBanner,
        callable $telegram,
        ?callable $removeKeyboard,
    ): void {
        if ($removeKeyboard !== null) {
            $removeKeyboard($fromId);
        }
        if (!empty($callbackId)) {
            @$telegram('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'cache_time' => 0,
            ]);
        }
        $sendBanner($fromId, 'start', $label, $keyboard, 'html');
    }

    public static function back(
        $fromId,
        bool $deleteCurrent,
        &$messageId,
        $text,
        $keyboard,
        callable $delete,
        callable $send,
        callable $step,
        callable $update,
        ?callable $cancelAbandonedCards,
    ): void {
        if ($deleteCurrent) {
            $delete($fromId, $messageId);
        }
        $messageId = $send($fromId, $text, $keyboard, 'html');
        $step('home', $fromId);
        foreach (['Processing_value', 'Processing_value_one', 'Processing_value_tow', 'Processing_value_four'] as $field) {
            $update('user', $field, '0', 'id', $fromId);
        }
        if ($cancelAbandonedCards !== null) {
            $cancelAbandonedCards($fromId, 'user_back', ['methods' => ['cart to cart']]);
        }
    }
}
