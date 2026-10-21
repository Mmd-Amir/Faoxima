<?php
declare(strict_types=1);
// Run: php scripts/tests/abangateway-card-invoice.php
// abangatewayCardInvoice() against the shape abangatewayCreatePayment()
// returns: no bot config, no database, no network.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$textbotlang = ['users' => ['Balance' => ['payments' => 'پرداخت']]];

// No textbot table here, so every text falls back to its default.
function select($table, $field, $whereField = null, $whereValue = null, $type = 'select')
{
    return null;
}

require $root . '/re/rx/function/bot_api_helpers.php';
require $root . '/re/rx/function/database_helpers_2.php';

$failed = 0;
function expect(bool $ok, string $what): void
{
    global $failed;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $what . PHP_EOL;
    $failed += $ok ? 0 : 1;
}

$pay = [
    'success' => true,
    'authority' => 'abn_inv_7k2m',
    'pay_url' => 'https://abangateway.example.test/pay/inv_7k2m',
    'card_in_bot' => true,
    'card' => ['number' => '6037991234567890', 'holder' => 'علی <محمدی>', 'bank' => 'بانک ملی'],
    'payable_rial' => 1250040,
    'payable_toman' => 125004,
    'minutes_left' => 30,
];

$message = abangatewayCardInvoice($pay, 'ab12cd34ef');
expect(is_array($message), 'a card answer builds the card message');
$text = $message['text'];
$keyboard = json_decode($message['keyboard'], true);
expect(strpos($text, '<code>6037991234567890</code>') !== false, 'card number is tap-to-copy');
expect(strpos($text, '<code>1250040</code>') !== false && strpos($text, '<code>125004</code>') !== false, 'Rial and Toman are tap-to-copy');
expect(strpos($text, '125,004') !== false, 'the amount is shown grouped');
expect(strpos($text, 'علی &lt;محمدی&gt; (بانک ملی)') !== false, 'the holder is escaped, the bank beside it');
expect(strpos($text, '<code>ab12cd34ef</code>') !== false, 'the tracking code is shown');
expect(strpos($text, '{') === false, 'no placeholder is left');
expect($keyboard['inline_keyboard'][0][0]['copy_text']['text'] === '6037991234567890', 'copy card');
expect($keyboard['inline_keyboard'][1][0]['copy_text']['text'] === '1250040', 'copy Rial');
expect($keyboard['inline_keyboard'][1][1]['copy_text']['text'] === '125004', 'copy Toman');
expect($keyboard['inline_keyboard'][2][0]['url'] === $pay['pay_url'], 'the payment page stays one button away');

expect(abangatewayCardInvoice(['success' => true, 'authority' => 'abn_x', 'pay_url' => 'https://a.test/pay/x'], 'o') === null, 'an answer without a card keeps the usual message');
expect(abangatewayCardInvoice(['card_in_bot' => false] + $pay, 'o') === null, 'the seller switched it off');
expect(abangatewayCardInvoice(['payable_rial' => 0] + $pay, 'o') === null, 'no amount, no card message');
expect(abangatewayCardInvoice(['pay_url' => 'http://a.test/pay/x'] + $pay, 'o') === null, 'a link that is not https is not offered');
$noBank = $pay;
$noBank['card']['bank'] = null;
expect(strpos(abangatewayCardInvoice($noBank, 'o')['text'], '()') === false, 'no empty brackets without a bank');

echo $failed === 0 ? 'all passed' . PHP_EOL : $failed . ' failed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
