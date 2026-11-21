<?php

namespace Faoxima\Admin\Web\Support;

use PDO;

final class Ordering
{
    public static function fx_order_entities(): array
    {
        return [
            'panel' => [
                'table' => 'marzban_panel',
                'label' => 'name_panel',
                'columns' => 'id, name_panel, code_panel, type, status, url_panel',
                'where' => "name_panel IS NOT NULL AND name_panel <> ''",
                'title' => 'جابجایی پنل‌ها',
                'empty' => 'هیچ پنلی ثبت نشده است.',
                'hint' => 'پنل‌ها را با کشیدن و رها کردن یا دکمه‌های بالا/پایین جابجا کنید. ترتیب در ربات و وب‌اپ اعمال می‌شود.',
            ],
            'product' => [
                'table' => 'product',
                'label' => 'name_product',
                'columns' => 'id, name_product, code_product, price_product, Volume_constraint, Service_time',
                'where' => '1=1',
                'title' => 'جابجایی ردیف محصولات',
                'empty' => 'هیچ محصولی ثبت نشده است.',
                'hint' => 'محصولات را با کشیدن و رها کردن یا دکمه‌های بالا/پایین جابجا کنید. این ترتیب در ربات و وب‌اپ نمایش داده می‌شود.',
            ],
            'category' => [
                'table' => 'category',
                'label' => 'remark',
                'columns' => 'id, remark',
                'where' => '1=1',
                'title' => 'جابجایی دسته‌بندی‌ها',
                'empty' => 'هیچ دسته‌بندی‌ای ثبت نشده است.',
                'hint' => 'دسته‌بندی‌ها را با کشیدن و رها کردن یا دکمه‌های بالا/پایین جابجا کنید. ترتیب در ربات و وب‌اپ اعمال می‌شود.',
            ],
        ];
    }

    public static function fx_order_ensure_schema(PDO $pdo, string $entity): void
    {
        $defs = self::fx_order_entities();
        if (!isset($defs[$entity])) {
            return;
        }
        $table = $defs[$entity]['table'];
        if (\Faoxima\Admin\Web\Support\Schema::faoxima_schema_column_exists($pdo, $table, 'position')) {
            return;
        }
        \Faoxima\Admin\Web\Support\Schema::faoxima_schema_ensure_column($pdo, $table, 'position', 'INT(11) NOT NULL DEFAULT 0');
        try {
            $pdo->exec('SET @fx_pos := 0');
            $pdo->exec("UPDATE `{$table}` SET position = (@fx_pos := @fx_pos + 1) ORDER BY id ASC");
        } catch (\Throwable $e) {
            error_log('[order] init ' . $table . ': ' . $e->getMessage());
        }
    }

