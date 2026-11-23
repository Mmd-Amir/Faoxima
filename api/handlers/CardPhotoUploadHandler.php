<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class CardPhotoUploadHandler extends BaseHandler
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
              WHERE id_order = :o AND id_user = :u AND payment_Status = 'Unpaid'
              LIMIT 1",
            [':o' => $orderId, ':u' => $this->user['id']]
        );
        if ($payment === null) {
            FaoximaResponse::notFound('Payment record not found or already processed');
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

        $fileId = $this->uploadPhotoGetFileId($apiKey, $this->user['id'], $tmp, (string)($f['type'] ?? 'image/jpeg'));
        if ($fileId === null) {
            FaoximaResponse::fail(502, '❌ آپلود تصویر کارت به سرور تلگرام ناموفق بود. لطفاً دوباره تلاش کنید.');
        }

        $cardLast4 = FaoximaInput::string($_POST, 'card_last4');
        if ($cardLast4 !== '' && !preg_match('/^\d{4}$/', $cardLast4)) {
            FaoximaResponse::badRequest('card_last4 must be exactly 4 digits');
        }

        try {
            $pdo = FaoximaDb::pdo();
            if ($cardLast4 !== '') {
                $stmt = $pdo->prepare(
                    "UPDATE Payment_report SET card_photo_file_id = :fid, card_last4 = :l4
                      WHERE id_order = :o AND id_user = :u AND payment_Status = 'Unpaid'"
                );
                $stmt->execute([':fid' => $fileId, ':l4' => $cardLast4, ':o' => $orderId, ':u' => $this->user['id']]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE Payment_report SET card_photo_file_id = :fid
                      WHERE id_order = :o AND id_user = :u AND payment_Status = 'Unpaid'"
                );
                $stmt->execute([':fid' => $fileId, ':o' => $orderId, ':u' => $this->user['id']]);
            }
        } catch (Throwable $e) {
            FaoximaLogger::warn('card_photo_file_id update failed', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(500, '❌ خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.');
        }

        $card  = null;
        $card2 = null;
        try {
            $userId = (string)$this->user['id'];
            $pdo    = FaoximaDb::pdo();
            $mRow   = FaoximaDb::fetchOne("SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
            $dMode  = is_array($mRow) ? ($mRow['ValuePay'] ?? 'random') : 'random';
            $accessSql = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?)))";

            if ($dMode == 'direct') {
                $stmt = $pdo->prepare("SELECT cardnumber, namecard FROM card_number cn WHERE {$accessSql} ORDER BY cn.created_at ASC LIMIT 2");
                $stmt->execute([$userId, $userId]);
                $rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $card  = $rows[0] ?? null;
                $card2 = $rows[1] ?? null;
            } else {
                $cycleKey = "card_cycle_{$userId}";
                $seenRow  = FaoximaDb::fetchOne("SELECT ValuePay FROM PaySetting WHERE NamePay = ? LIMIT 1", [$cycleKey]);
                $seenStr  = is_array($seenRow) ? ($seenRow['ValuePay'] ?? '') : '';
                $seen     = array_values(array_filter(array_map('intval', explode(',', $seenStr))));
                $excl     = !empty($seen) ? "AND cn.id NOT IN (" . implode(',', $seen) . ")" : "";

                $stmt = $pdo->prepare("SELECT cardnumber, namecard, id FROM card_number cn WHERE {$accessSql} {$excl} ORDER BY RAND() LIMIT 1");
                $stmt->execute([$userId, $userId]);
                $card = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if (!$card) {
                    $stmt = $pdo->prepare("SELECT cardnumber, namecard, id FROM card_number cn WHERE {$accessSql} ORDER BY RAND() LIMIT 1");
                    $stmt->execute([$userId, $userId]);
                    $card = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    $seen = [];
                }

                if ($card) {
                    $newSeen  = implode(',', array_unique(array_merge($seen, [intval($card['id'])])));
                    $updStmt  = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?");
                    $updStmt->execute([$newSeen, $cycleKey]);
                    if ($updStmt->rowCount() === 0) {
                        $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)")->execute([$cycleKey, $newSeen]);
                    }
                }
            }
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('card_number fetch failed in CardPhotoUpload', ['err' => $e->getMessage()]);
        }
        if (!is_array($card) || empty($card['cardnumber'])) {
            FaoximaResponse::fail(503, '❌ کارت بانکی فعالی برای کارت‌به‌کارت تنظیم نشده است.');
        }

        FaoximaResponse::ok([
            'order_id'      => $orderId,
            'card_number'   => (string)$card['cardnumber'],
            'name_card'     => (string)$card['namecard'],
            'card_number_2' => is_array($card2) ? (string)$card2['cardnumber'] : null,
            'name_card_2'   => is_array($card2) ? (string)$card2['namecard'] : null,
            'display_mode'  => $dMode ?? 'random',
            'amount'        => (int)($payment['price'] ?? 0),
            'amount_rial'   => (int)($payment['price'] ?? 0) * 10,
            'message'       => '✅ عکس کارت ثبت شد. حالا مبلغ را واریز کنید و رسید را آپلود نمایید.',
        ]);
    }

    private function uploadPhotoGetFileId(string $apiKey, $userId, string $localPath, string $mime): ?string
    {
        $ch = curl_init('https://api.telegram.org/bot' . $apiKey . '/sendPhoto');
        FaoximaTelegramTransport::applyProxy($ch);
        $caption = '🪪 عکس کارت ارسال‌شده از مینی‌اپ (ذخیره file_id)';
        if (function_exists('applyPremiumEmojiTransform')) {
            $caption = applyPremiumEmojiTransform($caption, 'HTML');
        }
        $post = [
            'chat_id'    => (string)$userId,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
            'photo'      => new CURLFile($localPath, $mime, 'card.jpg'),
        ];
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            FaoximaLogger::warn('CardPhotoUpload sendPhoto failed', ['http' => $httpCode, 'curl_err' => $curlErr]);
            return null;
        }
        $tg = json_decode((string)$response, true);
        if (!is_array($tg) || empty($tg['ok'])) {
            FaoximaLogger::warn('CardPhotoUpload sendPhoto rejected', ['desc' => $tg['description'] ?? '']);
            return null;
        }
        $sizes = $tg['result']['photo'] ?? [];
        if (!is_array($sizes) || empty($sizes)) return null;
        $best = end($sizes);
        return is_array($best) ? (string)($best['file_id'] ?? '') : null;
    }
}
