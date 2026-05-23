<?php

session_start();

$rawBody = file_get_contents('php://input');
$decodedBody = json_decode($rawBody, true);
$callbackPayload = [];
if (is_array($decodedBody)) {
    $callbackPayload = array_merge($callbackPayload, $decodedBody);
}
if (!empty($_POST)) {
    $callbackPayload = array_merge($callbackPayload, $_POST);
}
if (!empty($_GET)) {
    $callbackPayload = array_merge($callbackPayload, $_GET);
}

$normalizeValue = static function ($value) {
    if ($value === null) {
        return null;
    }

    if (is_scalar($value)) {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    return null;
};

$sessionAuthority = $normalizeValue($_SESSION['authority'] ?? null);
$sessionOrderId = $normalizeValue($_SESSION['order_id'] ?? null);
$callbackAuthority = $normalizeValue($callbackPayload['authority'] ?? null);
$callbackOrderId = $normalizeValue($callbackPayload['order_id'] ?? null);

$authority = $sessionAuthority ?: $callbackAuthority;
$invoiceId = $sessionOrderId ?: $callbackOrderId;

$hasSessionData = $sessionAuthority !== null && $sessionOrderId !== null;
$hasCallbackData = $callbackAuthority !== null && $callbackOrderId !== null;

if ($authority === null || $invoiceId === null) {
    http_response_code(400);
    echo 'پارامترهای لازم یافت نشد.';
    exit;
}

$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/config.php';
require_once $projectRoot . '/jdf.php';
require_once $projectRoot . '/botapi.php';
require_once $projectRoot . '/Marzban.php';
require_once $projectRoot . '/function.php';
require_once $projectRoot . '/panels.php';
require_once $projectRoot . '/keyboard.php';

$ManagePanel = new ManagePanel();

$textbotlang = languagechange($projectRoot . '/text.json');

$datatextbotRecords = select('textbot', '*', null, null, 'fetchAll');
$datatextbot = [
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'text_wgdashboard' => '',
    'textafterpayibsng' => '',
];

if (is_array($datatextbotRecords)) {
    foreach ($datatextbotRecords as $row) {
        $key = $row['id_text'] ?? null;
        if ($key !== null && array_key_exists($key, $datatextbot)) {
            $datatextbot[$key] = $row['text'];
        }
    }
}

$paymentReport = select('Payment_report', '*', 'id_order', $invoiceId, 'select');
if (!is_array($paymentReport)) {
    http_response_code(404);
    echo 'تراکنش یافت نشد.';
    exit;
}

try {
    $payload = json_encode([
        'authority' => $authority,
    ], JSON_UNESCAPED_UNICODE);

    $token = getPaySettingValue('token_zarinpey');
    if (empty($token) || $token === '0') {
        throw new Exception('توکن زرین پی تنظیم نشده است.');
    }

    $ch = curl_init('https://zarinpay.me/api/verify-payment');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('خطا در اتصال: ' . $error);
    }

    curl_close($ch);

    $result = json_decode($response, true);
    if (!is_array($result) || empty($result['success'])) {
        $message = $result['message'] ?? 'پرداخت انجام نشد';
        throw new Exception($message);
    }

    $verifyCode = $result['data']['code'] ?? null;
    if (!in_array($verifyCode, [100, 101], true)) {
        throw new Exception('پرداخت تایید نشد.');
    }

    $setting = select('setting', '*');
    $paymentreports = select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? null;
    $payment_status = 'پرداخت موفق';
    $dec_payment_status = 'از انجام تراکنش متشکریم!';

    if ($paymentReport['payment_Status'] !== 'paid') {


        $atomic = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'paid' "
            . "WHERE id_order = :id AND payment_Status <> 'paid'"
        );
        $atomic->bindValue(':id', $paymentReport['id_order'], PDO::PARAM_STR);
        $atomic->execute();
        if ($atomic->rowCount() < 1) {

            $payment_status = 'پرداخت موفق';
            $dec_payment_status = 'از انجام تراکنش متشکریم!';
        } else {
        DirectPayment($paymentReport['id_order']);
        update('user', 'Processing_value', '0', 'id', $paymentReport['id_user']);
        update('user', 'Processing_value_one', '0', 'id', $paymentReport['id_user']);
        update('user', 'Processing_value_tow', '0', 'id', $paymentReport['id_user']);
        update('Payment_report', 'payment_Status', 'paid', 'id_order', $paymentReport['id_order']);

        if (!empty($setting['Channel_Report'])) {
            $priceFormatted = number_format($paymentReport['price']);
            $userInfo = select('user', '*', 'id', $paymentReport['id_user'], 'select');
            $username = $userInfo['username'] ?? '—';
            $transactionId = $result['data']['transaction']['payment_id'] ?? '';

            $reportLines = [
                '💵 پرداخت جدید',
                '',
                "آیدی عددی کاربر : {$paymentReport['id_user']}",
                "نام کاربری کاربر : @{$username}",
                "مبلغ تراکنش : {$priceFormatted} تومان",
            ];

            if (!empty($transactionId)) {
                $reportLines[] = "شناسه تراکنش : {$transactionId}";
            }

            $reportLines[] = 'روش پرداخت : زرین پی';

            $text_report = implode("\n", $reportLines);

            $telegramPayload = [
                'chat_id' => $setting['Channel_Report'],
                'text' => $text_report,
                'parse_mode' => 'HTML',
            ];

            if (!empty($paymentreports)) {
                $telegramPayload['message_thread_id'] = $paymentreports;
            }

            telegram('sendmessage', $telegramPayload);
        }
        }
    }
} catch (Exception $e) {
    if ($hasCallbackData) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: failed.php');
    }

    session_unset();
    session_destroy();

    exit;
}

