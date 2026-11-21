<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use Throwable;

final class GiftCodeHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $code = \Faoxima\Api\Runtime\Input::string($this->data, 'code');
        if ($code === '') {
            \Faoxima\Api\Runtime\Response::badRequest('code is required');
        }

        $result = \Faoxima\Orders\Discounts\DiscountRules::redeemGift(\Faoxima\Api\Runtime\Db::pdo(), $code, $this->user);
        if (empty($result['ok'])) {
            \Faoxima\Api\Runtime\Response::fail(422, (string)($result['reason'] ?? '❌ کد هدیه نامعتبر است.'));
        }

        $this->reportGift($code, (int)$result['amount']);

        \Faoxima\Api\Runtime\Response::ok([
            'amount'      => (int)$result['amount'],
            'new_balance' => (float)$result['new_balance'],
            'message'     => '🎁 کد هدیه با موفقیت اعمال شد. مبلغ ' . number_format((int)$result['amount']) . ' تومان به کیف پول شما اضافه شد.',
        ]);
    }

    private function reportGift(string $code, int $amount): void
    {
        $channel = (string)($this->setting['Channel_Report'] ?? '');
        if ($channel === '') return;
        $otherRow = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'otherservice', 'select');
        $topic = is_array($otherRow) ? (string)($otherRow['idreport'] ?? '') : '';
        $uid = (string)($this->user['id'] ?? '');
        $text = "🎁 استفاده از کد هدیه\n"
              . "<blockquote>آیدی عددی: {$uid}</blockquote>\n"
              . "<blockquote>کد: {$code}</blockquote>\n"
              . "<blockquote>مبلغ: " . number_format($amount) . " تومان</blockquote>";
        try {
            telegram('sendmessage', [
                'chat_id'           => $channel,
                'message_thread_id' => $topic,
                'text'              => $text,
                'parse_mode'        => 'HTML',
            ]);
        } catch (Throwable $e) {
        }
    }
}
