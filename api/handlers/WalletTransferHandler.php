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

    private function operationId(): string
    {
        foreach (['operation_id', 'op_id', 'idempotency_key', '_op'] as $key) {
            $value = FaoximaInput::string($this->data, $key);
            if ($value !== '') {
                if (!preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $value)) {
                    FaoximaResponse::badRequest('شناسه عملیات نامعتبر است');
                }
                return $value;
            }
        }
        return bin2hex(random_bytes(16));
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

        $senderId = (string)$this->user['id'];
        $recipientId = (string)$recipient['id'];
        if ($senderId === $recipientId) {
            FaoximaResponse::badRequest('کاربر مقصد نامعتبر است');
        }
        $operationId = $this->operationId();
        $transfer = rx_wallet_transfer_atomic($senderId, $recipientId, $amount, $operationId);
        if (empty($transfer['ok'])) {
            $this->transferFailure((string)($transfer['reason'] ?? ''), $operationId);
        }
        $newBalance = (float)$transfer['new_balance'];
        if (!empty($transfer['replayed'])) {
            FaoximaResponse::ok(['new_balance' => $newBalance, 'replayed' => true, 'operation_id' => $operationId]);
        }
        rx_wallet_transfer_report($transfer['sender'], $transfer['recipient'], $amount, $operationId, $newBalance, (float)$transfer['recipient_balance']);
        $this->notifyRecipient($recipientId, $senderId, $amount);

        FaoximaResponse::ok([
            'new_balance' => $newBalance,
            'operation_id' => $operationId,
        ]);
    }

    private function transferFailure(string $reason, string $operationId): void
    {
        if ($reason === 'insufficient_balance') {
            FaoximaResponse::fail(422, 'موجودی کیف پول شما کافی نیست');
        }
        if ($reason === 'operation_conflict') {
            FaoximaResponse::fail(409, 'شناسه این انتقال قبلاً با مبلغ یا گیرنده دیگری استفاده شده است.');
        }
        FaoximaResponse::fail(500, 'انتقال تکمیل نشد؛ لطفاً با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $operationId]);
    }

    private function notifyRecipient(string $recipientId, string $senderId, int $amount): void
    {
        if (function_exists('sendmessage')) {
            try {
                $senderUsername = trim((string)($this->user['username'] ?? ''));
                $senderLines = $senderUsername !== '' ? "نام کاربری: @{$senderUsername}\n" : '';
                $senderLines .= 'آیدی عددی: <code>' . $senderId . '</code>';
                $notifyText = sprintf("مبلغ %s تومان به کیف پول شما واریز شد.\n\n<blockquote>%s</blockquote>", number_format($amount), $senderLines);
                sendmessage($recipientId, $notifyText, null, 'HTML');
            } catch (Throwable $e) {
                error_log('[miniapp:wallet-transfer] notification failed: ' . get_class($e));
            }
        }
    }

}
