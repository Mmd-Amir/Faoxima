<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;
use RuntimeException;

final class ReceiptMessage
{
    public static function updateReceipt(array $receipt, $text): void
    {
        Editmessagetext(
            $receipt['chat_id'], $receipt['message_id'], $text, $receipt['keyboard'],
            'HTML', $receipt['thread_id'] > 0 ? $receipt['thread_id'] : null
        );
    }
}
