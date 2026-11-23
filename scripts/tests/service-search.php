<?php

declare(strict_types=1);

// Start the Rebecca fixture in a separate terminal:
// php -S 127.0.0.1:43497 scripts/tests/service-search.php
// Then run against a disposable MariaDB/MySQL database with CREATE TEMPORARY TABLE privileges:
// FAOXIMA_TEST_DSN='mysql:host=127.0.0.1;dbname=faoxima_test;charset=utf8mb4' php scripts/tests/service-search.php
// FAOXIMA_TEST_DB_USER and FAOXIMA_TEST_DB_PASSWORD override the connection credentials.

if (PHP_SAPI === 'cli-server') {
    header('Content-Type: application/json');
    if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer fixture-api-key') {
        http_response_code(401);
        echo '{}';
        return;
    }
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $panel = explode('/', $path)[1];
    $base = 'https://' . $panel . '.subscriptions.test' . ($panel === 'west' ? '/west' : '') . '/sub';
    $key = $panel === 'east' ? 'aaaabbbbccccddddeeeeffff00001111' : 'aaaabbbbccccddddeeeeffff00002222';
    $user = [
        'id' => 91, 'username' => 'same-name',
        'subscription_url' => $base . '/same-name/' . $key,
        'subscription_urls' => [
            'username-key' => $base . '/same-name/' . $key,
            'key' => $base . '/' . $key,
            'subadress' => $base . '/legacy-address-' . $panel,
            'token' => $base . '/new-rebecca-token-signed',
        ],
        'links' => ['vless://' . ($panel === 'east' ? '11111111-2222-4333-8444-555555555555' : '99999999-8888-4777-8666-555555555555') . '@nodes.test:443#Label'],
    ];
    if ($path === "/$panel/sub/old-rebecca-token-signed/info") {
        echo json_encode(['user' => ['username' => $user['username']]]);
        return;
    }
    if ($path === "/$panel/api/user/same-name") {
        unset($user['subscription_urls']);
        echo json_encode($user + ['status' => 'active']);
        return;
    }
    if ($path !== "/$panel/api/users") {
        http_response_code(404);
        echo '{}';
        return;
    }
    $query = $_GET['search'] ?? null;
    if ($query !== null && $query === '91' && $panel === 'east' && (int)($_GET['offset'] ?? 0) === 0) {
        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = ['id' => $i + 100, 'username' => 'wrong_' . $i];
        }
        echo json_encode(['total' => 51, 'users' => $rows]);
        return;
    }
    if ($query !== null && !in_array(strtolower($query), [
        '91', 'old-rebecca-token-signed', 'wrong-token-in-note', 'legacy-address-' . $panel, $key,
        '11111111-2222-4333-8444-555555555555', '99999999-8888-4777-8666-555555555555',
    ], true)) {
        echo '{"total":0,"users":[]}';
        return;
    }
    if ($query === null && !str_contains($_SERVER['QUERY_STRING'], 'username=same-name')) {
        echo '{"total":0,"users":[]}';
        return;
    }
    echo json_encode(['total' => $query === '91' && $panel === 'east' ? 51 : 1, 'users' => [$user]]);
    return;
}
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$testDsn = getenv('FAOXIMA_TEST_DSN');
if ($testDsn === false || $testDsn === '') {
    fwrite(STDERR, "Set FAOXIMA_TEST_DSN to a disposable MariaDB or MySQL database.\n");
    exit(1);
}
require_once dirname(__DIR__, 2) . '/panels.php';
restore_exception_handler();

