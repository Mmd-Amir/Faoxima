<?php

namespace Faoxima\Payments\Gateways\Tronado;

final class DisputeCallbacks
{
    public static function tronado_dispute_respond($code, $body)
{
    http_response_code($code);
    exit($body);
}
}
