<?php

namespace Faoxima\Admin\Web\Pages;

/**
 * Handler stage. Inherited by value: none.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AppearanceRequest
{
    public static function handle(
        
    ) {
        if (empty($_SESSION['user']) || !is_string($_SESSION['user']) || $_SESSION['user'] === '') {
            header('Location: login.php');
            exit;
        }

        return true;
    }
}
