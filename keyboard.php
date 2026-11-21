<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
require_once REFACTORED_LEGACY_ROOT . '/src/Core/Logging/functions.php';
@chdir(REFACTORED_LEGACY_ROOT);

// keyboard.php runs in the scope of its includer (global for the payment entries, a function scope inside the Cron payment context):
// the variables the layouts use are captured into a scope array, built, and imported back.
try {
    require_once REFACTORED_LEGACY_ROOT . '/src/bootstrap.php';
    require_once 'config.php';

    $__fxScope = array_intersect_key(
        get_defined_vars(),
        array_flip(array_merge(\Faoxima\Telegram\Keyboard\LayoutRuntime::SCOPE, \Faoxima\Admin\Keyboard\PanelRuntime::SCOPE))
    );
    try {
        if (\Faoxima\Telegram\Keyboard\LayoutRuntime::main($__fxScope) === true) {
            \Faoxima\Telegram\Keyboard\LayoutRuntime::menus($__fxScope);
            \Faoxima\Admin\Keyboard\PanelRuntime::providerPanels($__fxScope);
            \Faoxima\Admin\Keyboard\PanelRuntime::gatewayPanels($__fxScope);
        }
    } finally {
        extract($__fxScope, EXTR_OVERWRITE | EXTR_REFS);
        unset($__fxScope);
    }
    // names the keyboard layouts bound with `global`: the includer sees them as aliases of the globals, as before
    global $_rx_acc_styles, $_rx_tx_styles, $_rx_pay_styles, $_rx_payrcpt_styles, $_rx_adm_styles, $_rx_set_styles, $_rx_shp_styles, $_rx_nav_styles, $_rx_rol_styles, $_rx_gw_styles, $_rx_svc_styles, $_rx_feat_styles, $_rx_chan_styles, $_rx_helpa_styles, $_rx_cat_styles, $_rx_prod_styles, $_rx_pedit_styles;
    unset($_rxStyleRef, $dashboardMenu);
} catch (Throwable $__rx_throwable) {
    \Faoxima\Core\Logging\RuntimeLog::throwable('keyboard', $__rx_throwable);
    throw $__rx_throwable;
}
