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
        $receiptMarker = trim((string)($payment['dec_not_confirmed'] ?? ''));
        if ($currentStatus === 'paid') {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_receipt_already_confirmed', '✅ این پرداخت قبلاً تأیید شده است.'));
        }
        if (in_array($currentStatus, ['waiting', 'pending'], true) && $receiptMarker !== '') {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_receipt_already_pending_review', '⏳ رسید این پرداخت قبلاً ارسال شده و در انتظار بررسی ادمین است.'));
        }
        if ($currentStatus !== 'unpaid') {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_receipt_payment_not_pending', '❌ این تراکنش دیگر در وضعیت قابل ارسال رسید نیست.'));
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
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_receipt_no_admin_configured', '❌ هیچ ادمینی روی سرور تنظیم نشده است.'));
        }

        $settingRow = FaoximaDb::fetchOne('SELECT Channel_Report FROM setting LIMIT 1');
        $reportGroupId = is_array($settingRow) ? trim((string)($settingRow['Channel_Report'] ?? '')) : '';
        $reportThreadId = null;
        if ($reportGroupId !== '' && $reportGroupId !== '0') {
            $topicRow = FaoximaDb::fetchOne("SELECT idreport FROM topicid WHERE report = 'receiptreport' LIMIT 1");
            $threadCandidate = $topicRow ? (int)($topicRow['idreport'] ?? 0) : 0;
            $reportThreadId = $threadCandidate > 0 ? $threadCandidate : null;
        } else {
            $reportGroupId = '';
        }


        global $APIKEY;
        $apiKey = is_string($APIKEY ?? null) ? $APIKEY : '';
        if ($apiKey === '') {
            $rowKey = select('setting', 'token_bot', null, null, 'select');
            $apiKey = is_array($rowKey) ? (string)($rowKey['token_bot'] ?? '') : '';
        }
        if ($apiKey === '') {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_receipt_bot_token_missing', '❌ توکن ربات روی سرور تنظیم نشده است.'));
        }

        $userId   = (string)$this->user['id'];
        $userName = (string)($this->user['username'] ?? '');
        $name     = trim((string)($this->user['first_name'] ?? '') . ' ' . (string)($this->user['last_name'] ?? ''));
        $balance  = (int)($this->user['Balance'] ?? 0);
        $amount   = (int)($payment['price'] ?? 0);
        $method   = (string)($payment['Payment_Method'] ?? 'cart to cart');


        $captionTemplate = faoxima_textbot_get('dyn_receipt_admin_caption_tpl', "💳 رسید پرداخت کارت‌به‌کارت\n\n🆔 کد پیگیری: <code>{order_id}</code>\n💰 مبلغ: {amount} تومان\n👤 کاربر: {user_link}\n🪪 شناسه عددی: <code>{user_id}</code>\n💎 موجودی فعلی: {balance} تومان\n📌 روش: {method}");
        $userLink = "<a href=\"tg://user?id={$userId}\">" .
            htmlspecialchars($name !== '' ? $name : $userId, ENT_QUOTES) .
            "</a>" . ($userName !== '' ? ' (@' . htmlspecialchars($userName, ENT_QUOTES) . ')' : '');
        $caption = faoxima_render_text($captionTemplate, [
            'order_id' => htmlspecialchars($orderId, ENT_QUOTES),
            'amount'   => number_format($amount),
            'user_link' => $userLink,
            'user_id'  => $userId,
            'balance'  => number_format($balance),
            'method'   => htmlspecialchars($method, ENT_QUOTES),
        ]);


        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => faoxima_textbot_get('jsontext.users.Balance.Confirmpaying', '✅ تأیید'), 'callback_data' => 'Confirm_pay_' . $orderId],
                    ['text' => faoxima_textbot_get('jsontext.users.Balance.reject_pay', '❌ رد'),    'callback_data' => 'reject_pay_'  . $orderId],
                ],
                [
                    ['text' => faoxima_textbot_get('jsontext.users.Balance.addbalamceuser', '➕ افزایش موجودی'),     'callback_data' => 'addbalamceuser_' . $orderId],
                    ['text' => faoxima_textbot_get('jsontext.users.Balance.blockedfake', '🚫 مسدود (جعلی)'), 'callback_data' => 'blockuserfake_' . $userId],
                ],
                [
                    ['text' => faoxima_textbot_get('dyn_receipt_view_user_btn', '👁 مشاهده کاربر'), 'callback_data' => 'manageuser_' . $userId],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);


        $reqCardTmp  = null;
        $reqCardMime = 'image/jpeg';
        $reqLast4    = '';

        if (isset($_FILES['card_photo']) && is_array($_FILES['card_photo'])) {
            $cf = $_FILES['card_photo'];
            if ((int)($cf['error'] ?? 99) === UPLOAD_ERR_OK
                && (int)($cf['size'] ?? 0) > 0
                && (int)($cf['size'] ?? 0) <= 8 * 1024 * 1024
            ) {
                $cfp = (string)($cf['tmp_name'] ?? '');
                if ($cfp !== '' && is_readable($cfp)) {
                    $reqCardTmp  = $cfp;
                    $reqCardMime = (string)($cf['type'] ?? 'image/jpeg');
                }
            }
        }
        $rawL4 = trim((string)(isset($_POST['card_last4']) ? $_POST['card_last4'] : ''));
        if (preg_match('/^\d{4}$/', $rawL4)) $reqLast4 = $rawL4;

        $receiptSentSuccessfully = false;
        $fileId = null;

        $targets = $reportGroupId !== '' ? [$reportGroupId] : $adminIds;
        $targetThreadId = $reportGroupId !== '' ? $reportThreadId : null;

        $reportMessageId = null;

        if ($reqCardTmp !== null) {
            $receiptFileId     = null;
            $newCardPhotoFileId = '';
            $remainingAdmins   = $targets;
            $failedAdmins      = [];

            while (!empty($remainingAdmins)) {
                $candidate   = array_shift($remainingAdmins);
                $albumResult = $this->sendAlbumBothLocal(
                    $apiKey, $candidate,
                    $reqCardTmp, $reqCardMime,
                    $tmp, (string)($f['type'] ?? 'image/jpeg'),
                    $caption, $keyboard, $targetThreadId
                );
                if (is_array($albumResult)) {
                    $receiptFileId      = $albumResult['receipt_file_id'];
                    $newCardPhotoFileId = $albumResult['card_file_id'];
                    if ($candidate === $reportGroupId) {
                        $reportMessageId = $albumResult['receipt_message_id'];
                    }
                    break;
                }
                $failedAdmins[] = $candidate;
            }

            if ($receiptFileId !== null && $newCardPhotoFileId !== '') {
                try {
                    $pdo = FaoximaDb::pdo();
                    if ($reqLast4 !== '') {
                        $pdo->prepare("UPDATE Payment_report SET card_photo_file_id = ?, card_last4 = ? WHERE id_order = ? AND id_user = ?")
                            ->execute([$newCardPhotoFileId, $reqLast4, $orderId, $this->user['id']]);
                    } else {
                        $pdo->prepare("UPDATE Payment_report SET card_photo_file_id = ? WHERE id_order = ? AND id_user = ?")
                            ->execute([$newCardPhotoFileId, $orderId, $this->user['id']]);
                    }
                } catch (Throwable $e) {
                    FaoximaLogger::warn('card_photo_file_id save in receipt failed', ['err' => $e->getMessage()]);
                }

                foreach (array_merge($failedAdmins, $remainingAdmins) as $adminId) {
                    $albumMsgId = $this->sendCardAlbumToSingleAdmin($apiKey, $adminId, $newCardPhotoFileId, $receiptFileId, $caption, $keyboard, $targetThreadId);
                    if ($albumMsgId !== null) {
                        if ($adminId === $reportGroupId) { $reportMessageId = $albumMsgId; }
                    } else {
                        $txtMsgId = $this->sendReceiptText($apiKey, $adminId, $caption, $keyboard, $targetThreadId);
                        if ($txtMsgId !== null && $adminId === $reportGroupId) { $reportMessageId = $txtMsgId; }
                    }
                }
                $receiptSentSuccessfully = true;
                $fileId = $receiptFileId;
            } else {
                $textOk = false;
                foreach ($failedAdmins as $adminId) {
                    $txtMsgId = $this->sendReceiptText($apiKey, $adminId, $caption, $keyboard, $targetThreadId);
                    if ($txtMsgId !== null) {
                        $textOk = true;
                        if ($adminId === $reportGroupId) { $reportMessageId = $txtMsgId; }
                    }
                }
                if (!$textOk) {
                    FaoximaLogger::warn('Receipt+card album: all admins unreachable', ['admins' => $failedAdmins]);
                    FaoximaResponse::fail(502, faoxima_textbot_get('dyn_receipt_admin_delivery_failed', '❌ ارسال رسید به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.'));
                }
                $receiptSentSuccessfully = true;
            }
        } else {
            if ($reqLast4 !== '') {
                try {
                    FaoximaDb::pdo()->prepare("UPDATE Payment_report SET card_last4 = ? WHERE id_order = ? AND id_user = ?")
                        ->execute([$reqLast4, $orderId, $this->user['id']]);
                } catch (Throwable $e) {
                    FaoximaLogger::warn('card_last4 save in receipt failed', ['err' => $e->getMessage()]);
                }
            }

            $cardPhotoFileId = (string)($payment['card_photo_file_id'] ?? '');
            $useAlbum = $cardPhotoFileId !== '';

            $receiptFileId     = null;
            $firstSuccessAdmin = null;
            $failedAdmins      = [];
            $remainingAdmins   = $targets;
            while (!empty($remainingAdmins)) {
                $candidate   = array_shift($remainingAdmins);
                $photoResult = $this->sendReceiptPhoto($apiKey, $candidate, $tmp, (string)($f['type'] ?? 'image/jpeg'), $caption, $useAlbum ? '' : $keyboard, $targetThreadId);
                if (is_array($photoResult) && $photoResult['file_id'] !== '') {
                    $receiptFileId     = $photoResult['file_id'];
                    $firstSuccessAdmin = $candidate;
                    if (!$useAlbum && $candidate === $reportGroupId) {
                        $reportMessageId = $photoResult['message_id'];
                    }
                    break;
                }
                $failedAdmins[] = $candidate;
            }

            if ($receiptFileId === null) {
                $textOk = false;
                foreach ($failedAdmins as $adminId) {
                    $txtMsgId = $this->sendReceiptText($apiKey, $adminId, $caption, $keyboard, $targetThreadId);
                    if ($txtMsgId !== null) {
                        $textOk = true;
                        if ($adminId === $reportGroupId) { $reportMessageId = $txtMsgId; }
                    }
                }
                if (!$textOk) {
                    FaoximaLogger::warn('Receipt: all admins unreachable', ['admins' => $failedAdmins]);
                    FaoximaResponse::fail(502, faoxima_textbot_get('dyn_receipt_admin_delivery_failed', '❌ ارسال رسید به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.'));
                } else {
                    $receiptSentSuccessfully = true;
                }
            } else {
                $receiptSentSuccessfully = true;
                if ($useAlbum) {
                    $albumMsgId = $this->sendCardAlbumToSingleAdmin($apiKey, $firstSuccessAdmin, $cardPhotoFileId, $receiptFileId, $caption, $keyboard, $targetThreadId);
                    if ($albumMsgId !== null && $firstSuccessAdmin === $reportGroupId) { $reportMessageId = $albumMsgId; }
                }
                $otherAdmins = array_merge($failedAdmins, $remainingAdmins);
                foreach ($otherAdmins as $adminId) {
                    if ($useAlbum) {
                        $altMsgId = $this->sendCardAlbumToSingleAdmin($apiKey, $adminId, $cardPhotoFileId, $receiptFileId, $caption, $keyboard, $targetThreadId)
                            ?? $this->sendReceiptText($apiKey, $adminId, $caption, $keyboard, $targetThreadId);
                    } else {
                        $altMsgId = $this->forwardReceiptByFileId($apiKey, $adminId, $receiptFileId, $caption, $keyboard, $targetThreadId)
                            ?? $this->sendReceiptText($apiKey, $adminId, $caption, $keyboard, $targetThreadId);
                    }
                    if ($altMsgId !== null && $adminId === $reportGroupId) { $reportMessageId = $altMsgId; }
                }
            }
            $fileId = $receiptFileId;
        }

        $fileId = $receiptFileId;

        if ($reportMessageId !== null && $reportMessageId > 0 && $reportGroupId !== '') {
            try {
                FaoximaDb::pdo()->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ?, report_thread_id = ? WHERE id_order = ? AND id_user = ?")
                    ->execute([$reportGroupId, $reportMessageId, $targetThreadId, $orderId, $this->user['id']]);
            } catch (Throwable $e) {
                FaoximaLogger::warn('report_message_id save failed', ['err' => $e->getMessage()]);
            }
        }


        $statusUpdated = false;
        if ($receiptSentSuccessfully) {
            try {
                $pdo = FaoximaDb::pdo();
                $stmt = $pdo->prepare(
                    'UPDATE Payment_report
                        SET payment_Status = :s, dec_not_confirmed = :d, at_updated = :au
                      WHERE id_order = :o AND id_user = :u AND source = \'miniapp\' AND payment_Status = \'Unpaid\''
                );
                $stmt->execute([
                    ':s' => 'waiting',
                    ':d' => 'receipt-submitted',
                    ':au' => date('Y/m/d H:i:s'),
                    ':o' => $orderId,
                    ':u' => $this->user['id'],
                ]);
                $statusUpdated = $stmt->rowCount() === 1;
            } catch (Throwable $e) {
                FaoximaLogger::error('Payment_report status update failed', ['err' => $e->getMessage(), 'order' => $orderId, 'user_id' => $this->user['id']]);
            }
        }

        if (!$statusUpdated) {
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_receipt_status_update_failed', '❌ رسید برای ادمین ارسال شد اما ثبت وضعیت آن ناموفق بود. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.'));
        }

        FaoximaLogger::debug('Receipt uploaded', [
            'order'    => $orderId,
            'user_id'  => $this->user['id'],
            'amount'   => $amount,
            'admins'   => count($adminIds),
            'photo_ok' => $fileId !== null,
        ]);

        FaoximaResponse::ok([
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_receipt_submitted_success', '✅ رسید شما برای ادمین ارسال شد. پس از تأیید، حساب شما شارژ می‌شود.'),
        ]);
    }


    private function sendReceiptPhoto(string $apiKey, string $chatId, string $localPath, string $mime, string $caption, string $keyboardJson, ?int $threadId = null): ?array
    {
        $payload = [
            'chat_id'    => $chatId,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
            'photo'      => new CURLFile($localPath, $mime, 'receipt.jpg'),
        ];
        if ($threadId !== null && $threadId > 0) { $payload['message_thread_id'] = $threadId; }
        if ($keyboardJson !== '') { $payload['reply_markup'] = $keyboardJson; }
        $tg = telegram('sendphoto', $payload, $apiKey);
        if (!is_array($tg) || empty($tg['ok'])) {
            $desc = is_array($tg) ? (string)($tg['description'] ?? '') : '';
            FaoximaLogger::warn('sendPhoto rejected', ['desc' => $desc, 'admin' => $chatId]);
            return null;
        }

        $sizes = $tg['result']['photo'] ?? [];
        if (!is_array($sizes) || empty($sizes)) return null;
        $best = end($sizes);
        $fileId = is_array($best) ? (string)($best['file_id'] ?? '') : '';
        if ($fileId === '') return null;
        return ['file_id' => $fileId, 'message_id' => (int)($tg['result']['message_id'] ?? 0)];
    }


    private function forwardReceiptByFileId(string $apiKey, string $chatId, string $fileId, string $caption, string $keyboardJson, ?int $threadId = null): ?int
    {
        if ($fileId === '') return null;
        $payload = [
            'chat_id'    => $chatId,
            'photo'      => $fileId,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ];
        if ($threadId !== null && $threadId > 0) { $payload['message_thread_id'] = $threadId; }
        if ($keyboardJson !== '') { $payload['reply_markup'] = $keyboardJson; }
        $tg = telegram('sendphoto', $payload, $apiKey);
        if (!is_array($tg) || empty($tg['ok'])) return null;
        return (int)($tg['result']['message_id'] ?? 0);
    }


    private function sendReceiptText(string $apiKey, string $chatId, string $caption, string $keyboardJson, ?int $threadId = null): ?int
    {
        $payload = [
            'chat_id'      => $chatId,
            'text'         => $caption,
            'parse_mode'   => 'HTML',
            'reply_markup' => $keyboardJson,
        ];
        if ($threadId !== null && $threadId > 0) { $payload['message_thread_id'] = $threadId; }
        $tg = telegram('sendmessage', $payload, $apiKey);
        if (!is_array($tg) || empty($tg['ok'])) return null;
        return (int)($tg['result']['message_id'] ?? 0);
    }

    private function sendCardAlbumToSingleAdmin(
        string $apiKey,
        string $chatId,
        string $cardFileId,
        string $receiptFileId,
        string $caption,
        string $keyboardJson,
        ?int $threadId = null
    ): ?int {
        if ($chatId === '' || $cardFileId === '' || $receiptFileId === '') return null;
        $this->forwardReceiptByFileId($apiKey, $chatId, $cardFileId, faoxima_textbot_get('dyn_receipt_user_card_caption', '🪪 کارت بانکی کاربر'), '', $threadId);
        return $this->forwardReceiptByFileId($apiKey, $chatId, $receiptFileId, $caption, $keyboardJson, $threadId);
    }

    private function sendAlbumBothLocal(
        string $apiKey,
        string $chatId,
        string $cardLocalPath,
        string $cardMime,
        string $receiptLocalPath,
        string $receiptMime,
        string $caption,
        string $keyboardJson,
        ?int $threadId = null
    ): ?array {
        if ($chatId === '' || !is_readable($cardLocalPath) || !is_readable($receiptLocalPath)) return null;

        $cardResult = $this->sendReceiptPhoto($apiKey, $chatId, $cardLocalPath, $cardMime, faoxima_textbot_get('dyn_receipt_user_card_caption', '🪪 کارت بانکی کاربر'), '', $threadId);
        if ($cardResult === null) return null;

        $receiptResult = $this->sendReceiptPhoto($apiKey, $chatId, $receiptLocalPath, $receiptMime, $caption, $keyboardJson, $threadId);
        if ($receiptResult === null) return null;

        return [
            'card_file_id' => $cardResult['file_id'],
            'receipt_file_id' => $receiptResult['file_id'],
            'receipt_message_id' => $receiptResult['message_id'],
        ];
    }
}