$pdo = new PDO($testDsn, getenv('FAOXIMA_TEST_DB_USER') ?: 'root', getenv('FAOXIMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$GLOBALS['pdo'] = $pdo;
$GLOBALS['domainhosts'] = 'shop.example.test';
$GLOBALS['setting'] = ['panel_curl_timeout' => 5];
$pdo->exec("CREATE TEMPORARY TABLE setting (panel_curl_timeout int, redis_enabled varchar(10))");
$pdo->exec("INSERT INTO setting VALUES (5, 'off')");
$pdo->exec("CREATE TEMPORARY TABLE marzban_panel (id int, name_panel varchar(100), type varchar(30), url_panel varchar(250), api_key varchar(50))");
$pdo->exec("INSERT INTO marzban_panel VALUES
  (1, 'east', 'rebecca', 'http://127.0.0.1:43497/east', 'fixture-api-key'),
  (2, 'west', 'rebecca', 'http://127.0.0.1:43497/west', 'fixture-api-key'),
  (3, 'manual', 'Manualsale', '', '')");
$pdo->exec("ALTER TABLE marzban_panel ADD subvip varchar(10) DEFAULT 'onsubvip'");
$pdo->exec("CREATE TEMPORARY TABLE invoice (id_invoice varchar(40) PRIMARY KEY, id_user varchar(30), username varchar(100), Service_location varchar(100), Status varchar(20), note varchar(255), Volume varchar(20), uuid text, user_info text)");
$rows = [
    ['e123', '100', 'same-name', 'east', 'active', 'match 91', '5', null, '{}'],
    ['w123', '200', 'same-name', 'west', 'disabled', null, '5', null, 'malformed JSON'],
    ['z123', '100', 'deleted', 'east', 'removebyuser', null, '5', null, null],
    ['m123', '100', 'literal_%', 'manual', 'active', 'match 91', '91', null, 'https://manual.test/sub/token?a=1&b=2'],
    ['x123', '200', 'literalXX', 'manual', 'active', null, '5', null, null],
    ['l123', '100', 'cache_user', 'manual', 'active', null, '7', '{"vless":{"id":"abcd"}}', '{"uuid":"76543210-1111-4222-8333-123456789abc","subscription_url":"https://cache.test/sub/old"}'],
];
$insert = $pdo->prepare('INSERT INTO invoice VALUES (?,?,?,?,?,?,?,?,?)');
foreach ($rows as $row) {
    $insert->execute($row);
}

$tested = 0;
function same($expected, $actual, string $label): void
{
    global $tested;
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': ' . json_encode(compact('expected', 'actual')));
    }
    $tested++;
}

function search(PDO $pdo, string $query, ?string $owner, bool $includeTextMatches = false): array
{
    $scope = $owner === null ? '1=1' : 'id_user = :owner AND ' . ServiceSearch::VISIBLE_STATUS;
    $params = $owner === null ? [] : [':owner' => $owner];
    [$where, $extra] = ServiceSearch::condition($pdo, $query, $scope, $params, ['username', 'note', 'Volume'], $includeTextMatches);
    $stmt = $pdo->prepare("SELECT id_invoice FROM invoice WHERE $scope AND $where ORDER BY id_invoice");
    $stmt->execute($params + $extra);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
same(['e123'], search($pdo, '۹۱', '100'), 'Persian numeric ID / buyer');
same(['w123'], search($pdo, '#٩١', '200'), 'Arabic digits / second owner');
same(['e123', 'w123'], search($pdo, '91', null), 'same numeric ID on two panels / administrator');
same(['e123', 'm123', 'w123'], search($pdo, '91', null, true), 'web orders retain existing numeric text matches');
same(['m123'], search($pdo, 'literal_%', '100'), 'literal SQL LIKE metacharacters');
same(['e123'], search($pdo, 'same-name', '100'), 'partial username under owner');
same([], search($pdo, 'match', '300'), 'no search across other owners');
same([], search($pdo, 'deleted', '100'), 'deleted record hidden');
same(['z123'], search($pdo, 'deleted', null), 'order archive for administrator');
same(['e123'], search($pdo, '11111111-2222-4333-8444-555555555555', '100'), 'VLESS UUID');
same([], search($pdo, '11111111-2222-4333-8444-555555555555', '200'), 'UUID never transfers to same username in another panel');
same(['e123'], search($pdo, 'AAAABBBBCCCCDDDDEEEEFFFF00001111', '100'), 'compact uppercase credential UUID');
same(['e123'], search($pdo, 'https://east.subscriptions.test/sub/same-name/aaaabbbbccccddddeeeeffff00001111#label', '100'), 'Rebecca username-key link');
same(['e123'], search($pdo, 'https://east.subscriptions.test/sub/legacy-address-east', '100'), 'legacy Rebecca subscription');
same(['e123'], search($pdo, 'https://east.subscriptions.test/sub/old-rebecca-token-signed', '100'), 'signed subscription token');
same(['w123'], search($pdo, 'https://west.subscriptions.test/west/sub/old-rebecca-token-signed', '200'), 'signed subscription behind a panel path prefix');
same([], search($pdo, 'https://east.subscriptions.test/sub/wrong-token-in-note', '100'), 'fuzzy candidate does not validate random subscription');
same([], search($pdo, 'https://foreign.test/sub/old-rebecca-token-signed', '100'), 'foreign subscription host');
same(['e123'], search($pdo, 'https://shop.example.test/sub/e123?format=clash', '100'), 'Faoxima public subscription');
same([], search($pdo, 'https://shop.example.test/sub/w123', '100'), 'public subscription with another owner');
same(['m123'], search($pdo, 'https://manual.test/sub/token?a=1&b=2', '100'), 'stored plain URL with query string');
same(['l123'], search($pdo, 'https://cache.test/sub/old', '100'), 'stored JSON with malformed legacy row in scope');
same(['l123'], search($pdo, '76543210-1111-4222-8333-123456789abc', '100'), 'stored UUID');
same([], search($pdo, 'https://shop.example.test/sub/%65%31%32%33', '100'), 'partial or encoded local token does not widen lookup');
same([], search($pdo, 'https://east.subscriptions.test/sub/same-name/not-its-key', '100'), 'native username alone does not validate a link');
same([], search($pdo, str_repeat('a', 2049), null), 'oversized input');
$rows = ServiceSearch::addPanelIds($pdo, $pdo->query('SELECT * FROM invoice ORDER BY id_invoice')->fetchAll(PDO::FETCH_ASSOC));
same(['91', null, null, '91', null, null], array_column($rows, 'panel_user_id'), 'batch numeric enrichment keyed by panel and username');

$pdo->exec("ALTER TABLE invoice ADD time_sell bigint DEFAULT 0");
$pdo->exec("INSERT INTO invoice (id_invoice, id_user, username, Service_location, Status, time_sell)
    VALUES ('later-order', '100', 'same-name', 'east', 'active', 2)");
$panel = new ManagePanel();
$selected = $panel->DataUser('east', 'same-name', 'links', 'e123');
same('https://shop.example.test/sub/e123', $selected['subscription_url'] ?? null, 'selected order has its own Faoxima subscription link');
$legacy = $panel->DataUser('east', 'same-name');
same('https://shop.example.test/sub/later-order', $legacy['subscription_url'] ?? null, 'legacy callers resolve the latest order in their panel');
echo "$tested service search checks passed.\n";
