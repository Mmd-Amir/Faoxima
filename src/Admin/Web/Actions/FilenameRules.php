<?php

namespace Faoxima\Admin\Web\Actions;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $fnAction, $pdo.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class FilenameRules
{
    public static function handle(
        $fnAction,
        $pdo
    ) {
        if ($fnAction === 'rules') {
            $rows = $pdo->query('SELECT * FROM config_file_rule ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
            $rules = [];
            foreach ((array) $rows as $row) {
                $panelName = (string) ($row['panel_name'] ?? '');
                $panelCode = (string) ($row['panel_code'] ?? '');
                if ($panelCode !== '') {
                    $stmt = $pdo->prepare('SELECT name_panel, type FROM marzban_panel WHERE code_panel = ? LIMIT 1');
                    $stmt->execute([$panelCode]);
                    $panelRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (is_array($panelRow)) {
                        $panelName = (string) ($panelRow['name_panel'] ?? $panelName);
                    }
                }
                $rules[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'panel_ref' => (string) ($row['panel_ref'] ?? ''),
                    'panel_code' => $panelCode,
                    'panel_name' => $panelName,
                    'panel_type' => (string) ($row['panel_type'] ?? ''),
                    'source' => (string) ($row['source'] ?? ''),
                    'source_service' => (string) ($row['source_service'] ?? ''),
                    'match_key' => (string) ($row['match_key'] ?? ''),
                    'requires_reselect' => in_array((string) ($row['source'] ?? ''), ['xui_wg', 'xui_file', 'rebecca', 'jovad'], true)
                        && strpos((string) ($row['match_key'] ?? ''), '|') === false
                        && empty(\Faoxima\Services\Delivery\FileNaming::rxFileNamingLegacyRuleKeys($row)),
                    'match_label' => (string) ($row['match_label'] ?? ''),
                    'match_basis' => (string) ($row['match_basis'] ?? ''),
                    'match_basis_label' => \Faoxima\Services\Delivery\FileNaming::rxFileNamingBasisLabel($row['match_basis'] ?? ''),
                    'file_ext' => (string) ($row['file_ext'] ?? ''),
                    'custom_filename' => (string) ($row['custom_filename'] ?? ''),
                    'custom_caption' => (string) ($row['custom_caption'] ?? ''),
                    'is_enabled' => ((int) ($row['is_enabled'] ?? 0)) === 1,
                    'updated_at' => (int) ($row['updated_at'] ?? 0),
                ];
            }
            \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'rules' => $rules, 'master' => \Faoxima\Services\Delivery\FileNaming::rxFileNamingEnabled(true)]);
        }

