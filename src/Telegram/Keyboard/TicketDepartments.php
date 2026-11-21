<?php

namespace Faoxima\Telegram\Keyboard;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo, $table_exists, $textbotlang.
 * Inherited or exported by reference: $list_departman, $result, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TicketDepartments
{
    public static function handle(
        &$list_departman,
        $pdo,
        &$result,
        &$stmt,
        $table_exists,
        $textbotlang
    ) {
        $list_departman = ['inline_keyboard' => []];

        if ($table_exists) {
            $stmt = $pdo->prepare("SELECT * FROM departman");
            $stmt->execute();
            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $list_departman['inline_keyboard'][] = [[
                    'text' => $result['name_departman'],
                    'callback_data' => "departman_{$result['id']}"
                ]];
            }
        }

        $list_departman['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
        ];
        $list_departman = json_encode($list_departman);

        return 1;
    }
}
