<?php

declare(strict_types=1);

namespace Faoxima\Core\Bootstrap;

use Faoxima\Core\Config\RuntimeConfig;

final readonly class Application
{
    private function __construct(
        public string $root,
        public RuntimeConfig $config,
    ) {
    }

    public static function boot(): self
    {
        return new self(dirname(__DIR__, 3), new RuntimeConfig());
    }
}
