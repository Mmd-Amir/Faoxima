<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $action, $btn, $currentJson, $keyboard, $method, $payload, $primaryKeys, $query, $row, $settingRow, $textbotMap.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class KeyboardRequest
{
    public static function handle(
        &$action,
        &$btn,
        &$currentJson,
        &$keyboard,
        &$method,
        &$payload,
        $pdo,
        &$primaryKeys,
        &$query,
        &$row,
        &$settingRow,
        &$textbotMap
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
        $query->execute();
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!isset($_SESSION["user"]) || !$adminRow) {
            header('Location: login.php');
            exit;
        }


        $DEFAULT_KEYBOARD_JSON = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';


        $action = filter_input(INPUT_GET, 'action');
        if ($action === 'reaset' || $action === 'reset') {
            \Faoxima\Core\Database\Db::update("setting", "keyboardmain", $DEFAULT_KEYBOARD_JSON, null, null);
            header('Location: keyboard.php');
            exit;
        }


        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $keyboard = json_decode($raw, true);
            if (is_array($keyboard)) {

                $clean = [];
                $allowedStyles = ['none', 'primary', 'success', 'danger'];
                foreach ($keyboard as $row) {
                    if (!is_array($row)) continue;
                    $cleanRow = [];
                    foreach ($row as $btn) {
                        if (is_string($btn)) {
                            $cleanRow[] = ['text' => $btn];
                        } elseif (is_array($btn) && isset($btn['text'])) {
                            $b = ['text' => (string)$btn['text']];
                            if (isset($btn['style']) && in_array($btn['style'], $allowedStyles, true)) {
                                $b['style'] = $btn['style'];
                            }
                            $cleanRow[] = $b;
                        }
                    }
                    $clean[] = $cleanRow;
                }
                $payload = ['keyboard' => $clean];
                \Faoxima\Core\Database\Db::update("setting", "keyboardmain", json_encode($payload, JSON_UNESCAPED_UNICODE), null, null);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true]);
                exit;
            }
            header('Content-Type: application/json; charset=utf-8', true, 400);
            echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
            exit;
        }


        $settingRow = \Faoxima\Core\Database\Db::select("setting", "keyboardmain", null, null, "select");
        $currentJson = $DEFAULT_KEYBOARD_JSON;
        if (is_array($settingRow) && !empty($settingRow['keyboardmain'])) {
            $decoded = json_decode($settingRow['keyboardmain'], true);
            if (is_array($decoded) && isset($decoded['keyboard'])) {
                $currentJson = $settingRow['keyboardmain'];
            }
        }


        $textbotMap = [];
        try {
            $textbotRows = \Faoxima\Core\Database\Db::select("textbot", "*", null, null, "fetchAll");
            if (is_array($textbotRows)) {
                foreach ($textbotRows as $row) {
                    if (isset($row['id_text']) && isset($row['text']) && $row['text'] !== '') {
                        $textbotMap[(string)$row['id_text']] = (string)$row['text'];
                    }
                }
            }
        } catch (\Throwable $e) {

            error_log('[panel/keyboard] textbot load failed: ' . $e->getMessage());
        }


        $primaryKeys = [
            'text_sell', 'text_extend', 'text_usertest', 'text_wheel_luck',
            'text_Purchased_services', 'accountwallet',
            'text_affiliates', 'text_Tariff_list',
            'text_support', 'text_help',
            'text_miniapp_button',
        ];
        if (!isset($textbotMap['text_miniapp_button'])) {
            $textbotMap['text_miniapp_button'] = '🚀 Open Mini App';
        }

        return true;
    }
}
