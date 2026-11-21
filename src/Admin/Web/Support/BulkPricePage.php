<?php

namespace Faoxima\Admin\Web\Support;

final class BulkPricePage
{
    public static function fx_bp_respond(int $status, array $body): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    if (!isset($body['message']) && isset($body['error'])) {
        $messages = \Faoxima\Admin\Web\Support\BulkPrice::fx_bp_messages();
        if (isset($messages[$body['error']])) {
            $body['message'] = $messages[$body['error']];
        }
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
}
