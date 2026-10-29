<?php
declare(strict_types=1);
// Run: php scripts/tests/cubepay-buyer-identity.php
// Loads only the production functions under test, without bot config or network access.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function testLoadFunction(string $source, string $name): void
{
    $tokens = token_get_all($source, TOKEN_PARSE);
    for ($i = 0, $n = count($tokens); $i < $n; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) continue;
        $code = ''; $depth = 0; $body = false;
        for ($j = $i; $j < $n; $j++) {
            $token = $tokens[$j];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { $depth++; $body = true; }
            if ($token === '}' && --$depth === 0 && $body) { eval($code); return; }
        }
    }
    throw new RuntimeException('Production function not found: ' . $name);
}
function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
$state = ['api_token' => 'fixture-token', 'fee' => '0', 'profile' => null, 'lookup_error' => false, 'requests' => [], 'lookups' => []];
$domainhosts = 'store.example.test/bot';
function cubepayApiToken() { return $GLOBALS['state']['api_token']; }
function getPaySettingValue($key, $default = '') { return $key === 'feecubepay' ? $GLOBALS['state']['fee'] : $default; }
function select($table, $field, $whereField = null, $whereValue = null, $type = 'select')
{
    $GLOBALS['state']['lookups'][] = [$table, $field, $whereField, $whereValue, $type];
    if ($GLOBALS['state']['lookup_error']) throw new RuntimeException('Fixture database unavailable');
    return $GLOBALS['state']['profile'];
}
function cubepayCurlJson($method, $endpoint, $payload, $token)
{
    $GLOBALS['state']['requests'][] = [$method, $endpoint, $payload, $token];
    if (isset($GLOBALS['state']['response'])) return $GLOBALS['state']['response'];
    return ['success' => true, 'method' => 'card', 'authority' => 'fixture-authority',
        'pay_page_url' => 'https://gateway.example.test/pay', 'card' => ['number' => '0000'],
        'pay_amount_toman' => 50037, 'expires_in_minutes' => 15, 'show_card_in_bot' => true];
}
$source = file_get_contents(dirname(__DIR__, 2) . '/re/rx/function/database_helpers_2.php');
if ($source === false) throw new RuntimeException('Cannot read production source');
testLoadFunction($source, 'cubepayPayableAmount');
testLoadFunction($source, 'cubepayCreatePayment');

$state['profile'] = ['username' => '@Buyer_name'];
$result = cubepayCreatePayment('order-A', 50000, '7000000123');
$request = $state['requests'][0]; $payload = $request[2];
check(($payload['customer_user_id'] ?? null) === '7000000123', 'Large Telegram ID must be sent exactly as a string');
check(($payload['customer_username'] ?? null) === 'Buyer_name', 'Username must be sent without @');
check($state['lookups'][0] === ['user', 'username', 'id', '7000000123', 'select'], 'Profile lookup must use this buyer, not another user');
check($request[0] === 'POST' && $request[1] === 'https://cubevps.ir/pay/create-order.php' && $request[3] === 'fixture-token', 'Endpoint/auth must be preserved');
check($payload['order_id'] === 'order-A' && $payload['price_amount'] === 50000 && $payload['redirect_after_payment'] === false, 'Financial/request behavior must be preserved');
check($payload['callback_url'] === 'https://store.example.test/bot/payment/cubepay.php', 'Callback must be preserved');
check($result === ['success'=>true,'method'=>'card','authority'=>'fixture-authority','payment_link'=>'https://gateway.example.test/pay','card'=>['number'=>'0000'],'pay_amount_toman'=>50037,'expires_in_minutes'=>15,'show_card_in_bot'=>true], 'Response normalization must be preserved');

// Some legacy select() implementations return the selected field directly.
$state['profile'] = '@ScalarBuyer';
cubepayCreatePayment('order-B', 1, '7000000456');
check(end($state['requests'])[2]['customer_username'] === 'ScalarBuyer', 'Support scalar select result');
check(end($state['requests'])[2]['customer_user_id'] === '7000000456', 'Sequential buyers cannot bleed into each other');
foreach ([null, false, [], ['username'=>null], ['username'=>'none'], ['username'=>'NULL'], ['username'=>'<script>']] as $profile) {
    $state['profile'] = $profile;
    cubepayCreatePayment('no-username', 50000, 123456789);
    $payload = end($state['requests'])[2];
    check($payload['customer_user_id'] === '123456789', 'No username must not hide ID');
    check(!isset($payload['customer_username']), 'Missing or invalid username must be omitted');
}
$state['lookup_error'] = true;
$result = cubepayCreatePayment('lookup-failure', 50000, '7000000123');
check($result['success'] && end($state['requests'])[2]['customer_user_id'] === '7000000123', 'Lookup failure must not interrupt creation or drop ID');
$state['lookup_error'] = false;
foreach ([null, '', '0', '-1', 'abc', [], true, 1.5] as $id) {
    $before = count($state['lookups']);
    $result = cubepayCreatePayment('legacy', 50000, $id);
    check($result['success'] && !isset(end($state['requests'])[2]['customer_user_id']), 'Legacy/missing ID must not be fabricated');
    check(count($state['lookups']) === $before, 'Invalid ID must not trigger a user lookup');
}
foreach (['25'=>62500, '1000'=>51000] as $fee=>$expected) {
    $state['fee'] = (string)$fee;
    cubepayCreatePayment('fee', 50000, '123');
    check(end($state['requests'])[2]['price_amount'] === $expected, 'Percentage/fixed fee behavior must remain unchanged');
}
$state['api_token'] = '';
$before = count($state['requests']);
check(cubepayCreatePayment('no-token', 50000, '123')['success'] === false && count($state['requests']) === $before, 'Missing API token must still reject before network');
echo "PASS: CubePay buyer metadata, legacy calls, scoped profile lookup and financial behavior.\n";

