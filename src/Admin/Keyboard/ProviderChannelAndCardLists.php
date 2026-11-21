<?php

namespace Faoxima\Admin\Keyboard;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo, $textbotlang.
 * Inherited or exported by reference: $button, $json_list_helpkey, $json_list_marzban_panel, $list_card_remove, $list_marzban_panel_edit_product, $result, $row, $stmt, $table_exists.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderChannelAndCardLists
{
    public static function handle(
        &$button,
        &$json_list_helpkey,
        &$json_list_marzban_panel,
        &$list_card_remove,
        &$list_marzban_panel_edit_product,
        $pdo,
        &$result,
        &$row,
        &$stmt,
        &$table_exists,
        $textbotlang
    ) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'marzban_panel'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        $namepanel = [];
        if ($table_exists) {
            $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> '' ORDER BY (position = 0) ASC, position ASC, id ASC");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rxPanelName = trim((string)$row['name_panel']);
                if ($rxPanelName === '' || $rxPanelName[0] === '{' || $rxPanelName[0] === '[') continue;
                $namepanel[] = [$row['name_panel']];
            }
            $list_marzban_panel = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            foreach ($namepanel as $button) {
                $list_marzban_panel['keyboard'][] = [
                    ['text' => $button[0]]
                ];
            }
                $list_marzban_panel['keyboard'][] = [
                ['text' => $textbotlang['Admin']['backadmin']],
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
            ];
            $json_list_marzban_panel = json_encode($list_marzban_panel);

            $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> '' ORDER BY (position = 0) ASC, position ASC, id ASC");
            $stmt->execute();
            $list_marzban_panel_edit_product = ['inline_keyboard' => []];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rxPanelName = trim((string)$row['name_panel']);
                if ($rxPanelName === '' || $rxPanelName[0] === '{' || $rxPanelName[0] === '[') continue;
                $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>$row['name_panel'],'callback_data' => 'locationedit_'.$row['code_panel']]];
            }
            $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"همه پنل ها",'callback_data' => 'locationedit_all']];
            $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"▶️ بازگشت به منوی قبل",'callback_data' => 'backproductadmin']];
            $list_marzban_panel_edit_product = json_encode($list_marzban_panel_edit_product);
        }

        $stmt = $pdo->prepare("SHOW TABLES LIKE 'channels'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        $list_channels = [];
        if ($table_exists) {
            $stmt = $pdo->prepare("SELECT * FROM channels");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $list_channels[] = [$row['link']];
            }
            $list_channels_join = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            foreach ($list_channels as $button) {
                $list_channels_join['keyboard'][] = [
                    ['text' => $button[0]]
                ];
            }
                $list_channels_join['keyboard'][] = [
                ['text' => $textbotlang['Admin']['backadmin']],
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
            ];
            $list_channels_joins = json_encode($list_channels_join);
        }

        $stmt = $pdo->prepare("SHOW TABLES LIKE 'card_number'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        $list_card = [];
        if ($table_exists) {
            $stmt = $pdo->prepare("SELECT * FROM card_number");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $list_card[] = [$row['cardnumber']];
            }
            $list_card_remove = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            foreach ($list_card as $button) {
                $list_card_remove['keyboard'][] = [
                    ['text' => $button[0]]
                ];
            }
                $list_card_remove['keyboard'][] = [
                ['text' => $textbotlang['Admin']['backadmin']],
                ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
            ];
            $list_card_remove = json_encode($list_card_remove);
        }

            $stmt = $pdo->prepare("SHOW TABLES LIKE 'help'");
            $stmt->execute();
            $result = $stmt->fetchAll();
            $table_exists = count($result) > 0;
            if ($table_exists) {
            $stmt = $pdo->prepare("SELECT * FROM help");
            $stmt->execute();
            $helpkey = [];
            $stmt = $pdo->prepare("SELECT * FROM help");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $helpkey[] = [$row['name_os']];
                }
                $help_arrke = [
                    'keyboard' => [],
                    'resize_keyboard' => true,
                ];
                foreach ($helpkey as $button) {
                    $help_arrke['keyboard'][] = [
                        ['text' => $button[0]]
                    ];
                }
                        $help_arrke['keyboard'][] = [
                    ['text' => $textbotlang['Admin']['backmenu']],
                ];
                $json_list_helpkey = json_encode($help_arrke);
        }

        return 1;
    }
}
