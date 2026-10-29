<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Reuse the offline function loader and legacy API regression checks.
require __DIR__.'/cubepay-buyer-identity.php';
testLoadFunction($source, 'cubepayBotInvoice');
$state['api_token']='fixture-token';
$state['response']=['success'=>true,'method'=>'card','pay_page_url'=>'https://cubevps.ir/pay/choose.php?token=fixture','pay_amount_rial'=>'521578','crypto_payment_url'=>'https://cubevps.ir/pay/inline-crypto.php?token=fixture'];
$normalized=cubepayCreatePayment('contract',50000,123,'',true);
check($normalized['pay_amount_rial']==='521578' && $normalized['crypto_payment_url']===$state['response']['crypto_payment_url'],'New API fields must preserve exact rial and authenticated link');
foreach(['http://cubevps.ir/pay/','https://cubevps.ir.evil.example/pay/','https://evil.example/pay/','https://user@cubevps.ir/pay/','https://cubevps.ir:8443/pay/','javascript:alert(1)',[]] as $unsafe){
    $state['response']['crypto_payment_url']=$unsafe;
    check(!isset(cubepayCreatePayment('contract',50000,123,'',true)['crypto_payment_url']),'Untrusted crypto URL must not become a Telegram button');
}
unset($state['response']);
$state['api_token']='fixture-token'; $state['fee']='0'; $state['profile']=null;
$before=count($state['requests']);
cubepayCreatePayment('inline-order',50000,'123','',true);
check(count($state['requests'])===$before+1 && end($state['requests'])[2]['inline_card']===true,'One create-order request opts in; never create two payments');
cubepayCreatePayment('api-order',50000,'123');
check(!isset(end($state['requests'])[2]['inline_card']),'Legacy API and mini-app calls do not opt in');
$payment=['payment_link'=>'https://cubevps.ir/pay/choose.php?token=fixture', 'method'=>'card',
    'show_card_in_bot'=>true,'card'=>['number'=>'0000 0000 0000 0000','holder'=>'A&B <name>'],
    'pay_amount_toman'=>52157,'pay_amount_rial'=>521570,'expires_in_minutes'=>30,
    'crypto_payment_url'=>'https://cubevps.ir/pay/inline-crypto.php?token=fixture'];
$message=cubepayBotInvoice('order<&',50000,$payment,'پرداخت');
check(str_contains($message['text'],'⚠️ <b>مبلغ دقیق به تومان:</b> <code>52,157</code>'),'Exact toman is emphasized and monospace');
check(str_contains($message['text'],'⚠️ <b>مبلغ دقیق به ریال:</b> <code>521,570</code>'),'Exact rial is emphasized and monospace');
check(!str_contains($message['text'],'50,000'),'Base amount does not compete with the exact payable amount');
check(str_contains($message['text'],'A&amp;B &lt;name&gt;') && str_contains($message['text'],'order&lt;&amp;'),'Gateway and order text is HTML escaped');
$buttons=json_decode($message['reply_markup'],true)['inline_keyboard'];
check(count($buttons)===2 && $buttons[0][0]['url']===$payment['crypto_payment_url'] && $buttons[1][0]['url']===$payment['payment_link'],'Crypto is attached to the same message and original order links');
$payment['pay_amount_rial']=521578;
check(str_contains(cubepayBotInvoice('x',50000,$payment,'پرداخت')['text'],'<code>52,157.8</code>'),'Fractional toman is not truncated when exact rial is supplied');
unset($payment['pay_amount_rial'],$payment['crypto_payment_url']);
$message=cubepayBotInvoice('x',50000,$payment,'پرداخت');
check(str_contains($message['text'],'<code>521,570</code>') && count(json_decode($message['reply_markup'],true)['inline_keyboard'])===1,'Legacy card response retains two units without inventing a crypto link');
foreach([null,0,-1,'not-a-number',[],521570.5] as $bad){
    $payment['pay_amount_rial']=$bad;
    check(!str_contains(cubepayBotInvoice('x',50000,$payment,'پرداخت')['text'],'شماره کارت'),'Malformed explicit exact amount falls back to payment page');
}
foreach(['choice','crypto'] as $method){
    $m=cubepayBotInvoice('x',50000,['method'=>$method,'payment_link'=>'https://cubevps.ir/pay/example'],'پرداخت');
    check(!str_contains($m['text'],'شماره کارت') && count(json_decode($m['reply_markup'],true)['inline_keyboard'])===1,'No guessed card or extra invoice for '.$method);
}
$payment['pay_amount_rial']=521570; $payment['show_card_in_bot']=false;
check(!str_contains(cubepayBotInvoice('x',50000,$payment,'پرداخت')['text'],'شماره کارت'),'Merchant hiding preference is preserved');
// Check the actual handler sends the constructed invoice once and tracks that message.
$flow=file_get_contents(dirname(__DIR__,2).'/re/rx/index/user_flow.php');
$start=strpos($flow,'} elseif ($datain == "cubepay") {');$end=strpos($flow,'} elseif ($datain == "variza") {',$start);
$handler=substr($flow,$start,$end-$start);
check(substr_count($handler,'cubepayCreatePayment(')===1 && substr_count($handler,"\$cubepayInvoice['text']")===1 && !str_contains($handler,'$cubepayCardText'),'Single request and single invoice message in the real handler');
check(str_contains($handler,'updatePaymentMessageId($message_id, $randomString)'),'Status updates still target the actual invoice message');
echo "PASS: inline card/crypto invoice, exact rial/toman, escaping, legacy fallback and one order.\n";
