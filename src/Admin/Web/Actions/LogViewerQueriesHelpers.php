<?php

namespace Faoxima\Admin\Web\Actions;

final class LogViewerQueriesHelpers
{
    public static function lv_snapshot_secret(): string
{
    $t = (string)($_SESSION['csrf_token'] ?? '');
    if ($t === '') {
        \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('auth', 401);
    }
    return $t . '|' . (string)($_SESSION['user'] ?? '');
}

    public static function lv_param_id(): string
{
    $v = $_REQUEST['id'] ?? '';
    return is_string($v) ? $v : '';
}

    public static function lv_param_cursors(): array
{
    $raw = $_GET['cursors'] ?? '';
    if (!is_string($raw) || $raw === '' || strlen($raw) > 16000) {
        return [];
    }
    $d = json_decode($raw, true);
    $out = [];
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            if (is_int($v) || is_float($v)) {
                $out[(string)$k] = (int)$v;
            }
        }
    }
    return $out;
}

    public static function lv_param_limit(int $default = 50): int
{
    $l = isset($_GET['limit']) ? (int)$_GET['limit'] : $default;
    return max(1, min(LV_MAX_LIMIT, $l));
}

    public static function lv_param_source(): ?string
{
    $s = $_GET['source'] ?? '';
    return in_array($s, ['logs', 'php'], true) ? $s : null;
}
}
