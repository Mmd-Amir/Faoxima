<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use PDO;
use Throwable;

final class CardSelectHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $orderId  = \Faoxima\Api\Runtime\Input::string($this->data, 'order_id');
        $last4    = \Faoxima\Api\Runtime\Input::string($this->data, 'last4');

        if ($orderId === '') {
            \Faoxima\Api\Runtime\Response::badRequest('order_id is required');
        }
        if (!preg_match('/^\d{4}$/', $last4)) {
            \Faoxima\Api\Runtime\Response::badRequest('last4 must be exactly 4 digits');
        }

        $vcRow = \Faoxima\Api\Runtime\Db::fetchOne(
            'SELECT id FROM verified_cards WHERE user_id = ? AND last4 = ? LIMIT 1',
            [(string)$this->user['id'], $last4]
        );
        if ($vcRow === null) {
            \Faoxima\Api\Runtime\Response::fail(403, '❌ این کارت در لیست کارت‌های تاییدشده شما وجود ندارد.');
        }

        $payment = \Faoxima\Api\Runtime\Db::fetchOne(
            "SELECT * FROM Payment_report WHERE id_order = :o AND id_user = :u AND payment_Status = 'Unpaid' LIMIT 1",
            [':o' => $orderId, ':u' => $this->user['id']]
        );
        if ($payment === null) {
            \Faoxima\Api\Runtime\Response::notFound('Payment record not found or already processed');
        }

        try {
            $pdo  = \Faoxima\Api\Runtime\Db::pdo();
            $stmt = $pdo->prepare(
                "UPDATE Payment_report SET card_last4 = :l4 WHERE id_order = :o AND id_user = :u AND payment_Status = 'Unpaid'"
            );
            $stmt->execute([':l4' => $last4, ':o' => $orderId, ':u' => $this->user['id']]);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::warn('CardSelectHandler update failed', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(500, '❌ خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.');
        }

        $s = is_array($this->setting) ? $this->setting : [];
        global $APIKEY;
        $apiKey = is_string($APIKEY ?? null) ? $APIKEY : '';
        if ($apiKey === '') {
            $rowKey = \Faoxima\Core\Database\Db::select('setting', 'token_bot', null, null, 'select');
            $apiKey = is_array($rowKey) ? (string)($rowKey['token_bot'] ?? '') : '';
        }
        if ($apiKey !== '') {
            try {
                $uname    = (string)($this->user['username'] ?? '');
                $uid      = (string)$this->user['id'];
                $note     = "ℹ️ کاربر " . ($uname ? "@{$uname}" : "<code>{$uid}</code>") . " از کارت تاییدشده **** **** **** {$last4} استفاده کرده است.\n<blockquote>🛒 کد پیگیری: {$orderId}</blockquote>\nلطفاً ۴ رقم آخر رسید را بررسی کنید.";
                $receiptRoute = method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'rxReceiptDeliveryRoute')
                    ? \Faoxima\Services\Delivery\ServiceDelivery::rxReceiptDeliveryRoute()
                    : ['topic_enabled' => true, 'chat_id' => trim((string)($s['Channel_Report'] ?? '')), 'thread_id' => null];
                $topicReportingEnabled = !empty($receiptRoute['topic_enabled']);
                $groupId = $topicReportingEnabled ? trim((string)($receiptRoute['chat_id'] ?? '')) : '';
                $topicId = isset($receiptRoute['thread_id']) ? (int)$receiptRoute['thread_id'] : 0;
                if ($topicReportingEnabled && $groupId !== '') {
                    $payload = ['chat_id' => $groupId, 'text' => $note, 'parse_mode' => 'HTML'];
                    if ($topicId > 0) {
                        $payload['message_thread_id'] = $topicId;
                    }
                    telegram('sendmessage', $payload, $apiKey);
                } elseif (!$topicReportingEnabled) {
                    $adminRows = \Faoxima\Api\Runtime\Db::fetchAll('SELECT id_admin FROM admin');
                    $adminIds  = array_column($adminRows ?: [], 'id_admin');
                    foreach ($adminIds as $adminId) {
                        telegram('sendmessage', ['chat_id' => (string)$adminId, 'text' => $note, 'parse_mode' => 'HTML'], $apiKey);
                    }
                }
            } catch (Throwable $e) {
                \Faoxima\Api\Runtime\Logger::userFacing('CardSelectHandler notify failed', ['err' => $e->getMessage()]);
            }
        }

        $card = null;
        try {
            $userId = (string)$this->user['id'];

            $stmt = \Faoxima\Api\Runtime\Db::pdo()->prepare("
                SELECT cn.cardnumber, cn.namecard
                FROM card_number cn
                WHERE (
                    (cn.is_active = 1 AND (
                        NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id)
                        OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?)
                    ))
                    OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?))
                )
                AND NOT EXISTS (SELECT 1 FROM user_card_block ucb WHERE ucb.card_id = cn.id AND ucb.user_id = ?)
                ORDER BY RAND()
                LIMIT 1
            ");
            $stmt->execute([$userId, $userId, $userId]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($card)) {
                $card = null;
            }
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('card_number fetch failed in CardSelect', ['err' => $e->getMessage()]);
        }
        if (!is_array($card) || empty($card['cardnumber'])) {
            \Faoxima\Api\Runtime\Response::fail(503, '❌ کارت بانکی فعالی برای کارت‌به‌کارت تنظیم نشده است.');
        }

        $sbvView = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_public_view') ? \Faoxima\Payments\Receipts\ReceiptOrders::publicView(\Faoxima\Api\Runtime\Db::pdo(), $orderId) : null;
        \Faoxima\Api\Runtime\Response::ok([
            'order_id'    => $orderId,
            'card_number' => (string)$card['cardnumber'],
            'name_card'   => (string)$card['namecard'],
            'amount'      => (int)($payment['price'] ?? 0),
            'amount_rial' => $sbvView !== null ? (int) $sbvView['payable_rial'] : (int)($payment['price'] ?? 0) * 10,
            'verify'      => $sbvView,
            'auto_confirm' => $sbvView !== null,
            'used_last4'  => $last4,
            'message'     => $sbvView !== null ? '✅ کارت تاییدشده انتخاب شد. دقیقاً مبلغ قابل پرداخت را واریز کنید؛ پرداخت به‌صورت خودکار بررسی می‌شود.' : '✅ کارت تاییدشده انتخاب شد. مبلغ را واریز کنید و رسید را آپلود نمایید.',
        ]);
    }
}
