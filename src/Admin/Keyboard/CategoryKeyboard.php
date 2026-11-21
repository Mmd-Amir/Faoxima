<?php

namespace Faoxima\Admin\Keyboard;

use PDO;

final class CategoryKeyboard
{
    public static function build(PDO $pdo, $textbotlang, ?callable $toInline, ?callable $finalize)
    {
        $stmt = $pdo->prepare("SELECT * FROM category ORDER BY (position = 0) ASC, position ASC, id ASC");
        $stmt->execute();
        $list_category = [
            'keyboard' => [],
            'resize_keyboard' => true,
        ];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $list_category['keyboard'][] = [['text' => $row['remark']]];
        }
        $list_category['keyboard'][] = [
            ['text' => $textbotlang['Admin']['backadmin']],
        ];

        $json = json_encode($list_category);
        if ($toInline !== null) {
            return $toInline($list_category);
        }
        if ($finalize !== null) {
            return $finalize($json, true);
        }
        return $json;
    }
}
