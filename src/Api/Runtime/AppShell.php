<?php

namespace Faoxima\Api\Runtime;

final class AppShell
{
    public static function fx_asset_v(string $absPath, string $versionSafe, string $hardR): string {
    $stamp = (string) @filemtime($absPath);
    return $versionSafe . '.' . $stamp . ($hardR !== '' ? '.' . $hardR : '');
}
}