        if ($fnAction === 'preview') {
            $custom = \Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('custom_filename');
            $ext = strtolower(ltrim(\Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('file_ext', 'conf'), '.'));
            $wireguard = ($ext === 'conf');
            $check = \Faoxima\Services\Delivery\FileNaming::rxFileNamingValidateCustomName($custom, $ext, $wireguard);
            $caption = html_entity_decode(\Faoxima\Services\Delivery\FileNaming::rxFileNamingMergeCaption('', \Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('custom_caption')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            \Faoxima\Admin\Web\Support\FilenamingApi::fn_json([
                'ok' => true,
                'filename' => $check['filename'],
                'changed' => $check['changed'],
                'error' => $check['error'],
                'wireguard' => $wireguard,
                'caption' => $caption,
                'caption_length' => function_exists('mb_strlen') ? mb_strlen($caption, 'UTF-8') : strlen($caption),
            ]);
        }

        if ($fnAction === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $panelRef = \Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('panel_ref');
            $panel = \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_panel_by_ref($panelRef);
            if ($panel === null) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('panel_not_found', 404);
            }
            $token = \Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('target_token');
            $parts = explode('.', $token);
            $secret = (string) ($_SESSION['file_naming_secret'] ?? '');
            if (count($parts) !== 2 || $secret === '' || !hash_equals(hash_hmac('sha256', $parts[0], $secret), $parts[1])) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_target');
            }
            $decoded = base64_decode($parts[0], true);
            $selection = is_string($decoded) ? json_decode($decoded, true) : null;
            if (!is_array($selection) || (int) ($selection['expires'] ?? 0) < time()
                || ($selection['panel_ref'] ?? '') !== $panelRef
                || ($selection['service'] ?? '') !== \Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('source_service')
                || !is_array($selection['target'] ?? null)) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_target');
            }
            $verifiedTarget = $selection['target'];
            foreach (['source' => 'source', 'match_key' => 'key', 'file_ext' => 'ext', 'match_label' => 'label'] as $posted => $verified) {
                if (\Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string($posted) !== (string) ($verifiedTarget[$verified] ?? '')) {
                    \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_target');
                }
            }
            $source = \Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('source');
            $allowedSources = ['xui_wg', 'xui_file', 'rebecca', 'jovad', 'wgdashboard', 'manual'];
            if (!in_array($source, $allowedSources, true)) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_source');
            }
            $matchKey = \Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('match_key');
            $keyPrefixes = ['label:', 'endpoint:', 'file:', 'record:', 'sub:', 'server:'];
            $keyAllowed = false;
            foreach ($keyPrefixes as $prefix) {
                if (strpos($matchKey, $prefix) === 0) {
                    $keyAllowed = true;
                    break;
                }
            }
            $matchKeyLength = function_exists('mb_strlen') ? mb_strlen($matchKey, 'UTF-8') : strlen($matchKey);
            if (!$keyAllowed || $matchKeyLength > 191) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_target');
            }
            $matchLabel = \Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('match_label');
            if (function_exists('mb_substr')) {
                $matchLabel = mb_substr($matchLabel, 0, 255, 'UTF-8');
            } else {
                $matchLabel = substr($matchLabel, 0, 255);
            }
            $basisFromKey = substr($matchKey, 0, (int) strpos($matchKey, ':'));
            $basis = in_array($basisFromKey, ['label', 'endpoint', 'file', 'record', 'sub', 'server'], true) ? $basisFromKey : 'label';
            $allowedBasisBySource = [
                'xui_wg' => ['label', 'endpoint', 'file'],
                'xui_file' => ['file'],
                'rebecca' => ['label', 'file'],
                'jovad' => ['file', 'server'],
                'wgdashboard' => ['sub'],
                'manual' => ['record', 'file'],
            ];
            if (!in_array($basis, $allowedBasisBySource[$source] ?? ['label'], true)) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_target');
            }
            $ext = strtolower(ltrim(\Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('file_ext', 'conf'), '.'));
            if (!preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_extension');
            }
            $wireguard = ($ext === 'conf');
            $check = \Faoxima\Services\Delivery\FileNaming::rxFileNamingValidateCustomName(\Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('custom_filename'), $ext, $wireguard);
            if (!$check['ok']) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_filename', 400, ['preview' => $check]);
            }
            $caption = \Faoxima\Services\Delivery\FileNaming::rxFileNamingTrimCaption(\Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('custom_caption'), 1024);
            $service = \Faoxima\Admin\Web\Support\FilenamingApi::fn_post_string('source_service');
            if (function_exists('mb_substr')) {
                $service = mb_substr($service, 0, 191, 'UTF-8');
            } else {
                $service = substr($service, 0, 191);
            }
            $enabled = isset($_POST['is_enabled']) && (string) $_POST['is_enabled'] !== '0' ? 1 : 0;
            $panelCode = (string) ($panel['code_panel'] ?? '');
            $panelName = (string) ($panel['name_panel'] ?? '');
            $panelType = (string) ($panel['type'] ?? '');

            $dupStmt = $pdo->prepare('SELECT id FROM config_file_rule WHERE panel_ref = ? AND source = ? AND match_key = ? AND id <> ? LIMIT 1');
            $dupStmt->execute([$panelRef, $source, $matchKey, $id]);
            $duplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($duplicate)) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('duplicate_rule', 409);
            }

            $now = time();
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE config_file_rule SET panel_ref = ?, panel_code = ?, panel_name = ?, panel_type = ?, source = ?, source_service = ?, match_key = ?, match_label = ?, match_basis = ?, file_ext = ?, custom_filename = ?, custom_caption = ?, is_enabled = ?, updated_at = ? WHERE id = ?');
                    $stmt->execute([$panelRef, $panelCode, $panelName, $panelType, $source, $service, $matchKey, $matchLabel, $basis, $ext, $check['filename'], $caption, $enabled, $now, $id]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO config_file_rule (panel_ref, panel_code, panel_name, panel_type, source, source_service, match_key, match_label, match_basis, file_ext, custom_filename, custom_caption, is_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$panelRef, $panelCode, $panelName, $panelType, $source, $service, $matchKey, $matchLabel, $basis, $ext, $check['filename'], $caption, $enabled, $now, $now]);
                    $id = (int) $pdo->lastInsertId();
                }
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'duplicate') !== false) {
                    \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('duplicate_rule', 409);
                }
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('save_failed', 500);
            }
            \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'id' => $id, 'filename' => $check['filename']]);
        }

        if ($fnAction === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_id');
            }
            $stmt = $pdo->prepare('DELETE FROM config_file_rule WHERE id = ?');
            $stmt->execute([$id]);
            \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true]);
        }

        if ($fnAction === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_id');
            }
            $enabled = isset($_POST['is_enabled']) && (string) $_POST['is_enabled'] !== '0' ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE config_file_rule SET is_enabled = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$enabled, time(), $id]);
            \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'is_enabled' => $enabled === 1]);
        }

        if ($fnAction === 'master') {
            $enabled = isset($_POST['is_enabled']) && (string) $_POST['is_enabled'] !== '0' ? '1' : '0';
            try {
                $stmt = $pdo->prepare('UPDATE setting SET config_file_custom_status = ?');
                $stmt->execute([$enabled]);
            } catch (Throwable $e) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('save_failed', 500);
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('setting');
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'faoxima_bust_bot_selectcache')) {
                \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('setting');
            }
            if (method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingEnabled')) {
                \Faoxima\Services\Delivery\FileNaming::rxFileNamingEnabled(true);
            }
            \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'is_enabled' => $enabled === '1']);
        }

        \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('unknown_action', 404);

        return 1;
    }
}