if ($hasCallbackData) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    session_unset();
    session_destroy();
    exit;
}

$price = $paymentReport['price'];
$payment_status = $payment_status ?? 'پرداخت موفق';
$dec_payment_status = $dec_payment_status ?? 'از انجام تراکنش متشکریم!';
?>
   <!DOCTYPE html>
   <html lang="en">

   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>پرداخت موفق</title>


      <style>
         * {
            font-family: "vazir";
            direction: rtl;
         }

         .card {
            box-shadow: 0 15px 16.8px rgba(0, 0, 0, 0.031), 0 100px 134px rgba(0, 0, 0, 0.05);
            background-color: white;
            border-radius: 15px;
            padding: 35px;
         }

         .top {
            padding-bottom: 25px;
            min-width: 250px;
            text-align: center;
            border-bottom: dashed
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
            border-left: 0.18em dashed
            position: relative;
         }

         .top:before {
            background-color:
            position: absolute;
            content: "";
            display: block;
            width: 20px;
            height: 20px;
            border-radius: 100%;
            bottom: 0;
            right: -10px;
            margin-bottom: -10px;
         }

         svg,
         h3 {
            color:
         }

         svg {
            margin: 0 auto;
            width: 60px;
            height: 60px;
         }

         h3 {
            margin-top: 0px;
            margin-bottom: 10px;
         }

         span {
            color:
            font-size: 12px;
         }

         .bottom {
            text-align: center;
            margin-top: 30px;
         }

         .key-value {
            display: flex;
            justify-content: space-between;
         }

         .key-value span:first-child {
            font-weight: 0;
         }

         a {
            padding: 8px 20px;
            background-color:
            text-decoration: none;
            color: white;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 20px;
            display: block;
         }

         .outer-container {
            background-color:
            position: absolute;
            display: table;
            width: 100%;
            height: 100%;
            top: 0;
            right: 0;
         }

         .inner-container {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
         }

         .centered-content {
            display: inline-block;
            text-align: left;
            background:
            margin-top: 10px;
         }
      </style>

      <link href="https://cdnjs.cloudflare.com/ajax/libs/vazir-font/27.2.0/font-face.css" rel="stylesheet"
         type="text/css">


   </head>

   <body>
      <div class="outer-container">
         <div class="inner-container">
            <div class="card centered-content">
               <div class="top">

                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                     <path fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd" />
                  </svg>
                  <h3>
                     پرداخت موفق!
                  </h3>
                  <span>شماره تراکنش: <?php echo htmlspecialchars($invoiceId, ENT_QUOTES, 'UTF-8'); ?></span>
               </div>
               <div class="bottom">
                  <div class="key-value">
                     <span>پرداخت با موفقیت انجام شد</span>
                  </div>
                  <div class="key-value">
                     <span>مبلغ پرداختی: <?php echo number_format($price) ?> تومان</span>
                  </div>
                  <div class="key-value">
                     <span>زمان: <?php echo jdate('Y/m/d H:i') ?></span>
                  </div>
                  <a href="http://t.me/<?php echo htmlspecialchars($usernamebot, ENT_QUOTES, 'UTF-8'); ?>"> برگشت به ربات</a>
               </div>
            </div>
         </div>
      </div>
   </body>

   </html>
<?php
session_unset();
session_destroy();
exit;


