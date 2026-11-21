<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $ALLOWED_STYLES, $btn, $buttons, $g, $label, $layoutRows, $payload, $query, $row, $textbotMap, $v.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AccountLayoutRequest
{
    public static function handle(
        &$ALLOWED_STYLES,
        &$btn,
        &$buttons,
        &$g,
        &$label,
        &$layoutRows,
        &$payload,
        $pdo,
        &$query,
        &$row,
        &$textbotMap,
        &$v
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
        $query->execute();
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!isset($_SESSION["user"]) || !$adminRow) {
            header('Location: login.php');
            exit;
        }

        $ALLOWED_STYLES = ['default', 'none', 'primary', 'success', 'danger'];
        $registry = \Faoxima\Telegram\Presentation\AccountLayout::rx_account_button_registry();

        $readStyleState = function () {
            $useDefaults = true;
            $row = \Faoxima\Core\Database\Db::select("setting", "keyboard_styles_all", null, null, "select", ['cache' => false]);
            $decoded = (is_array($row) && !empty($row['keyboard_styles_all'])) ? json_decode($row['keyboard_styles_all'], true) : null;
            if (!is_array($decoded)) {
                $decoded = [];
            }
            if (array_key_exists('_use_defaults', $decoded)) {
                $useDefaults = (bool) $decoded['_use_defaults'];
            }
            return [$decoded, $useDefaults];
        };

        $factoryAccount = [];
        if (function_exists('rx_getKeyboardDefaultStyles')) {
            $factoryAll = rx_getKeyboardDefaultStyles();
            if (is_array($factoryAll) && isset($factoryAll['account']) && is_array($factoryAll['account'])) {
                foreach ($factoryAll['account'] as $k => $v) {
                    if (in_array($v, $ALLOWED_STYLES, true)) {
                        $factoryAccount[$k] = $v;
                    }
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json; charset=utf-8');
            if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'forbidden']);
                exit;
            }
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
                exit;
            }

            if (($payload['action'] ?? '') === 'reset') {
                \Faoxima\Core\Database\Db::update("setting", "account_keyboard_layout", '', null, null);
                echo json_encode(['ok' => true]);
                exit;
            }

            if (!isset($payload['rows']) || !is_array($payload['rows'])) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
                exit;
            }

            $layoutRows = [];
            $picked = [];
            $seen = [];
            foreach ($payload['rows'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $cleanRow = [];
                foreach ($row as $btn) {
                    if (!is_array($btn) || !isset($btn['id']) || !is_string($btn['id'])) {
                        continue;
                    }
                    $id = $btn['id'];
                    if (!isset($registry[$id]) || isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    $cleanRow[] = $id;
                    $style = isset($btn['style']) ? (string) $btn['style'] : 'default';
                    if (in_array($style, $ALLOWED_STYLES, true)) {
                        $picked[$id] = $style;
                    }
                }
                if ($cleanRow) {
                    $layoutRows[] = $cleanRow;
                }
            }
            if (!isset($seen['backuser'])) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'back_required']);
                exit;
            }

            list($allStyles, $useDefaults) = $readStyleState();
            $account = (isset($allStyles['account']) && is_array($allStyles['account'])) ? $allStyles['account'] : [];
            foreach ($picked as $id => $style) {
                $factory = $useDefaults ? ($factoryAccount[$id] ?? null) : null;
                if ($style === 'default' || ($factory !== null && $style === $factory)) {
                    unset($account[$id]);
                } else {
                    $account[$id] = $style;
                }
            }
            $allStyles['account'] = $account;

            \Faoxima\Core\Database\Db::update("setting", "account_keyboard_layout", \Faoxima\Telegram\Presentation\AccountLayout::rx_account_layout_encode($layoutRows), null, null);
            \Faoxima\Core\Database\Db::update("setting", "keyboard_styles_all", json_encode($allStyles, JSON_UNESCAPED_UNICODE), null, null);
            echo json_encode(['ok' => true]);
            exit;
        }

        list($allStyles, $useDefaults) = $readStyleState();
        $accountStyles = (isset($allStyles['account']) && is_array($allStyles['account'])) ? $allStyles['account'] : [];

        $textbotMap = [];
        try {
            $textbotRows = \Faoxima\Core\Database\Db::select("textbot", "*", null, null, "fetchAll");
            if (is_array($textbotRows)) {
                foreach ($textbotRows as $row) {
                    if (isset($row['id_text']) && isset($row['text']) && $row['text'] !== '') {
                        $textbotMap[(string) $row['id_text']] = (string) $row['text'];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[panel/account_layout] textbot load failed: ' . $e->getMessage());
        }

        $buttons = [];
        foreach ($registry as $id => $def) {
            $label = $def['label'];
            if ($def['textbot'] !== null && isset($textbotMap[$def['textbot']])) {
                $label = $textbotMap[$def['textbot']];
            }
            $savedStyle = $accountStyles[$id] ?? null;
            if (!is_string($savedStyle) || $savedStyle === 'default' || !in_array($savedStyle, $ALLOWED_STYLES, true)) {
                $savedStyle = null;
            }
            $style = $savedStyle ?? ($useDefaults ? ($factoryAccount[$id] ?? 'default') : 'default');
            if (!in_array($style, $ALLOWED_STYLES, true)) {
                $style = 'default';
            }
            $guess = 'default';
            if ($useDefaults) {
                $guess = 'primary';
                if (function_exists('rx_kb_guess_style_from_text')) {
                    $g = rx_kb_guess_style_from_text($label);
                    if (is_string($g) && in_array($g, $ALLOWED_STYLES, true)) {
                        $guess = $g;
                    }
                }
            }
            $buttons[$id] = [
                'id' => $id,
                'label' => $label,
                'style' => $style,
                'guess' => $guess,
                'conditional' => $def['condition'] !== null,
                'note' => $def['note'],
                'required' => !empty($def['required']),
            ];
        }

        $layoutRaw = \Faoxima\Core\Database\Db::select("setting", "account_keyboard_layout", null, null, "select", ['cache' => false]);
        $layoutRaw = is_array($layoutRaw) ? trim((string) ($layoutRaw['account_keyboard_layout'] ?? '')) : '';
        $layoutRows = $layoutRaw === '' ? \Faoxima\Telegram\Presentation\AccountLayout::rx_account_default_layout() : \Faoxima\Telegram\Presentation\AccountLayout::rx_account_layout_normalize($layoutRaw);

        return true;
    }
}
