<?php

namespace Faoxima\Services\Delivery;

final class AutoFileRateLimit extends \RuntimeException
{
    public function __construct(public readonly int $seconds)
    {
        parent::__construct('Telegram file delivery is rate limited');
    }
}
