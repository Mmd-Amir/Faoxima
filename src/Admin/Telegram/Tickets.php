<?php

namespace Faoxima\Admin\Telegram;

final class Tickets
{
    public static function rxAdminTicketFind($tracking)
    {
        return \Faoxima\Core\Database\Db::select("support_message", "*", "Tracking", $tracking);
    }

    public static function rxAdminTicketStatus(string $tracking, string $status): void
    {
        \Faoxima\Core\Database\Db::update("support_message", "status", $status, "Tracking", $tracking);
    }
}
