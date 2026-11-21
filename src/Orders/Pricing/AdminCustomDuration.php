<?php

namespace Faoxima\Orders\Pricing;

final class AdminCustomDuration
{
    public static function rx_duration_admin_handle(): bool
{
    global $text, $datain, $user, $from_id, $adminrulecheck, $callback_query_id, $update, $pdo;

    require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
    return \Faoxima\Admin\Telegram\Conversation\CustomDuration::handle($text, $datain, $user, $from_id, $adminrulecheck, $callback_query_id, $update, $pdo);
}
}
