<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class UsersRequestHelpers
{
    public static function fx_users_summary(PDO $pdo): array
{
    return \Faoxima\Admin\Users\UserDirectory::summary($pdo);
}

    public static function fx_users_page(PDO $pdo, string $userQ, string $userFilter): array
{
    return \Faoxima\Admin\Users\UserDirectory::page($pdo, $userQ, $userFilter);
}
}
