<?php

declare(strict_types=1);

namespace Faoxima\Admin\Security;

final readonly class TelegramAccess
{
    private function __construct(public int|string $senderId)
    {
    }

    public static function forSender(mixed $senderId, mixed $adminIds): ?self
    {
        if ((!is_int($senderId) && !is_string($senderId)) || !is_array($adminIds)) {
            return null;
        }

        return in_array($senderId, $adminIds) ? new self($senderId) : null;
    }
}
