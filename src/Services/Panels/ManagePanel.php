<?php

namespace Faoxima\Services\Panels;

/**
 * Panel provider facade used by the Telegram bot, the web panel, the Mini App API, the payment callbacks and Cron.
 * The operations live in the Panel* traits next to this file; the provider drivers are in Drivers/. The root panels.php
 * registers this class under its historical global name `ManagePanel`.
 */
class ManagePanel
{
    use PanelInspection;
    use PanelProvisioning;
    use PanelLifecycle;
    use PanelRenewal;
    use PanelExtras;

    public $pdo, $domainhosts, $name_panel;



    private static $panelRowCache = [];
}
