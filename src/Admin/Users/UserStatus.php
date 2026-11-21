<?php

declare(strict_types=1);

namespace Faoxima\Admin\Users;

final class UserStatus
{
    public static function change($userId, string $status, callable $update): void
    {
        if (in_array($status, ['block', 'active', 'Active'], true)) {
            $update('user', 'User_Status', $status, 'id', $userId);
        }
    }
}
