<?php

declare(strict_types=1);

namespace Faoxima\Admin\Users;

use Faoxima\Core\Database\Db;

final class UserStatus
{
    public static function change($userId, string $status, callable $update): void
    {
        if (in_array($status, ['block', 'active', 'Active'], true)) {
            $update('user', 'User_Status', $status === 'active' ? 'Active' : $status, 'id', $userId);
        }
    }

    public static function report(string $chatId, string $text, ?string $replyMarkup = null): void
    {
        if ($chatId === '' || $chatId === '0') {
            return;
        }

        $topic = Db::select('topicid', 'idreport', 'report', 'otherreport', 'select');
        $threadId = (int) ($topic['idreport'] ?? 0);
        if ($threadId <= 0) {
            return;
        }

        $params = [
            'chat_id' => $chatId,
            'message_thread_id' => $threadId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }
        telegram('sendmessage', $params);
    }
}
