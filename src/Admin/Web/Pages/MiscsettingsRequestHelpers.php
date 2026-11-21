<?php

namespace Faoxima\Admin\Web\Pages;

final class MiscsettingsRequestHelpers
{
    public static function faoxima_misc_enabled($value): bool
{
    return !in_array(strtolower(trim((string)$value)), ['0', 'off', 'false', 'no'], true);
}
}
