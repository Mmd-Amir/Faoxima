<?php

namespace Faoxima\Admin\Web\Support;

use PDO;
use RuntimeException;

final class BulkPrice
{
    public static function fx_bp_messages(): array
    {
        return [
            'unauthorized' => 'نشست شما منقضی شده است. دوباره وارد شوید.',
            'csrf_invalid' => 'توکن امنیتی نامعتبر است. صفحه را دوباره بارگذاری کنید.',
            'method_not_allowed' => 'روش درخواست مجاز نیست.',
            'invalid_action' => 'درخواست نامعتبر است.',
            'invalid_payload' => 'داده ارسالی نامعتبر است.',
            'invalid_operation' => 'نوع عملیات نامعتبر است.',
            'invalid_amount' => 'مبلغ باید یک عدد صحیح مثبت (به تومان) و در محدوده مجاز باشد.',
            'invalid_request_id' => 'شناسه درخواست نامعتبر است. دوباره تلاش کنید.',
            'empty_selection' => 'هیچ محصولی انتخاب نشده است.',
            'too_many' => 'تعداد محصولات انتخاب‌شده بیش از حد مجاز است.',
            'not_found' => 'برخی از محصولات انتخاب‌شده دیگر وجود ندارند. فهرست را بازخوانی کنید.',
            'invalid_price' => 'قیمت ذخیره‌شده برخی محصولات معتبر نیست. ابتدا قیمت آن‌ها را از صفحه ویرایش اصلاح کنید.',
            'negative_price' => 'قیمت جدید برخی محصولات منفی می‌شود. عملیات اعمال نشد.',
            'over_limit' => 'قیمت جدید برخی محصولات از حداکثر مقدار مجاز بیشتر می‌شود.',
            'stale' => 'قیمت برخی محصولات پس از پیش‌نمایش تغییر کرده است. پیش‌نمایش به‌روزرسانی شد؛ دوباره بررسی و ثبت کنید.',
            'request_conflict' => 'این شناسه درخواست پیش‌تر با اطلاعات دیگری استفاده شده است. دوباره تلاش کنید.',
            'engine_unsupported' => 'موتور جدول محصولات از تراکنش پشتیبانی نمی‌کند و تغییر گروهی قیمت امن نیست.',
            'server_error' => 'خطای داخلی سرور. جزئیات در لاگ سرور ثبت شد.',
        ];
    }

    public static function fx_bp_normalize_digits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    public static function fx_bp_parse_amount($raw): ?int
    {
        if (!is_string($raw) && !is_int($raw)) {
            return null;
        }
        $s = self::fx_bp_normalize_digits(trim((string)$raw));
        $s = str_replace([',', '٬', ' '], '', $s);
        if ($s === '' || !preg_match('/^\d{1,15}$/', $s)) {
            return null;
        }
        $n = (int)ltrim($s, '0');
        if ($n < 1 || $n > FX_BP_MAX_AMOUNT) {
            return null;
        }
        return $n;
    }

    public static function fx_bp_parse_price($raw): ?int
    {
        $s = trim((string)$raw);
        if ($s === '' || !preg_match('/^\d{1,12}$/', $s)) {
            return null;
        }
        return (int)$s;
    }

