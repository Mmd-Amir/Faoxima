<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class CheckJoinHandler
{
    public static function run(): void
    {
        \Faoxima\Api\Handlers\VerifyHandler::run();
    }
}
