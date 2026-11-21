<?php

namespace Faoxima\Api\Endpoints;

/**
 * Handler stage. Inherited by value: none.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Keyboard
{
    public static function handle(
        
    ) {
        header('Content-Type: application/json');
        date_default_timezone_set('Asia/Tehran');
        ini_set('default_charset', 'UTF-8');
        ini_set('error_log', 'error_log');
        
        $textRows = \Faoxima\Core\Database\Db::select('textbot', '*', null, null, 'fetchAll');
        $layoutRow = \Faoxima\Core\Database\Db::select('setting', 'keyboardmain', null, null, 'select');
        echo json_encode(\Faoxima\Api\Handlers\KeyboardLayoutHandler::describe($textRows, $layoutRow['keyboardmain'] ?? null));

        return 1;
    }
}
