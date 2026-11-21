<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use CURLFile;
use Throwable;

final class CryptoReceiptUploadHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $orderId = \Faoxima\Api\Runtime\Input::string($_POST, 'order_id');
        if ($orderId === '') {
            \Faoxima\Api\Runtime\Response::badRequest('order_id is required');
        }

        if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            \Faoxima\Api\Runtime\Response::badRequest('photo file is required');
        }
        $f = $_FILES['photo'];
        if ((int)($f['error'] ?? 99) !== UPLOAD_ERR_OK) {
            \Faoxima\Api\Runtime\Response::badRequest('photo upload error: ' . ($f['error'] ?? 'unknown'));
        }
        if ((int)($f['size'] ?? 0) > 8 * 1024 * 1024) {
            \Faoxima\Api\Runtime\Response::badRequest('photo too large (max 8 MB)');
        }
        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            \Faoxima\Api\Runtime\Response::badRequest('photo not accessible on server');
        }

        $payment = \Faoxima\Api\Runtime\Db::fetchOne(
            "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND source = 'miniapp'
              LIMIT 1",
            [':o' => $orderId, ':u' => (string)$this->user['id']]
        );
        if ($payment === null) {
            \Faoxima\Api\Runtime\Response::notFound('Payment not found');
        }

        $currency = (string)($payment['crypto_currency'] ?? '');
        $manualCurrencies = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
        if (!isset($manualCurrencies[$currency])) {
            \Faoxima\Api\Runtime\Response::fail(422, 'این فاکتور نیاز به آپلود رسید ندارد.');
        }

        $currentStatus = (string)($payment['payment_Status'] ?? '');
        if ($currentStatus !== 'AwaitingHash') {
            \Faoxima\Api\Runtime\Response::fail(409, 'این فاکتور در وضعیت قابل ثبت رسید نیست (وضعیت فعلی: ' . $currentStatus . ')');
        }
        $hash = trim((string)($payment['crypto_tx_hash'] ?? ''));
        if ($hash === '') {
            \Faoxima\Api\Runtime\Response::fail(422, 'ابتدا باید هش تراکنش را ثبت کنید.');
        }

        global $APIKEY;
        $apiKey = is_string($APIKEY ?? null) ? $APIKEY : '';
        if ($apiKey === '') {
            $rowKey = \Faoxima\Core\Database\Db::select('setting', 'token_bot', null, null, 'select');
            $apiKey = is_array($rowKey) ? (string)($rowKey['token_bot'] ?? '') : '';
        }
        if ($apiKey === '') {
            \Faoxima\Api\Runtime\Response::fail(503, '❌ توکن ربات روی سرور تنظیم نشده است.');
        }

        $admins = [];
        try {
            $admins = \Faoxima\Api\Runtime\Db::fetchAll("SELECT id_admin FROM admin");
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('admin table fetch failed', ['err' => $e->getMessage()]);
        }
        $adminIds = [];
        foreach ($admins as $row) {
            $id = trim((string)($row['id_admin'] ?? ''));
            if ($id !== '' && ctype_digit($id)) {
                $adminIds[] = $id;
            }
        }
        if (empty($adminIds)) {
            \Faoxima\Api\Runtime\Response::fail(503, '❌ هیچ ادمینی روی سرور تنظیم نشده است.');
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
            \Faoxima\Api\Runtime\Logger::warn('Crypto receipt: all admins unreachable', ['admins' => $failedAdmins, 'order' => $orderId]);
            \Faoxima\Api\Runtime\Response::fail(502, '❌ ارسال رسید به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.');
        }

        foreach (array_merge($failedAdmins, $remainingAdmins) as $adminId) {
            $this->forwardReceiptByFileId($apiKey, $adminId, $receiptFileId, $caption, $keyboard);
        }

        try {
            $pdo = \Faoxima\Api\Runtime\Db::pdo();
            $stmt = $pdo->prepare(
                "UPDATE Payment_report SET card_photo_file_id = :fid
                  WHERE id_order = :o AND id_user = :u"
            );
            $stmt->execute([':fid' => $receiptFileId, ':o' => $orderId, ':u' => $userId]);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::warn('crypto receipt card_photo_file_id save failed', ['err' => $e->getMessage()]);
        }

        \Faoxima\Api\Runtime\Logger::debug('Crypto receipt uploaded', [
            'order'   => $orderId,
            'user_id' => $userId,
            'admins'  => count($adminIds),
        ]);

        \Faoxima\Api\Runtime\Response::ok([
            'order_id' => $orderId,
            'message'  => '✅ رسید شما برای ادمین ارسال شد. پس از تأیید، حساب شما شارژ می‌شود.',
        ]);
    }

    private function sendReceiptPhoto(string $apiKey, string $chatId, string $localPath, string $mime, string $caption, string $keyboardJson): ?string
    {
        $payload = [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'photo' => new CURLFile($localPath, $mime, 'receipt.jpg'),
            '_rx_curl_timeout' => 30,
        ];
        if ($keyboardJson !== '') { $payload['reply_markup'] = $keyboardJson; }
        $tg = telegram('sendPhoto', $payload, $apiKey);
        if (empty($tg['ok'])) {
            \Faoxima\Api\Runtime\Logger::warn('crypto receipt sendPhoto rejected', ['desc' => $tg['description'] ?? '', 'admin' => $chatId]);
            return null;
        }
        $sizes = $tg['result']['photo'] ?? [];
        if (!is_array($sizes) || !$sizes) { return null; }
        $best = end($sizes);
        return is_array($best) ? (string) ($best['file_id'] ?? '') : null;
    }

    private function forwardReceiptByFileId(string $apiKey, string $chatId, string $fileId, string $caption, string $keyboardJson): bool
    {
        if ($fileId === '') { return false; }
        $payload = ['chat_id' => $chatId, 'photo' => $fileId, 'caption' => $caption, 'parse_mode' => 'HTML', '_rx_curl_timeout' => 15];
        if ($keyboardJson !== '') { $payload['reply_markup'] = $keyboardJson; }
        $tg = telegram('sendPhoto', $payload, $apiKey);
        return !empty($tg['ok']);
    }
}
