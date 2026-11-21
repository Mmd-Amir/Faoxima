<?php

namespace Faoxima\Core\ProtectedRuntime;

/**
 * Administration bootstrap around the protected runtime.
 *
 * The four protected files in this directory (_guard.php, _meta.php, _vx.php, _render.php) are the original files, byte for byte.
 * They are not path-bound: _fx_render() verifies SHA-256 digests of the payload, the shard constant _FX_SHARD and the
 * decrypted result, and falls back to the second payload when the first digest fails. Their key and payload variables ($_gk,
 * $_gd, $_mk, ...) are read by the _fx_* functions through `global`, so they are loaded into the global scope exactly as the
 * original file-scope require did.
 */
final class Bootstrap
{
    public static function run()
    {
        $textadmin = &$GLOBALS['textadmin'];
        $textbotlang = &$GLOBALS['textbotlang'];
        $datain = &$GLOBALS['datain'];
        $text = &$GLOBALS['text'];
        $from_id = &$GLOBALS['from_id'];
        $admin_ids = &$GLOBALS['admin_ids'];
        $text_panel_admin_login_template = &$GLOBALS['text_panel_admin_login_template'];

        $textadmin = ["panel", "/panel", $textbotlang['Admin']['textpaneladmin']];
        if (isset($datain) && $datain != "" && $text == "" && in_array($from_id, $admin_ids)) {
            $text = $datain;
        }
        if (!defined('_FX_SHARD')) {
            define('_FX_SHARD', 'b08d416dac363b09');
        }
        if (!defined('_FX_INIT')) {
            define('_FX_INIT', 1);
        }
        self::loadIntoGlobals(__DIR__ . '/_guard.php');
        self::loadIntoGlobals(__DIR__ . '/_meta.php');
        self::loadIntoGlobals(__DIR__ . '/_vx.php');
        self::loadIntoGlobals(__DIR__ . '/_render.php');
        $text_panel_admin_login_template = _fx_about();
    }

    /** require_once a protected file and publish the variables it defines as globals (what a file-scope require does). */
    private static function loadIntoGlobals(string $protectedFile): void
    {
        require_once $protectedFile;
        foreach (get_defined_vars() as $name => $value) {
            if ($name !== 'protectedFile') {
                $GLOBALS[$name] = $value;
            }
        }
    }
}
