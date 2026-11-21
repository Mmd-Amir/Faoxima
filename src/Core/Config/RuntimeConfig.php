<?php

declare(strict_types=1);

namespace Faoxima\Core\Config;

final readonly class RuntimeConfig
{
    public function __construct(
        public string $timezone = 'Asia/Tehran',
        public string $charset = 'UTF-8',
    ) {
    }
}
