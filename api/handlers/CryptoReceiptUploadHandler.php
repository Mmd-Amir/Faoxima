<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class CryptoReceiptUploadHandler extends BaseHandler
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
            "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND source = 'miniapp'
              LIMIT 1",
            [':o' => $orderId, ':u' => (string)$this->user['id']]
        );
        if ($payment === null) {
            FaoximaResponse::notFound('Payment not found');
        }

        $currency = (string)($payment['crypto_currency'] ?? '');
        $manualCurrencies = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
        if (!isset($manualCurrencies[$currency])) {
            FaoximaResponse::fail(422, 'این فاکتور نیاز به آپلود رسید ندارد.');
        }

        $currentStatus = (string)($payment['payment_Status'] ?? '');
        if ($currentStatus !== 'AwaitingHash') {
            FaoximaResponse::fail(409, 'این فاکتور در وضعیت قابل ثبت رسید نیست (وضعیت فعلی: ' . $currentStatus . ')');
        }
        $hash = trim((string)($payment['crypto_tx_hash'] ?? ''));
        if ($hash === '') {
            FaoximaResponse::fail(422, 'ابتدا باید هش تراکنش را ثبت کنید.');
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

        $admins = [];
        try {
            $admins = FaoximaDb::fetchAll("SELECT id_admin FROM admin");
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

        $userId   = (string)$this->user['id'];
        $userName = (string)($this->user['username'] ?? '');
        $name     = trim((string)($this->user['first_name'] ?? '') . ' ' . (string)($this->user['last_name'] ?? ''));
        $userLink = "<a href=\"tg://user?id={$userId}\">" .
            htmlspecialchars($name !== '' ? $name : $userId, ENT_QUOTES) .
            "</a>" . ($userName !== '' ? ' (@' . htmlspecialchars($userName, ENT_QUOTES) . ')' : '');

        $caption = "🔁 <b>هش + رسید پرداخت کریپتو ثبت شد (شبکه دستی)</b>\n\n"
            . "🛒 کد فاکتور: <code>" . htmlspecialchars($orderId, ENT_QUOTES) . "</code>\n"
            . "👤 کاربر: {$userLink}\n"
            . "💎 ارز: <b>" . htmlspecialchars($currency, ENT_QUOTES) . "</b>\n"
            . "💸 مبلغ تومانی: " . number_format((int)($payment['price'] ?? 0)) . " تومان\n"
            . "🔗 هش: <code>" . htmlspecialchars($hash, ENT_QUOTES) . "</code>";

        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و شارژ', 'callback_data' => 'confirmcryptomanual_' . $orderId],
                    ['text' => '❌ رد درخواست',   'callback_data' => 'rejectcryptomanual_' . $orderId],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $receiptFileId = null;
        $failedAdmins = [];
        $remainingAdmins = $adminIds;
        while (!empty($remainingAdmins)) {
            $candidate = array_shift($remainingAdmins);
            $maybeFileId = $this->sendReceiptPhoto($apiKey, $candidate, $tmp, (string)($f['type'] ?? 'image/jpeg'), $caption, $keyboard);
            if (is_string($maybeFileId) && $maybeFileId !== '') {
                $receiptFileId = $maybeFileId;
                break;
            }
            $failedAdmins[] = $candidate;
        }

        if ($receiptFileId === null) {
            FaoximaLogger::warn('Crypto receipt: all admins unreachable', ['admins' => $failedAdmins, 'order' => $orderId]);
            FaoximaResponse::fail(502, '❌ ارسال رسید به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.');
        }

        foreach (array_merge($failedAdmins, $remainingAdmins) as $adminId) {
            $this->forwardReceiptByFileId($apiKey, $adminId, $receiptFileId, $caption, $keyboard);
        }

        try {
            $pdo = FaoximaDb::pdo();
            $stmt = $pdo->prepare(
                "UPDATE Payment_report SET card_photo_file_id = :fid
                  WHERE id_order = :o AND id_user = :u"
            );
            $stmt->execute([':fid' => $receiptFileId, ':o' => $orderId, ':u' => $userId]);
        } catch (Throwable $e) {
            FaoximaLogger::warn('crypto receipt card_photo_file_id save failed', ['err' => $e->getMessage()]);
        }

        FaoximaLogger::debug('Crypto receipt uploaded', [
            'order'   => $orderId,
            'user_id' => $userId,
            'admins'  => count($adminIds),
        ]);

        FaoximaResponse::ok([
            'order_id' => $orderId,
            'message'  => '✅ رسید شما برای ادمین ارسال شد. پس از تأیید، حساب شما شارژ می‌شود.',
        ]);
    }

    private function sendReceiptPhoto(string $apiKey, string $chatId, string $localPath, string $mime, string $caption, string $keyboardJson): ?string
    {
        if (function_exists('applyPremiumEmojiTransform')) { $caption = applyPremiumEmojiTransform($caption, 'HTML'); }
        $ch = curl_init('https://api.telegram.org/bot' . $apiKey . '/sendPhoto');
        FaoximaTelegramTransport::applyProxy($ch);
        $post = [
            'chat_id'    => $chatId,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
            'photo'      => new CURLFile($localPath, $mime, 'receipt.jpg'),
        ];
        if ($keyboardJson !== '') { $post['reply_markup'] = $keyboardJson; }
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
            FaoximaLogger::warn('crypto receipt sendPhoto HTTP failed', ['http' => $httpCode, 'curl_err' => $curlErr, 'admin' => $chatId]);
            return null;
        }
        $tg = json_decode((string)$response, true);
        if (!is_array($tg) || empty($tg['ok'])) {
            $desc = is_array($tg) ? (string)($tg['description'] ?? '') : '';
            FaoximaLogger::warn('crypto receipt sendPhoto rejected', ['desc' => $desc, 'admin' => $chatId]);
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
        if (function_exists('applyPremiumEmojiTransform')) { $caption = applyPremiumEmojiTransform($caption, 'HTML'); }
        $url = 'https://api.telegram.org/bot' . $apiKey . '/sendPhoto';
        $payload = [
            'chat_id'    => $chatId,
            'photo'      => $fileId,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ];
        if ($keyboardJson !== '') { $payload['reply_markup'] = $keyboardJson; }
        $ch = curl_init($url);
        FaoximaTelegramTransport::applyProxy($ch);
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
}
