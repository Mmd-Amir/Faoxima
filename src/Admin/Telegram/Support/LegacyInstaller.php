<?php

namespace Faoxima\Admin\Telegram\Support;

/**
 * Compatibility boundary for the schema installer script (table.php), which was written to run at global scope: it reads the
 * configuration variables that config.php created earlier in the request and binds $connect and $pdo with `global`. The script is
 * executed with every global variable mirrored into its scope by reference, so it observes and updates exactly what an include at
 * global scope would.
 */
final class LegacyInstaller
{
    public static function runTableScript(string $path): void
    {
        $mirror = [];
        foreach ($GLOBALS as $name => $_) {
            if ($name === 'GLOBALS' || $name === 'path' || $name === 'mirror' || $name === 'name' || $name === '_' || (isset($name[0]) && $name[0] === '_' && in_array($name, ['_GET', '_POST', '_COOKIE', '_FILES', '_SERVER', '_ENV', '_REQUEST', '_SESSION'], true))) {
                continue;
            }
            $mirror[$name] = &$GLOBALS[$name];
        }
        unset($_, $name);
        extract($mirror, EXTR_SKIP | EXTR_REFS);
        unset($mirror);
        include $path;
    }
}
