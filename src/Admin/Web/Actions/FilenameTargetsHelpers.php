<?php

namespace Faoxima\Admin\Web\Actions;

use PDO;

final class FilenameTargetsHelpers
{
    public static function fn_panel_by_ref($ref)
{
    global $pdo;
    $ref = trim((string) $ref);
    if ($ref === '') {
        return null;
    }
    if (strpos($ref, 'code:') === 0) {
        $stmt = $pdo->prepare('SELECT * FROM marzban_panel WHERE code_panel = ? LIMIT 1');
        $stmt->execute([substr($ref, 5)]);
    } elseif (strpos($ref, 'name:') === 0) {
        $stmt = $pdo->prepare('SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1');
        $stmt->execute([substr($ref, 5)]);
    } else {
        return null;
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

    public static function fn_panel_targets_manual(array $panel)
{
    global $pdo;
    $key = trim((string) ($panel['code_panel'] ?? ''));
    if ($key === '') {
        return ['targets' => [], 'notice' => 'شناسه پنل فروش دستی در دسترس نیست.'];
    }
    $stmt = $pdo->prepare('SELECT id, namerecord, file_ext, username FROM manualsell WHERE codepanel = ? AND TRIM(contentrecord) <> \'\' ORDER BY id ASC LIMIT 500');
    $stmt->execute([$key]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $targets = [];
    $counts = [];
    foreach ((array) $rows as $row) {
        $ext = strtolower(ltrim(trim((string) ($row['file_ext'] ?? '')), '.'));
        if ($ext === '' || $ext === 'sub' || $ext === 'text') {
            continue;
        }
        $key = \Faoxima\Services\Delivery\FileNaming::rxFileNamingKeyRecord($row['id'] ?? 0);
        if ($key === '') {
            continue;
        }
        $counts[$key] = isset($counts[$key]) ? $counts[$key] + 1 : 1;
        $label = trim((string) ($row['namerecord'] ?? ''));
        if ($label === '') {
            $label = 'رکورد #' . (int) ($row['id'] ?? 0);
        }
        $owner = trim((string) ($row['username'] ?? ''));
        $targets[] = [
            'key' => $key,
            'label' => $label,
            'owner' => $owner,
            'basis' => 'record',
            'hint' => \Faoxima\Services\Delivery\FileNaming::rxFileNamingBasisLabel('record'),
            'ext' => $ext,
            'protocol' => $ext === 'conf' ? 'wireguard' : ($ext === 'ovpn' ? 'openvpn' : ''),
            'source' => 'manual',
            'record_id' => (int) ($row['id'] ?? 0),
        ];
    }
    foreach ($targets as $index => $target) {
        $targets[$index]['ambiguous'] = (($counts[$target['key']] ?? 0) > 1);
    }
    return ['targets' => $targets, 'notice' => ''];
}

    public static function fn_target_from_descriptor(array $descriptor)
{
    $basis = (string) ($descriptor['basis'] ?? 'label');
    return [
        'key' => (string) ($descriptor['key'] ?? ''),
        'label' => (string) ($descriptor['label'] ?? ''),
        'owner' => '',
        'basis' => $basis,
        'hint' => \Faoxima\Services\Delivery\FileNaming::rxFileNamingBasisLabel($basis),
        'ext' => (string) ($descriptor['ext'] ?? 'conf'),
        'protocol' => (string) ($descriptor['protocol'] ?? ''),
        'source' => (string) ($descriptor['source'] ?? ''),
        'record_id' => 0,
    ];
}

    public static function fn_finalize_targets(array $targets, $panelRef, $service)
{
    if (empty($_SESSION['file_naming_secret'])) {
        $_SESSION['file_naming_secret'] = bin2hex(random_bytes(32));
    }
    $counts = [];
    foreach ($targets as $target) {
        $indexKey = ($target['source'] ?? '') . '|' . ($target['key'] ?? '');
        $counts[$indexKey] = isset($counts[$indexKey]) ? $counts[$indexKey] + 1 : 1;
    }
    foreach ($targets as $index => $target) {
        $indexKey = ($target['source'] ?? '') . '|' . ($target['key'] ?? '');
        $targets[$index]['ambiguous'] = (($counts[$indexKey] ?? 0) > 1);
        if (!$targets[$index]['ambiguous']) {
            $payload = base64_encode(json_encode([
                'panel_ref' => $panelRef,
                'service' => $service,
                'target' => $target,
                'expires' => time() + 1800,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $targets[$index]['token'] = $payload . '.' . hash_hmac('sha256', $payload, $_SESSION['file_naming_secret']);
        }
    }
    return $targets;
}
}