    public static function fx_bp_split_csv($raw): array
    {
        $items = [];
        foreach (explode(',', (string)$raw) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $items[$part] = true;
            }
        }
        return array_keys($items);
    }

    public static function fx_bp_hidden_panels($raw): array
    {
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $v) {
            if (is_scalar($v) && trim((string)$v) !== '') {
                $out[trim((string)$v)] = true;
            }
        }
        return array_keys($out);
    }

    public static function fx_bp_row_view(array $row): array
    {
        $price = self::fx_bp_parse_price($row['price_product'] ?? '');
        return [
            'id' => (int)$row['id'],
            'name' => (string)($row['name_product'] ?? ''),
            'price' => $price,
            'price_raw' => $price === null ? (string)($row['price_product'] ?? '') : null,
            'panels' => self::fx_bp_split_csv($row['Location'] ?? ''),
            'categories' => self::fx_bp_split_csv($row['category'] ?? ''),
        ];
    }

    public static function fx_bp_options(PDO $pdo): array
    {
        $panels = $pdo->query("SELECT name_panel FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> '' ORDER BY (position = 0) ASC, position ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $cats = $pdo->query("SELECT remark FROM category WHERE remark IS NOT NULL AND remark <> '' ORDER BY (position = 0) ASC, position ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
        return [
            'panels' => array_values(array_map('strval', $panels)),
            'categories' => array_values(array_map('strval', $cats)),
        ];
    }

    public static function fx_bp_match(PDO $pdo, array $filter): array
    {
        $rows = $pdo->query("SELECT id, name_product, price_product, Location, category, hide_panel FROM product ORDER BY (position = 0) ASC, position ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

        $mode = (string)($filter['cat_mode'] ?? 'all');
        $cat = trim((string)($filter['category'] ?? ''));
        $panel = trim((string)($filter['panel'] ?? ''));
        $q = trim(self::fx_bp_normalize_digits((string)($filter['q'] ?? '')));

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $cats = self::fx_bp_split_csv($row['category'] ?? '');
            if ($mode === 'uncat' && $cats) {
                continue;
            }
            if ($mode === 'name' && ($cat === '' || !in_array($cat, $cats, true))) {
                continue;
            }
            if ($panel !== '') {
                $locs = self::fx_bp_split_csv($row['Location'] ?? '');
                if (!in_array($panel, $locs, true) && !in_array('/all', $locs, true)) {
                    continue;
                }
                if (in_array($panel, self::fx_bp_hidden_panels($row['hide_panel'] ?? ''), true)) {
                    continue;
                }
            }
            if ($q !== '') {
                $name = (string)($row['name_product'] ?? '');
                $hit = ((string)$id === $q) || mb_stripos($name, $q, 0, 'UTF-8') !== false || strpos((string)$id, $q) !== false;
                if (!$hit) {
                    continue;
                }
            }
            $seen[$id] = true;
            $out[] = self::fx_bp_row_view($row);
            if (count($out) >= FX_BP_MAX_MATCHES) {
                break;
            }
        }
        return $out;
    }

    public static function fx_bp_ensure_schema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_bulk_price_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(64) NOT NULL,
            admin_user VARCHAR(190) NOT NULL DEFAULT '',
            payload_hash CHAR(64) NOT NULL,
            operation VARCHAR(16) NOT NULL,
            amount BIGINT UNSIGNED NOT NULL,
            changes MEDIUMTEXT NOT NULL,
            created_at INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_request (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public static function fx_bp_table_transactional(PDO $pdo): bool
    {
        $stmt = $pdo->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' LIMIT 1");
        $stmt->execute();
        $engine = strtolower((string)$stmt->fetchColumn());
        return in_array($engine, ['innodb', 'xtradb', 'tokudb', 'rocksdb'], true);
    }

    public static function fx_bp_apply(PDO $pdo, string $admin, string $requestId, string $operation, int $amount, array $expected): array
    {
        $ids = array_keys($expected);
        sort($ids);
        $hash = hash('sha256', json_encode([$operation, $amount, $expected]));

        if (!self::fx_bp_table_transactional($pdo)) {
            return ['status' => 500, 'body' => ['ok' => false, 'error' => 'engine_unsupported']];
        }
        self::fx_bp_ensure_schema($pdo);

        try {
            $pdo->beginTransaction();

            try {
                $ins = $pdo->prepare("INSERT INTO product_bulk_price_log (request_id, admin_user, payload_hash, operation, amount, changes, created_at) VALUES (?, ?, ?, ?, ?, '', ?)");
                $ins->execute([$requestId, mb_substr($admin, 0, 190, 'UTF-8'), $hash, $operation, $amount, time()]);
            } catch (\PDOException $e) {
                if ((string)$e->getCode() !== '23000') {
                    throw $e;
                }
                $pdo->rollBack();
                $prev = $pdo->prepare("SELECT payload_hash, changes FROM product_bulk_price_log WHERE request_id = ? LIMIT 1");
                $prev->execute([$requestId]);
                $row = $prev->fetch(PDO::FETCH_ASSOC);
                if (!$row || !hash_equals((string)$row['payload_hash'], $hash)) {
                    return ['status' => 409, 'body' => ['ok' => false, 'error' => 'request_conflict']];
                }
                $items = json_decode((string)$row['changes'], true);
                return ['status' => 200, 'body' => ['ok' => true, 'replayed' => true, 'updated' => is_array($items) ? count($items) : 0, 'items' => is_array($items) ? $items : []]];
            }

            $in = implode(',', array_fill(0, count($ids), '?'));
            $sel = $pdo->prepare("SELECT id, name_product, price_product, Location, category FROM product WHERE id IN ($in) ORDER BY id ASC FOR UPDATE");
            $sel->execute($ids);
            $rows = [];
            foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[(int)$r['id']] = $r;
            }

            if (count($rows) !== count($ids)) {
                $pdo->rollBack();
                $missing = array_values(array_diff($ids, array_keys($rows)));
                return ['status' => 409, 'body' => ['ok' => false, 'error' => 'not_found', 'missing' => $missing]];
            }

            $invalid = [];
            $stale = [];
            $negative = [];
            $over = [];
            $changes = [];
            $sign = $operation === 'increase' ? 1 : -1;
            foreach ($ids as $id) {
                $r = $rows[$id];
                $current = self::fx_bp_parse_price($r['price_product'] ?? '');
                if ($current === null) {
                    $invalid[] = ['id' => $id, 'name' => (string)$r['name_product'], 'price_raw' => (string)$r['price_product']];
                    continue;
                }
                if ($current !== $expected[$id]) {
                    $stale[] = $id;
                    continue;
                }
                $new = $current + $sign * $amount;
                if ($new < 0) {
                    $negative[] = ['id' => $id, 'name' => (string)$r['name_product'], 'price' => $current, 'new_price' => $new];
                    continue;
                }
                if ($new > FX_BP_MAX_AMOUNT) {
                    $over[] = ['id' => $id, 'name' => (string)$r['name_product'], 'price' => $current];
                    continue;
                }
                $changes[] = ['id' => $id, 'name' => (string)$r['name_product'], 'old' => $current, 'new' => $new];
            }

            if ($invalid) {
                $pdo->rollBack();
                return ['status' => 422, 'body' => ['ok' => false, 'error' => 'invalid_price', 'products' => $invalid]];
            }
            if ($stale) {
                $pdo->rollBack();
                $fresh = [];
                foreach ($rows as $r) {
                    $fresh[] = self::fx_bp_row_view($r);
                }
                return ['status' => 409, 'body' => ['ok' => false, 'error' => 'stale', 'stale_ids' => $stale, 'items' => $fresh]];
            }
            if ($negative) {
                $pdo->rollBack();
                return ['status' => 422, 'body' => ['ok' => false, 'error' => 'negative_price', 'products' => $negative]];
            }
            if ($over) {
                $pdo->rollBack();
                return ['status' => 422, 'body' => ['ok' => false, 'error' => 'over_limit', 'products' => $over]];
            }

            $upd = $pdo->prepare("UPDATE product SET price_product = ? WHERE id = ?");
            foreach ($changes as $c) {
                $upd->execute([(string)$c['new'], $c['id']]);
                if ($upd->rowCount() !== 1) {
                    throw new RuntimeException('price update affected unexpected row count for product ' . $c['id']);
                }
            }

            $log = $pdo->prepare("UPDATE product_bulk_price_log SET changes = ? WHERE request_id = ?");
            $log->execute([json_encode($changes, JSON_UNESCAPED_UNICODE), $requestId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[bulk_price] apply: ' . get_class($e) . ': ' . $e->getMessage());
            return ['status' => 500, 'body' => ['ok' => false, 'error' => 'server_error']];
        }

        return ['status' => 200, 'body' => ['ok' => true, 'updated' => count($changes), 'items' => $changes]];
    }

    public static function fx_bp_modal_html(string $modalId): string
    {
        $e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
        $id = $e($modalId);
        return '<div id="' . $id . '" class="modal-overlay bp-overlay" data-bp-modal data-page-size="' . FX_BP_PAGE_SIZE . '" data-endpoint="bulk_price.php" data-csrf="' . $e(\Faoxima\Admin\Web\Support\Csrf::fx_csrf_token()) . '">'
            . '<div class="modal-box bp-modal" role="dialog" aria-modal="true" aria-labelledby="' . $id . '-title" tabindex="-1">'
            . '<div class="modal-head">'
            . '<span class="modal-head__title" id="' . $id . '-title" data-bp-title>قیمت گروهی محصولات</span>'
            . '<button type="button" class="modal-close" data-bp-close aria-label="بستن">&times;</button>'
            . '</div>'
            . '<div class="modal-body bp-body" data-bp-body></div>'
            . '<div class="modal-foot bp-foot" data-bp-foot hidden></div>'
            . '</div></div>';
    }
}
