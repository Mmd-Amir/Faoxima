<?php

namespace Faoxima\Admin\Web\Support;

final class OrderEndpoint
{
    public static function fx_order_messages(): array
{
    return [
        'unauthorized' => 'نشست شما منقضی شده است. دوباره وارد شوید.',
        'csrf_invalid' => 'توکن امنیتی نامعتبر است. صفحه را دوباره بارگذاری کنید.',
        'method_not_allowed' => 'روش درخواست مجاز نیست.',
        'invalid_entity' => 'نوع فهرست نامعتبر است.',
        'invalid_payload' => 'داده ارسالی نامعتبر است.',
        'invalid_id' => 'شناسه نامعتبر است.',
        'server_error' => 'خطای داخلی سرور. جزئیات در لاگ سرور ثبت شد.',
    ];
}

    public static function fx_order_respond(int $status, array $body): void
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
        $messages = self::fx_order_messages();
        if (isset($messages[$body['error']])) {
            $body['message'] = $messages[$body['error']];
        }
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

    public static function fx_order_fail(string $context, \Throwable $e): void
{
    error_log('[order.php] ' . $context . ': ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    self::fx_order_respond(500, ['ok' => false, 'error' => 'server_error']);
}
}
