<?php


declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
date_default_timezone_set('Asia/Tehran');
@putenv('TZ=Asia/Tehran');


$root = realpath(__DIR__ . '/..');
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(1);
}

require_once $root . '/config.php';
require_once $root . '/function.php';


(function () {
    if (php_sapi_name() === 'cli') return;
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $localIps = ['127.0.0.1', '::1', $_SERVER['SERVER_ADDR'] ?? ''];
    if ($remote !== '' && in_array($remote, $localIps, true)) return;

    $providedToken = (string) ($_GET['token'] ?? $_REQUEST['token'] ?? '');
    if ($providedToken !== '' && function_exists('hash_equals')) {
        if (isset($GLOBALS['admin_panel_token']) && is_string($GLOBALS['admin_panel_token'])
            && trim($GLOBALS['admin_panel_token']) !== ''
            && hash_equals(trim($GLOBALS['admin_panel_token']), $providedToken)) {
            return;
        }
        try {
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query("SELECT password FROM admin");
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $stored = (string) ($row['password'] ?? '');
                        if ($stored !== '' && hash_equals($stored, $providedToken)) {
                            return;
                        }
                    }
                }
            }
        } catch (Throwable $e) {}
    }

    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><title>Not Found</title></head><body><h1>Not Found</h1></body></html>';
    exit;
})();


global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('getDatabaseConnection')) {
        $pdo = getDatabaseConnection();
    }
}
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cli = (php_sapi_name() === 'cli');
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$out = static function (string $msg) use ($cli): void {
    echo ($cli ? '' : htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')) . $msg . "\n";
    @ob_flush(); @flush();
};


$addIndex = static function (PDO $pdo, string $table, string $indexName, string $columns) use (&$out): void {

    try {
        $check = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $check->execute([$table]);
        if (!$check->fetchColumn()) {
            $out("[skip] table `{$table}` does not exist yet — skipping index `{$indexName}`");
            return;
        }
    } catch (Throwable $e) {
        $out("[warn] cannot check table `{$table}`: " . $e->getMessage());
        return;
    }

    try {
        $check = $pdo->prepare("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1");
        $check->execute([$table, $indexName]);
        if ($check->fetchColumn()) {
            $out("[ok ] `{$table}`.`{$indexName}` already present");
            return;
        }
    } catch (Throwable $e) {
        $out("[warn] cannot check index `{$table}`.`{$indexName}`: " . $e->getMessage());
    }

    $colsClean = preg_replace('/\s+/', '', $columns);
    $colNames = array_filter(array_map('trim', explode(',', preg_replace('/\([0-9]+\)/', '', $colsClean))));
    foreach ($colNames as $col) {
        $col = trim($col, '`');
        try {
            $check = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
            $check->execute([$table, $col]);
            if (!$check->fetchColumn()) {
                $out("[skip] column `{$table}`.`{$col}` missing — skipping index `{$indexName}`");
                return;
            }
        } catch (Throwable $e) {
            $out("[warn] cannot check column `{$table}`.`{$col}`: " . $e->getMessage());
            return;
        }
    }

    try {
        $sql = "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columns})";
        $pdo->exec($sql);
        $out("[+++] created `{$table}`.`{$indexName}` on ({$columns})");
    } catch (Throwable $e) {

        if (stripos($e->getMessage(), 'Duplicate') !== false || stripos($e->getMessage(), '1061') !== false) {
            $out("[ok ] `{$table}`.`{$indexName}` already present (race)");
        } else {
            $out("[FAIL] `{$table}`.`{$indexName}`: " . $e->getMessage());
        }
    }
};

$out("=== Index migration starting at " . date('Y-m-d H:i:s') . " (Asia/Tehran) ===");
$started = microtime(true);


$addIndex($pdo, 'user', 'idx_user_status',                'User_Status(20)');
$addIndex($pdo, 'user', 'idx_user_agent',                 'agent(20)');
$addIndex($pdo, 'user', 'idx_user_status_agent',          'User_Status(20), agent(20)');
$addIndex($pdo, 'user', 'idx_user_last_message_time',     'last_message_time');
$addIndex($pdo, 'user', 'idx_user_register',              'register');
$addIndex($pdo, 'user', 'idx_user_affiliates',            'affiliates(20)');


$addIndex($pdo, 'invoice', 'idx_invoice_id_user',         'id_user');
$addIndex($pdo, 'invoice', 'idx_invoice_user_location',   'id_user, Service_location(50)');
$addIndex($pdo, 'invoice', 'idx_invoice_at_updated',      'at_updated');
$addIndex($pdo, 'invoice', 'idx_invoice_status_buy',      'Status_Buy(20)');
$addIndex($pdo, 'invoice', 'idx_invoice_id_panel',        'id_user_panel(50)');


$addIndex($pdo, 'reagent_report', 'idx_reagent',          'reagent');


$addIndex($pdo, 'Payment_report', 'idx_payment_at_updated','at_updated');
$addIndex($pdo, 'Payment_report', 'idx_payment_id_user',  'id_user');


$addIndex($pdo, 'Discount',       'idx_discount_code',    'codeDiscount(50)');


$addIndex($pdo, 'nm_config_stock', 'idx_stock_status',    'status, source_codepanel');

$elapsed = round(microtime(true) - $started, 2);
$out("=== Done in {$elapsed}s. Re-run any time — this script is idempotent. ===");

