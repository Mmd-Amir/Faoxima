<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class PaymentReceiptHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $orderId = FaoximaInput::string($_POST, 'order_id');
        if ($orderId === '') {
            FaoximaResponse::badRequest('order_id is required');
        }

        if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            FaoximaResponse::badRequest('photo file is required');
        }
        $f = $_FILES['photo'];
        if ((int)($f['error'] ?? 99) !== UPLOAD_ERR_OK) {
            FaoximaResponse::badRequest('photo upload error: ' . ($f['error'] ?? 'unknown'));
        }
        if ((int)($f['size'] ?? 0) > 8 * 1024 * 1024) {
            FaoximaResponse::badRequest('photo too large (max 8 MB)');
        }
        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            FaoximaResponse::badRequest('photo not accessible on server');
        }


        $payment = FaoximaDb::fetchOne(
            'SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND source = \'miniapp\'
              LIMIT 1',
            [':o' => $orderId, ':u' => $this->user['id']]
        );
        if ($payment === null) {
            FaoximaResponse::notFound('Payment record not found');
        }
        $currentStatus = strtolower((string)($payment['payment_Status'] ?? ''));
        if ($currentStatus === 'paid') {
            FaoximaResponse::fail(409, '✅ این پرداخت قبلاً تأیید شده است.');
        }


        $admins = [];
        try {
            $admins = FaoximaDb::fetchAll(
                "SELECT id_admin FROM admin
                  WHERE rule = 'administrator'
                     OR rule = 'Seller'"
            );
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('admin table fetch failed', ['err' => $e->getMessage()]);
        }
        $adminIds = [];
        foreach ($admins as $row) {
            $id = trim((string)($row['id_admin'] ?? ''));
            if ($id !== '' && ctype_digit($id)) {
                $adminIds[] = $id;
            }
        }
        if (empty($adminIds)) {
            FaoximaResponse::fail(503, '❌ هیچ ادمینی روی سرور تنظیم نشده است.');
        }


        global $APIKEY;
        $apiKey = is_string($APIKEY ?? null) ? $APIKEY : '';
        if ($apiKey === '') {
            $rowKey = select('setting', 'token_bot', null, null, 'select');
            $apiKey = is_array($rowKey) ? (string)($rowKey['token_bot'] ?? '') : '';
        }
        if ($apiKey === '') {
            FaoximaResponse::fail(503, '❌ توکن ربات روی سرور تنظیم نشده است.');
        }

        $userId   = (string)$this->user['id'];
        $userName = (string)($this->user['username'] ?? '');
        $name     = trim((string)($this->user['first_name'] ?? '') . ' ' . (string)($this->user['last_name'] ?? ''));
        $balance  = (int)($this->user['Balance'] ?? 0);
        $amount   = (int)($payment['price'] ?? 0);
        $method   = (string)($payment['Payment_Method'] ?? 'cart to cart');


        $caption =
            "💳 رسید پرداخت کارت‌به‌کارت (از مینی‌اپ)\n\n" .
            "🆔 کد پیگیری: <code>" . htmlspecialchars($orderId, ENT_QUOTES) . "</code>\n" .
            "💰 مبلغ: " . number_format($amount) . " تومان\n" .
            "👤 کاربر: <a href=\"tg://user?id={$userId}\">" .
                htmlspecialchars($name !== '' ? $name : $userId, ENT_QUOTES) .
                "</a>" . ($userName !== '' ? ' (@' . htmlspecialchars($userName, ENT_QUOTES) . ')' : '') . "\n" .
            "🪪 شناسه عددی: <code>{$userId}</code>\n" .
            "💎 موجودی فعلی: " . number_format($balance) . " تومان\n" .
            "📌 روش: " . htmlspecialchars($method, ENT_QUOTES);


        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأیید', 'callback_data' => 'Confirm_pay_' . $orderId],
                    ['text' => '❌ رد',    'callback_data' => 'reject_pay_'  . $orderId],
                ],
                [
                    ['text' => '➕ افزایش موجودی',     'callback_data' => 'addbalamceuser_' . $orderId],
                    ['text' => '🚫 مسدود (رسید جعلی)', 'callback_data' => 'blockuserfake_' . $userId],
                ],
                [
                    ['text' => '👁 مشاهده کاربر', 'url' => 'tg://user?id=' . $userId],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);


        $firstAdmin = array_shift($adminIds);
        $fileId = $this->sendReceiptPhoto($apiKey, $firstAdmin, $tmp, (string)($f['type'] ?? 'image/jpeg'), $caption, $keyboard);
        if ($fileId === null) {
            FaoximaResponse::fail(502, '❌ ارسال رسید به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.');
        }


        foreach ($adminIds as $adminId) {


            $ok = $this->forwardReceiptByFileId($apiKey, $adminId, $fileId, $caption, $keyboard);
            if (!$ok) {
                $this->sendReceiptText($apiKey, $adminId, $caption, $keyboard);
            }
        }


        try {
            $pdo = FaoximaDb::pdo();
            $stmt = $pdo->prepare(
                'UPDATE Payment_report
                    SET payment_Status = :s
                  WHERE id_order = :o AND id_user = :u AND source = \'miniapp\''
            );
            $stmt->execute([
                ':s' => 'waiting',
                ':o' => $orderId,
                ':u' => $this->user['id'],
            ]);
        } catch (Throwable $e) {
            FaoximaLogger::warn('Payment_report status update failed', ['err' => $e->getMessage()]);
        }

        FaoximaLogger::debug('Receipt uploaded', [
            'order'    => $orderId,
            'user_id'  => $this->user['id'],
            'amount'   => $amount,
            'admins'   => 1 + count($adminIds),
            'first_admin' => $firstAdmin,
        ]);

        FaoximaResponse::ok([
            'order_id' => $orderId,
            'message'  => '✅ رسید شما برای ادمین ارسال شد. پس از تأیید، حساب شما شارژ می‌شود.',
        ]);
    }


    private function sendReceiptPhoto(string $apiKey, string $chatId, string $localPath, string $mime, string $caption, string $keyboardJson): ?string
    {
        $ch = curl_init('https://api.telegram.org/bot' . $apiKey . '/sendPhoto');
        $post = [
            'chat_id'      => $chatId,
            'caption'      => $caption,
            'parse_mode'   => 'HTML',
            'reply_markup' => $keyboardJson,
            'photo'        => new CURLFile($localPath, $mime, 'receipt.jpg'),
        ];
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            FaoximaLogger::warn('sendPhoto HTTP failed', ['http' => $httpCode, 'curl_err' => $curlErr, 'admin' => $chatId]);
            return null;
        }
        $tg = json_decode((string)$response, true);
        if (!is_array($tg) || empty($tg['ok'])) {
            $desc = is_array($tg) ? (string)($tg['description'] ?? '') : '';
            FaoximaLogger::warn('sendPhoto rejected', ['desc' => $desc, 'admin' => $chatId]);
            return null;
        }


        $sizes = $tg['result']['photo'] ?? [];
        if (!is_array($sizes) || empty($sizes)) return null;
        $best = end($sizes);
        return is_array($best) ? (string)($best['file_id'] ?? '') : null;
    }


    private function forwardReceiptByFileId(string $apiKey, string $chatId, string $fileId, string $caption, string $keyboardJson): bool
    {
        if ($fileId === '') return false;
        $url = 'https://api.telegram.org/bot' . $apiKey . '/sendPhoto';
        $payload = [
            'chat_id'      => $chatId,
            'photo'        => $fileId,
            'caption'      => $caption,
            'parse_mode'   => 'HTML',
            'reply_markup' => $keyboardJson,
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || !$response) return false;
        $tg = json_decode((string)$response, true);
        return is_array($tg) && !empty($tg['ok']);
    }


    private function sendReceiptText(string $apiKey, string $chatId, string $caption, string $keyboardJson): void
    {
        $url = 'https://api.telegram.org/bot' . $apiKey . '/sendMessage';
        $payload = [
            'chat_id'      => $chatId,
            'text'         => $caption,
            'parse_mode'   => 'HTML',
            'reply_markup' => $keyboardJson,
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        @curl_exec($ch);
        curl_close($ch);
    }
}