    public static function fx_order_fetch_rows(PDO $pdo, string $entity, bool $lock = false): array
    {
        $defs = self::fx_order_entities();
        $def = $defs[$entity];
        $sql = "SELECT {$def['columns']} FROM `{$def['table']}` WHERE {$def['where']} ORDER BY (position = 0) ASC, position ASC, id ASC";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fx_order_version(array $rows): string
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row['id'];
        }
        return sha1(implode(',', $ids));
    }

    public static function fx_order_format_item(string $entity, array $row): array
    {
        $id = (int)$row['id'];
        if ($entity === 'panel') {
            $host = (string)parse_url((string)($row['url_panel'] ?? ''), PHP_URL_HOST);
            return [
                'id' => $id,
                'name' => (string)$row['name_panel'],
                'badges' => array_values(array_filter([
                    (string)($row['type'] ?? ''),
                    (string)($row['code_panel'] ?? '') !== '' ? 'کد: ' . $row['code_panel'] : '',
                    $host,
                ], function ($v) { return $v !== ''; })),
                'inactive' => (string)($row['status'] ?? '') !== 'active',
            ];
        }
        if ($entity === 'product') {
            $badges = [];
            if ((string)($row['code_product'] ?? '') !== '') {
                $badges[] = 'کد: ' . $row['code_product'];
            }
            if ((string)($row['price_product'] ?? '') !== '') {
                $badges[] = number_format((float)$row['price_product']) . ' تومان';
            }
            if ((string)($row['Volume_constraint'] ?? '') !== '') {
                $badges[] = $row['Volume_constraint'] . ' گیگ';
            }
            if ((string)($row['Service_time'] ?? '') !== '') {
                $badges[] = $row['Service_time'] . ' روز';
            }
            return ['id' => $id, 'name' => (string)$row['name_product'], 'badges' => $badges, 'inactive' => false];
        }
        return ['id' => $id, 'name' => (string)$row['remark'], 'badges' => [], 'inactive' => false];
    }

    public static function fx_order_payload(PDO $pdo, string $entity): array
    {
        $rows = self::fx_order_fetch_rows($pdo, $entity);
        $items = [];
        foreach ($rows as $row) {
            $items[] = self::fx_order_format_item($entity, $row);
        }
        return ['ok' => true, 'items' => $items, 'version' => self::fx_order_version($rows)];
    }

    public static function fx_order_save(PDO $pdo, string $entity, array $ids, string $version): array
    {
        $defs = self::fx_order_entities();
        $def = $defs[$entity];

        if (!$ids) {
            return ['status' => 422, 'body' => ['ok' => false, 'error' => 'empty_list']];
        }
        foreach ($ids as $id) {
            if (!is_int($id) || $id <= 0) {
                return ['status' => 422, 'body' => ['ok' => false, 'error' => 'invalid_id']];
            }
        }
        if (count($ids) !== count(array_unique($ids))) {
            return ['status' => 422, 'body' => ['ok' => false, 'error' => 'duplicate_id']];
        }

        try {
            $pdo->beginTransaction();
            $rows = self::fx_order_fetch_rows($pdo, $entity, true);
            $current = [];
            foreach ($rows as $row) {
                $current[] = (int)$row['id'];
            }
            $sortedCurrent = $current;
            $sortedSubmitted = $ids;
            sort($sortedCurrent);
            sort($sortedSubmitted);
            if ($sortedCurrent !== $sortedSubmitted || !hash_equals(self::fx_order_version($rows), $version)) {
                $pdo->rollBack();
                $fresh = self::fx_order_payload($pdo, $entity);
                $fresh['ok'] = false;
                $fresh['error'] = 'stale';
                return ['status' => 409, 'body' => $fresh];
            }
            $stmt = $pdo->prepare("UPDATE `{$def['table']}` SET position = ? WHERE id = ?");
            $pos = 1;
            foreach ($ids as $id) {
                $stmt->execute([$pos, $id]);
                $pos++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[order] save ' . $entity . ': ' . $e->getMessage());
            return ['status' => 500, 'body' => ['ok' => false, 'error' => 'server_error']];
        }

        return ['status' => 200, 'body' => self::fx_order_payload($pdo, $entity)];
    }

    public static function fx_order_modal_html(string $entity, string $modalId): string
    {
        $defs = self::fx_order_entities();
        $def = $defs[$entity];
        $e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
        return '<div id="' . $e($modalId) . '" class="modal-overlay ord-overlay" data-order-modal data-entity="' . $e($entity) . '" data-endpoint="order.php" data-csrf="' . $e(\Faoxima\Admin\Web\Support\Csrf::fx_csrf_token()) . '" data-empty="' . $e($def['empty']) . '">'
            . '<div class="modal-box ord-modal" role="dialog" aria-modal="true" aria-labelledby="' . $e($modalId) . '-title">'
            . '<div class="modal-head">'
            . '<span class="modal-head__title" id="' . $e($modalId) . '-title">' . $e($def['title']) . '</span>'
            . '<button type="button" class="modal-close" data-order-close aria-label="بستن">&times;</button>'
            . '</div>'
            . '<div class="modal-body ord-body">'
            . '<p class="ord-hint">' . $e($def['hint']) . '</p>'
            . '<div class="ord-state" data-order-state hidden></div>'
            . '<ol class="ord-list" data-order-list aria-label="' . $e($def['title']) . '"></ol>'
            . '<div class="mdt-foot ord-pager" data-order-pager hidden></div>'
            . '</div>'
            . '<div class="modal-foot ord-foot">'
            . '<span class="ord-status" data-order-status role="status" aria-live="polite"></span>'
            . '<button type="button" class="btn btn-soft-purple btn-sm" data-order-close>بستن</button>'
            . '</div>'
            . '</div></div>';
    }
}
