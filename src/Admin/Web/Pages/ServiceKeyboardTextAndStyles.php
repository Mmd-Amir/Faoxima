<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Handler stage. Inherited by value: $ALLOWED_STYLES, $ALL_DB_KEYS, $CONFIG_ITEM_KEY_RE, $CONFIG_ITEM_SECTION, $EXPLICIT_STYLES, $MAIN_TOKEN_MAP, $MENUS, $adminWebPage, $pdo, $rxPanelKey, $rxPanelPairs, $rxPanelSec, $rxPanelVal, $tonpayFactoryStyles, $tonpayStyleKey, $tonpayStyleValue.
 * Inherited or exported by reference: $button, $factoryDefaultsMap, $key, $keyboardMain, $kmRaw, $kmRows, $payload, $row, $rxAutoStyleFor, $rxFamilyStyle, $settingRow, $token, $useBuiltinDefaults, $userStylesRaw, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceKeyboardTextAndStyles
{
    public static function handle(
        $ALLOWED_STYLES,
        $ALL_DB_KEYS,
        $CONFIG_ITEM_KEY_RE,
        $CONFIG_ITEM_SECTION,
        $EXPLICIT_STYLES,
        $MAIN_TOKEN_MAP,
        $MENUS,
        $adminWebPage,
        &$button,
        &$factoryDefaultsMap,
        &$key,
        &$keyboardMain,
        &$kmRaw,
        &$kmRows,
        &$payload,
        $pdo,
        &$row,
        &$rxAutoStyleFor,
        &$rxFamilyStyle,
        $rxPanelKey,
        $rxPanelPairs,
        $rxPanelSec,
        $rxPanelVal,
        &$settingRow,
        &$token,
        $tonpayFactoryStyles,
        $tonpayStyleKey,
        $tonpayStyleValue,
        &$useBuiltinDefaults,
        &$userStylesRaw,
        &$value
    ) {
        if (($adminWebPage ?? null) !== 'service_keyboard.php') {
            return;
        }

        foreach ($ALL_DB_KEYS as $_k) {
            $userStylesRaw[$_k] = [];
        }
        $userStylesRaw[$CONFIG_ITEM_SECTION] = [];

        $useBuiltinDefaults = true;
        $settingRow = \Faoxima\Core\Database\Db::select("setting", "keyboard_styles_all", null, null, "select");
        if (is_array($settingRow) && !empty($settingRow['keyboard_styles_all'])) {
            $decoded = json_decode($settingRow['keyboard_styles_all'], true);
            if (is_array($decoded)) {
                if (array_key_exists('_use_defaults', $decoded)) {
                    $useBuiltinDefaults = (bool) $decoded['_use_defaults'];
                }
                foreach ($ALL_DB_KEYS as $m) {
                    if (!empty($decoded[$m]) && is_array($decoded[$m])) {
                        $userStylesRaw[$m] = $decoded[$m];
                    }
                }
                if (!empty($decoded[$CONFIG_ITEM_SECTION]) && is_array($decoded[$CONFIG_ITEM_SECTION])) {
                    $userStylesRaw[$CONFIG_ITEM_SECTION] = $decoded[$CONFIG_ITEM_SECTION];
                }
            }
        }

        $factoryDefaultsMap = [];
        foreach ($ALL_DB_KEYS as $_k) {
            $factoryDefaultsMap[$_k] = [];
        }
        if (function_exists('rx_getKeyboardDefaultStyles')) {
            $rxPanelDefaults = rx_getKeyboardDefaultStyles();
            if (is_array($rxPanelDefaults)) {
                foreach ($rxPanelDefaults as $rxPanelSec => $rxPanelPairs) {
                    if (!is_array($rxPanelPairs) || empty($rxPanelPairs)) { continue; }
                    if (!isset($factoryDefaultsMap[$rxPanelSec])) { $factoryDefaultsMap[$rxPanelSec] = []; }
                    foreach ($rxPanelPairs as $rxPanelKey => $rxPanelVal) {
                        if (in_array($rxPanelVal, $ALLOWED_STYLES, true)) {
                            $factoryDefaultsMap[$rxPanelSec][$rxPanelKey] = $rxPanelVal;
                        }
                    }
                }
            }
            unset($rxPanelDefaults, $rxPanelSec, $rxPanelPairs, $rxPanelKey, $rxPanelVal);
        }
        foreach ($tonpayFactoryStyles as $tonpayStyleKey => $tonpayStyleValue) {
            $factoryDefaultsMap['tonpay_payment'][$tonpayStyleKey] = $tonpayStyleValue;
        }
        unset($tonpayStyleKey, $tonpayStyleValue);

        $rxAutoStyleFor = function (string $btnLabel) use ($ALLOWED_STYLES): string {
            $guessed = function_exists('rx_kb_guess_style_from_text') ? rx_kb_guess_style_from_text($btnLabel) : null;
            return is_string($guessed) && in_array($guessed, $ALLOWED_STYLES, true) ? $guessed : 'primary';
        };

        $rxFamilyStyle = function (string $menuKey, string $btnKey, string $btnLabel, ?string $fallbackKey = null) use ($userStylesRaw, $factoryDefaultsMap, $useBuiltinDefaults, $EXPLICIT_STYLES, $rxAutoStyleFor): string {
            $candidates = [$btnKey];
            if ($useBuiltinDefaults && is_string($fallbackKey) && $fallbackKey !== '' && $fallbackKey !== $btnKey) {
                $candidates[] = $fallbackKey;
            }
            foreach ($candidates as $candidate) {
                $saved = $userStylesRaw[$menuKey][$candidate] ?? null;
                if (is_string($saved) && in_array($saved, $EXPLICIT_STYLES, true)) {
                    return $saved;
                }
            }
            if (!$useBuiltinDefaults) { return 'default'; }
            foreach ($candidates as $candidate) {
                $factory = $factoryDefaultsMap[$menuKey][$candidate] ?? null;
                if (is_string($factory) && in_array($factory, $EXPLICIT_STYLES, true)) { return $factory; }
            }
            return $rxAutoStyleFor($btnLabel);
        };

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && (filter_input(INPUT_GET, 'action') === 'check_log')) {
            header('Content-Type: application/json; charset=utf-8');
            $parsed = json_decode((string) ($settingRow['keyboard_styles_all'] ?? ''), true);
            echo json_encode([
                'db_has_data' => !empty($settingRow['keyboard_styles_all']),
                'sections'    => is_array($parsed) ? array_keys($parsed) : [],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw, true);
            header('Content-Type: application/json; charset=utf-8');
            if (!is_array($payload)) {
                error_log('[SKB_SAVE] invalid payload. raw_len=' . strlen($raw));
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
                exit;
            }

            $changes = $payload['changes'] ?? null;
            $mainChanges = $payload['main_changes'] ?? null;
            $client = $payload['client'] ?? null;
            $revision = $payload['revision'] ?? null;
            $valid = ($payload['patch_version'] ?? null) === 1 && is_array($changes) && is_array($mainChanges)
                && is_string($client) && preg_match('/^[a-z0-9-]{16,80}$/', $client) === 1
                && is_int($revision) && $revision > 0;
            if ($valid) {
                foreach ($changes as $menu => $pairs) {
                    if (!is_array($pairs) || (!isset($MENUS[$menu]) && !in_array($menu, [$CONFIG_ITEM_SECTION, 'button_items'], true))) { $valid = false; break; }
                    foreach ($pairs as $key => $style) {
                        $keyAllowed = $menu === $CONFIG_ITEM_SECTION
                            ? preg_match($CONFIG_ITEM_KEY_RE, (string) $key) === 1
                            : ($menu === 'button_items' ? preg_match('/^[a-f0-9]{64}$/', (string) $key) === 1 : (isset($MENUS[$menu]['buttons'][$key]) || ($menu === 'account' && preg_match('/^rw_pick_[0-9]+$/', (string) $key) === 1)))
                                && !($menu === 'misc_buttons' && $key === 'confirmpaid');
                        if (!$keyAllowed || ($style !== null && (!is_string($style) || !in_array($style, $EXPLICIT_STYLES, true)))) { $valid = false; break 2; }
                    }
                }
                foreach ($mainChanges as $token => $style) {
                    if (!isset($MAIN_TOKEN_MAP[$token]) || ($style !== null && (!is_string($style) || !in_array($style, $EXPLICIT_STYLES, true)))) { $valid = false; break; }
                }
                if (array_key_exists('_use_defaults', $payload) && !is_bool($payload['_use_defaults'])) { $valid = false; }
            }
            if (!$valid) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'invalid_patch']);
                exit;
            }

            $revisions = isset($_SESSION['skb_patch_revisions']) && is_array($_SESSION['skb_patch_revisions']) ? $_SESSION['skb_patch_revisions'] : [];
            if (($revisions[$client] ?? 0) >= $revision) {
                echo json_encode(['ok' => true]);
                exit;
            }

            try {
                if (!$pdo->beginTransaction()) { throw new RuntimeException('transaction_failed'); }
                $locked = $pdo->query('SELECT keyboard_styles_all, keyboardmain FROM setting LIMIT 1 FOR UPDATE');
                $latest = $locked ? $locked->fetch(PDO::FETCH_ASSOC) : false;
                if (!is_array($latest)) { throw new RuntimeException('settings_missing'); }
                $rawStyles = (string) ($latest['keyboard_styles_all'] ?? '');
                $clean = $rawStyles !== '' ? json_decode($rawStyles, true) : [];
                if (!is_array($clean)) { throw new RuntimeException('invalid_saved_styles'); }
                $expected = [];
                foreach ($changes as $menu => $pairs) {
                    if (!$pairs) { continue; }
                    if (isset($clean[$menu]) && !is_array($clean[$menu])) { throw new RuntimeException('invalid_saved_section'); }
                    if (!isset($clean[$menu])) { $clean[$menu] = []; }
                    foreach ($pairs as $key => $style) {
                        if ($style === null) { unset($clean[$menu][$key]); }
                        else { $clean[$menu][$key] = $style; }
                    }
                }
                if (array_key_exists('_use_defaults', $payload)) { $clean['_use_defaults'] = $payload['_use_defaults']; }
                if ($changes || array_key_exists('_use_defaults', $payload)) {
                    $jsonToSave = json_encode($clean, JSON_UNESCAPED_UNICODE);
                    if ($jsonToSave === false) { throw new RuntimeException('styles_encoding_failed'); }
                    $saveStyles = $pdo->prepare('UPDATE setting SET keyboard_styles_all = ?');
                    if (!$saveStyles->execute([$jsonToSave])) { throw new RuntimeException('styles_save_failed'); }
                    $expected['keyboard_styles_all'] = $jsonToSave;
                }
                if ($mainChanges) {
                    $km = json_decode((string) ($latest['keyboardmain'] ?? ''), true);
                    if (!is_array($km) || !isset($km['keyboard']) || !is_array($km['keyboard'])) { throw new RuntimeException('invalid_main_layout'); }
                    $applied = [];
                    foreach ($km['keyboard'] as &$row) {
                        if (!is_array($row)) { continue; }
                        foreach ($row as &$button) {
                            $token = is_array($button) ? ($button['text'] ?? '') : (is_string($button) ? $button : '');
                            if (!is_string($token) || !array_key_exists($token, $mainChanges)) { continue; }
                            if (!is_array($button)) { $button = ['text' => $token]; }
                            if ($mainChanges[$token] === null) { unset($button['style']); }
                            else { $button['style'] = $mainChanges[$token]; }
                            $applied[$token] = true;
                        }
                        unset($button);
                    }
                    unset($row);
                    if (count($applied) !== count($mainChanges)) { throw new RuntimeException('main_layout_changed'); }
                    $mainJson = json_encode($km, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($mainJson === false) { throw new RuntimeException('main_encoding_failed'); }
                    $saveMain = $pdo->prepare('UPDATE setting SET keyboardmain = ?');
                    if (!$saveMain->execute([$mainJson])) { throw new RuntimeException('main_save_failed'); }
                    $expected['keyboardmain'] = $mainJson;
                }
                $verify = $pdo->query('SELECT keyboard_styles_all, keyboardmain FROM setting LIMIT 1');
                $saved = $verify ? $verify->fetch(PDO::FETCH_ASSOC) : false;
                foreach ($expected as $field => $value) {
                    if (!is_array($saved) || ($saved[$field] ?? null) !== $value) { throw new RuntimeException('save_verification_failed'); }
                }
                if (!$pdo->commit()) { throw new RuntimeException('commit_failed'); }
                unset($revisions[$client]);
                $revisions[$client] = $revision;
                $_SESSION['skb_patch_revisions'] = array_slice($revisions, -64, null, true);
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) { \Faoxima\Core\Database\SelectCache::clearSelectCache('setting'); }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('[SKB_SAVE] ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'save_failed']);
                exit;
            }

            echo json_encode(['ok' => true]);
            exit;
        }

        $keyboardMain = [];
        $kmRows = [];
        $kmRow = \Faoxima\Core\Database\Db::select("setting", "keyboardmain", null, null, "select", ['cache' => false]);
        $kmRaw = is_array($kmRow) ? (string) ($kmRow['keyboardmain'] ?? '') : '';

        return true;
    }
}
