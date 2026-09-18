<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class WalletTransferHandler extends BaseHandler
{

    public ?string $mode = null;

    public function handle(): void
    {
        $mode = $this->mode ?? FaoximaInput::string($this->data, '_mode');
        if ($mode === 'quote') {
            $this->requireMethod('POST');
            $this->handleQuote();
            return;
        }
        if ($mode === 'confirm') {
            $this->requireMethod('POST');
            $this->handleConfirm();
            return;
        }
        FaoximaResponse::badRequest('Unknown mode for wallet_transfer');
    }

    private const MIN_TRANSFER_AMOUNT = 1000;

    private function minAmount(): int
    {
        return self::MIN_TRANSFER_AMOUNT;
    }

    private function resolveRecipient(): ?array
    {
        $recipientId = FaoximaInput::string($this->data, 'recipient_id');
        if ($recipientId === '' || !ctype_digit($recipientId)) {
            return null;
        }
        if ($recipientId === (string)($this->user['id'] ?? '')) {
            return null;
        }
        $recipient = select('user', '*', 'id', $recipientId, 'select');
        return is_array($recipient) && !empty($recipient) ? $recipient : null;
    }

    private function handleQuote(): void
    {
        $recipient = $this->resolveRecipient();
        if ($recipient === null) {
            FaoximaResponse::badRequest('کاربر مقصد نامعتبر است');
        }

        $amount = FaoximaInput::int($this->data, 'amount', 0);
        $minAmount = $this->minAmount();

        FaoximaResponse::ok([
            'recipient_id'       => (string)$recipient['id'],
            'recipient_username' => $recipient['username'] !== null && $recipient['username'] !== ''
                ? '@' . $recipient['username']
                : null,
            'min_amount'         => $minAmount,
            'amount'             => $amount,
            'balance'            => (float)($this->user['Balance'] ?? 0),
        ]);
    }

    private function handleConfirm(): void
    {
        $recipient = $this->resolveRecipient();
        if ($recipient === null) {
            FaoximaResponse::badRequest('کاربر مقصد نامعتبر است');
        }

        $amount = FaoximaInput::int($this->data, 'amount', 0);
        $minAmount = $this->minAmount();
        if ($amount < $minAmount) {
            FaoximaResponse::badRequest('مبلغ باید حداقل ' . number_format($minAmount) . ' تومان باشد');
        }

        if ((float)($this->user['Balance'] ?? 0) < $amount) {
            FaoximaResponse::fail(422, 'موجودی کیف پول شما کافی نیست');
        }

        $senderId = (string)$this->user['id'];
        $recipientId = (string)$recipient['id'];

        $charge = balance_atomic_charge($senderId, $amount, 0);
        if (empty($charge['ok'])) {
            FaoximaResponse::fail(422, 'موجودی کیف پول شما کافی نیست');
        }

        wallet_ledger_record($senderId, 'debit', $amount, 'transfer_out', 'انتقال موجودی به کاربر ' . $recipientId, null, 'user', $recipientId);

        $credited = balance_atomic_credit($recipientId, $amount);
        if (!$credited) {
            $refunded = balance_atomic_credit($senderId, $amount);
            if ($refunded) {
                wallet_ledger_record($senderId, 'credit', $amount, 'refund', 'بازگشت وجه انتقال ناموفق', null, 'user', $recipientId);
            }
            FaoximaLogger::critical('WalletTransfer credit failed, refunded sender', [
                'sender' => $senderId,
                'recipient' => $recipientId,
                'amount' => $amount,
            ]);
            FaoximaResponse::fail(500, 'خطا در انتقال موجودی. مبلغ به کیف پول شما بازگشت داده شد');
        }

        wallet_ledger_record($recipientId, 'credit', $amount, 'transfer_in', 'انتقال موجودی از کاربر ' . $senderId, null, 'user', $senderId);

        if (function_exists('sendmessage')) {
            try {
                $senderUsername = trim((string)($this->user['username'] ?? ''));
                $senderLines = $senderUsername !== '' ? "نام کاربری: @{$senderUsername}\n" : '';
                $senderLines .= 'آیدی عددی: <code>' . $senderId . '</code>';
                $notifyText = sprintf("مبلغ %s تومان به کیف پول شما واریز شد.\n\n<blockquote>%s</blockquote>", number_format($amount), $senderLines);
                sendmessage($recipientId, $notifyText, null, 'HTML');
            } catch (Throwable $e) {
            }
        }

        FaoximaResponse::ok([
            'new_balance' => (float)($charge['new_balance'] ?? 0),
        ]);
    }
}
