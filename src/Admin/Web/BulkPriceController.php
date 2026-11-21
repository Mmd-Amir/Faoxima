<?php

namespace Faoxima\Admin\Web;

use Faoxima\Admin\Security\CsrfToken;
use PDO;

final class BulkPriceController
{
    public static function dispatch(PDO $pdo, string $method, array $get, array $post, array $server, string $adminName, callable $csrfToken, callable $respond): void
    {
        if ($method !== 'GET' && $method !== 'POST') {
            $respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
        }

        $src = $method === 'POST' ? $post : $get;
        $action = isset($src['action']) && is_string($src['action']) ? $src['action'] : '';

        if ($method === 'GET') {
            if ($action === 'options') {
                $respond(200, ['ok' => true] + \Faoxima\Admin\Web\Support\BulkPrice::fx_bp_options($pdo));
            }
            if ($action === 'list' || $action === 'match') {
                $filter = [
                    'cat_mode' => in_array($src['cat_mode'] ?? '', ['all', 'uncat', 'name'], true) ? $src['cat_mode'] : 'all',
                    'category' => is_string($src['category'] ?? null) ? $src['category'] : '',
                    'panel' => is_string($src['panel'] ?? null) ? $src['panel'] : '',
                    'q' => is_string($src['q'] ?? null) ? mb_substr($src['q'], 0, 200, 'UTF-8') : '',
                ];
                $all = \Faoxima\Admin\Web\Support\BulkPrice::fx_bp_match($pdo, $filter);
                if ($action === 'match') {
                    $respond(200, ['ok' => true, 'total' => count($all), 'items' => $all]);
                }
                $per = FX_BP_PAGE_SIZE;
                $total = count($all);
                $pages = max(1, (int)ceil($total / $per));
                $page = isset($src['page']) && ctype_digit((string)$src['page']) ? max(1, (int)$src['page']) : 1;
                if ($page > $pages) {
                    $page = $pages;
                }
                $respond(200, [
                    'ok' => true,
                    'total' => $total,
                    'page' => $page,
                    'pages' => $pages,
                    'per' => $per,
                    'items' => array_slice($all, ($page - 1) * $per, $per),
                ]);
            }
            $respond(400, ['ok' => false, 'error' => 'invalid_action']);
        }

        $token = $csrfToken();
        $incoming = $post['_csrf'] ?? ($server['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!CsrfToken::matches($token, $incoming)) {
            $respond(403, ['ok' => false, 'error' => 'csrf_invalid']);
        }

        if ($action !== 'apply') {
            $respond(400, ['ok' => false, 'error' => 'invalid_action']);
        }

        $operation = $post['operation'] ?? '';
        if (!is_string($operation) || !in_array($operation, ['increase', 'decrease'], true)) {
            $respond(422, ['ok' => false, 'error' => 'invalid_operation']);
        }

        $amount = \Faoxima\Admin\Web\Support\BulkPrice::fx_bp_parse_amount($post['amount'] ?? null);
        if ($amount === null) {
            $respond(422, ['ok' => false, 'error' => 'invalid_amount']);
        }

        $requestId = $post['request_id'] ?? '';
        if (!is_string($requestId) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $requestId)) {
            $respond(422, ['ok' => false, 'error' => 'invalid_request_id']);
        }

        $rawItems = $post['items'] ?? '';
        $decoded = is_string($rawItems) && $rawItems !== '' ? json_decode($rawItems, true) : null;
        if (!is_array($decoded) || !$decoded) {
            $respond(422, ['ok' => false, 'error' => $decoded === [] ? 'empty_selection' : 'invalid_payload']);
        }
        if (count($decoded) > FX_BP_MAX_SELECTION) {
            $respond(422, ['ok' => false, 'error' => 'too_many']);
        }

        $expected = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || !isset($item['id'], $item['price'])) {
                $respond(422, ['ok' => false, 'error' => 'invalid_payload']);
            }
            $idRaw = (string)$item['id'];
            $priceRaw = (string)$item['price'];
            if (!ctype_digit($idRaw) || (int)$idRaw <= 0 || !ctype_digit($priceRaw) || strlen($priceRaw) > 12) {
                $respond(422, ['ok' => false, 'error' => 'invalid_payload']);
            }
            $expected[(int)$idRaw] = (int)$priceRaw;
        }

        $result = \Faoxima\Admin\Web\Support\BulkPrice::fx_bp_apply($pdo, $adminName, $requestId, $operation, $amount, $expected);
        $respond($result['status'], $result['body']);
    }
}
