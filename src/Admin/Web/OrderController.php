<?php

namespace Faoxima\Admin\Web;

use Faoxima\Admin\Security\CsrfToken;
use PDO;

final class OrderController
{
    public static function dispatch(PDO $pdo, string $method, array $get, array $post, array $server, callable $csrfToken, callable $respond): void
    {
        if ($method !== 'GET' && $method !== 'POST') {
            $respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
        }

        $entity = (string)($method === 'POST' ? ($post['entity'] ?? '') : ($get['entity'] ?? ''));
        if (!isset(\Faoxima\Admin\Web\Support\Ordering::fx_order_entities()[$entity])) {
            $respond(400, ['ok' => false, 'error' => 'invalid_entity']);
        }

        \Faoxima\Admin\Web\Support\Ordering::fx_order_ensure_schema($pdo, $entity);

        if ($method === 'GET') {
            $respond(200, \Faoxima\Admin\Web\Support\Ordering::fx_order_payload($pdo, $entity));
        }

        $token = $csrfToken();
        $incoming = $post['_csrf'] ?? ($server['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!CsrfToken::matches($token, $incoming)) {
            $respond(403, ['ok' => false, 'error' => 'csrf_invalid']);
        }

        $rawIds = $post['ids'] ?? '';
        $version = $post['version'] ?? '';
        if (!is_string($rawIds) || !is_string($version) || $rawIds === '' || $version === '') {
            $respond(422, ['ok' => false, 'error' => 'invalid_payload']);
        }
        $ids = [];
        foreach (explode(',', $rawIds) as $part) {
            if (!ctype_digit($part)) {
                $respond(422, ['ok' => false, 'error' => 'invalid_id']);
            }
            $ids[] = (int)$part;
        }

        $result = \Faoxima\Admin\Web\Support\Ordering::fx_order_save($pdo, $entity, $ids, $version);
        $respond($result['status'], $result['body']);
    }
}
