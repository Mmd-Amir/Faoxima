<?php

namespace Faoxima\Admin\Web\Pages;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $result.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class IndexRequest
{
    public static function handle(
        $pdo,
        &$result
    ) {
        $sessionUser = isset($_SESSION["user"]) && is_string($_SESSION["user"]) && $_SESSION["user"] !== ''
            ? $_SESSION["user"]
            : null;

        if ($sessionUser === null) {
            header('Location: login.php');
            exit;
        }

        $result = \Faoxima\Admin\Web\Support\Csrf::fx_admin_find_by_username($pdo, $sessionUser);

        if (!$result) {
            $_SESSION = [];
            header('Location: login.php');
            exit;
        }




        return true;
    }
}
