<?php

namespace Faoxima\Admin\Web\Pages;

/**
 * Handler stage. Inherited by value: $adminWebPage.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ManualInventoryConfigurationStore
{
    public static function handle(
        $adminWebPage
    ) {
        if (($adminWebPage ?? null) !== 'manual_service.php') {
            return;
        }





        return true;
    }
}
