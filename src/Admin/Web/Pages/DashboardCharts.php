<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $adminWebPage, $pdo.
 * Inherited or exported by reference: $cat, $cfg, $items, $json_data, $json_labels, $payload, $r, $resultcontsell, $resultcount, $resultcountday, $st, $stmt, $total_income.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DashboardCharts
{
    public static function handle(
        $adminWebPage,
        &$cat,
        &$cfg,
        &$items,
        &$json_data,
        &$json_labels,
        &$payload,
        $pdo,
        &$r,
        &$resultcontsell,
        &$resultcount,
        &$resultcountday,
        &$st,
        &$stmt,
        &$total_income
    ) {
        if (($adminWebPage ?? null) !== 'index.php') {
            return;
        }

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'chart') {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            $range = $_GET['range'] ?? '7d';
            if (!in_array($range, ['24h', '7d', '3m', 'custom'], true)) $range = '7d';
            $days  = isset($_GET['days']) ? (int)$_GET['days'] : 7;
            try {
                $payload = \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_chart_data($pdo, $range, $days);
                echo json_encode(['ok' => true] + $payload, JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            $q = trim((string)($_GET['q'] ?? ''));
            $qLen = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
            if ($qLen < 2) { echo json_encode(['ok' => true, 'groups' => []], JSON_UNESCAPED_UNICODE); exit; }
            $like = '%' . $q . '%';
            $groups = [];
            try {
                $st = $pdo->prepare("SELECT id, username, namecustom, number FROM user WHERE id LIKE ? OR username LIKE ? OR namecustom LIKE ? OR number LIKE ? OR number_username LIKE ? ORDER BY (id + 0) DESC LIMIT 8");
                $st->execute([$like, $like, $like, $like, $like]);
                $items = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $uname = (string)($r['username'] ?? '');
                    if ($uname === '' || $uname === 'none') $uname = (string)($r['namecustom'] ?? '');
                    $title = ($uname !== '' && $uname !== 'none') ? $uname : (string)$r['id'];
                    $sub = 'آیدی: ' . (string)$r['id'];
                    $num = (string)($r['number'] ?? '');
                    if ($num !== '' && $num !== 'none') $sub .= ' — ' . $num;
                    $items[] = ['title' => $title, 'sub' => $sub, 'url' => 'user.php?id=' . urlencode((string)$r['id'])];
                }
                if ($items) $groups[] = ['type' => 'users', 'label' => 'کاربران', 'icon' => \Faoxima\Admin\Web\Support\Icons::icon('users', 'svg-icon'), 'items' => $items];
            } catch (\Throwable $e) { error_log('[search] users: ' . $e->getMessage()); }
            try {
                $st = $pdo->prepare("SELECT id_invoice, id_user, username, name_product, price_product FROM invoice WHERE (id_invoice LIKE ? OR id_user LIKE ? OR username LIKE ? OR name_product LIKE ?) AND name_product != 'سرویس تست' ORDER BY (time_sell + 0) DESC LIMIT 6");
                $st->execute([$like, $like, $like, $like]);
                $items = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $title = (string)$r['name_product'];
                    $cfg = (string)($r['username'] ?? '');
                    if ($cfg !== '') $title .= ' — ' . $cfg;
                    $sub = 'سفارش: ' . (string)$r['id_invoice'] . ' — ' . number_format((float)$r['price_product']) . ' تومان';
                    $items[] = ['title' => $title, 'sub' => $sub, 'url' => 'invoice.php?q=' . urlencode($q)];
                }
                if ($items) $groups[] = ['type' => 'invoices', 'label' => 'سفارشات', 'icon' => \Faoxima\Admin\Web\Support\Icons::icon('receipt', 'svg-icon'), 'items' => $items];
            } catch (\Throwable $e) { error_log('[search] invoices: ' . $e->getMessage()); }
            try {
                $st = $pdo->prepare("SELECT id, name_product, price_product, category FROM product WHERE id LIKE ? OR name_product LIKE ? OR category LIKE ? ORDER BY id ASC LIMIT 6");
                $st->execute([$like, $like, $like]);
                $items = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $title = (string)$r['name_product'];
                    $sub = number_format((float)$r['price_product']) . ' تومان';
                    $cat = (string)($r['category'] ?? '');
                    if ($cat !== '') $sub .= ' — ' . $cat;
                    $items[] = ['title' => $title, 'sub' => $sub, 'url' => 'productedit.php?id=' . urlencode((string)$r['id'])];
                }
                if ($items) $groups[] = ['type' => 'products', 'label' => 'محصولات', 'icon' => \Faoxima\Admin\Web\Support\Icons::icon('package', 'svg-icon'), 'items' => $items];
            } catch (\Throwable $e) { error_log('[search] products: ' . $e->getMessage()); }
            try {
                $st = $pdo->prepare("SELECT id_user, id_order, price, Payment_Method FROM Payment_report WHERE id_order LIKE ? OR id_user LIKE ? ORDER BY time DESC LIMIT 6");
                $st->execute([$like, $like]);
                $items = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $title = 'پیگیری: ' . (string)$r['id_order'];
                    $sub = 'کاربر ' . (string)$r['id_user'] . ' — ' . number_format((float)$r['price']) . ' تومان';
                    $items[] = ['title' => $title, 'sub' => $sub, 'url' => 'payment.php?q=' . urlencode($q)];
                }
                if ($items) $groups[] = ['type' => 'payments', 'label' => 'تراکنش‌ها', 'icon' => \Faoxima\Admin\Web\Support\Icons::icon('money-bill', 'svg-icon'), 'items' => $items];
            } catch (\Throwable $e) { error_log('[search] payments: ' . $e->getMessage()); }
            try {
                $st = $pdo->prepare("SELECT id, id_user, username, type, price FROM service_other WHERE id_user LIKE ? OR username LIKE ? ORDER BY id DESC LIMIT 6");
                $st->execute([$like, $like]);
                $items = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $title = (string)($r['username'] ?? '');
                    if ($title === '') $title = 'سرویس #' . (string)$r['id'];
                    $sub = 'کاربر ' . (string)$r['id_user'];
                    $items[] = ['title' => $title, 'sub' => $sub, 'url' => 'service.php?q=' . urlencode($q)];
                }
                if ($items) $groups[] = ['type' => 'services', 'label' => 'سرویس‌ها', 'icon' => \Faoxima\Admin\Web\Support\Icons::icon('server', 'svg-icon'), 'items' => $items];
            } catch (\Throwable $e) { error_log('[search] services: ' . $e->getMessage()); }
            try {
                $st = $pdo->prepare("SELECT id, id_user, id_order, id_invoice, price FROM Payment_report WHERE id_user LIKE ? OR id_order LIKE ? OR id_invoice LIKE ? ORDER BY id DESC LIMIT 6");
                $st->execute([$like, $like, $like]);
                $items = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $title = 'درخواست #' . (string)$r['id'] . ' — کاربر ' . (string)$r['id_user'];
                    $sub = number_format((float)$r['price']) . ' تومان';
                    $items[] = ['title' => $title, 'sub' => $sub, 'url' => 'cancelService.php?q=' . urlencode($q)];
                }
                if ($items) $groups[] = ['type' => 'requests', 'label' => 'درخواست‌ها', 'icon' => \Faoxima\Admin\Web\Support\Icons::icon('list', 'svg-icon'), 'items' => $items];
            } catch (\Throwable $e) { error_log('[search] requests: ' . $e->getMessage()); }
            try {
                $st = $pdo->prepare("SELECT s.Tracking, s.iduser, s.name_departman, s.status, s.text FROM support_message s INNER JOIN (SELECT Tracking, MIN(id) mid FROM support_message WHERE Tracking LIKE ? OR iduser LIKE ? OR text LIKE ? GROUP BY Tracking ORDER BY MAX(id) DESC LIMIT 6) f ON s.id = f.mid");
                $st->execute([$like, $like, $like]);
                $items = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $subj = '';
                    if (preg_match('/\[\[subj:([^\]]*)\]\]/u', (string)$r['text'], $m)) $subj = trim($m[1]);
                    $title = $subj !== '' ? $subj : ('تیکت ' . (string)$r['Tracking']);
                    $sub = 'کد: ' . (string)$r['Tracking'] . ' — کاربر ' . (string)$r['iduser'];
                    $items[] = ['title' => $title, 'sub' => $sub, 'url' => 'tickets.php?t=' . urlencode((string)$r['Tracking'])];
                }
                if ($items) $groups[] = ['type' => 'tickets', 'label' => 'تیکت‌ها', 'icon' => \Faoxima\Admin\Web\Support\Icons::icon('message', 'svg-icon'), 'items' => $items];
            } catch (\Throwable $e) { error_log('[search] tickets: ' . $e->getMessage()); }
            try {
                $st = $pdo->prepare("SELECT id, namerecord, username, codeproduct, status FROM manualsell WHERE namerecord LIKE ? OR username LIKE ? OR codeproduct LIKE ? ORDER BY id DESC LIMIT 6");
                $st->execute([$like, $like, $like]);
                $items = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $title = (string)($r['namerecord'] ?? '');
                    if ($title === '') $title = 'کانفیگ #' . (string)$r['id'];
                    $sub = (string)($r['codeproduct'] ?? '');
                    $items[] = ['title' => $title, 'sub' => $sub, 'url' => 'manual_service.php?q=' . urlencode($q)];
                }
                if ($items) $groups[] = ['type' => 'manual_sales', 'label' => 'فروش دستی', 'icon' => \Faoxima\Admin\Web\Support\Icons::icon('cart-check', 'svg-icon'), 'items' => $items];
            } catch (\Throwable $e) { error_log('[search] manual_sales: ' . $e->getMessage()); }
            try {
                $st = $pdo->prepare("SELECT nm.id, nm.shelf_id, nm.assigned_user, nm.status, sh.name AS shelf_name FROM nm_config_stock nm LEFT JOIN nm_stock_shelves sh ON sh.id = nm.shelf_id WHERE nm.assigned_user LIKE ? OR nm.content LIKE ? OR nm.sub_link LIKE ? ORDER BY nm.id DESC LIMIT 6");
                $st->execute([$like, $like, $like]);
                $items = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $title = (string)($r['shelf_name'] ?? ('انبار #' . (string)$r['shelf_id']));
                    $assigned = (string)($r['assigned_user'] ?? '');
                    $sub = $assigned !== '' ? ('کاربر: ' . $assigned) : ('کانفیگ #' . (string)$r['id']);
                    $items[] = ['title' => $title, 'sub' => $sub, 'url' => 'stock.php?shelf=' . urlencode((string)$r['shelf_id']) . '&q=' . urlencode($q)];
                }
                if ($items) $groups[] = ['type' => 'national_net', 'label' => 'انبار شبکه ملی', 'icon' => \Faoxima\Admin\Web\Support\Icons::icon('package', 'svg-icon'), 'items' => $items];
            } catch (\Throwable $e) { error_log('[search] national_net: ' . $e->getMessage()); }
            echo json_encode(['ok' => true, 'groups' => $groups], JSON_UNESCAPED_UNICODE);
            exit;
        }


        $datefirstday = time() - 86400;


        @set_time_limit(20);


        $total_income = \Faoxima\Admin\Reporting\Statistics::rx_stats_total_sales_amount($pdo);


        $resultcount = \Faoxima\Admin\Reporting\Statistics::rx_stats_total_users($pdo);


        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register > :time_register AND register != 'none'");
        $stmt->bindValue(':time_register', $datefirstday, PDO::PARAM_INT);
        $stmt->execute();
        $resultcountday = (int)$stmt->fetchColumn();


        $resultcontsell = \Faoxima\Admin\Reporting\Statistics::rx_stats_total_sales_count($pdo);


        $initialChart = \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_chart_data($pdo, '7d');
        $json_labels  = json_encode($initialChart['labels'], JSON_UNESCAPED_UNICODE);
        $json_data    = json_encode($initialChart['data']);


        $__VALID = "(status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold')";

        return true;
    }
}
