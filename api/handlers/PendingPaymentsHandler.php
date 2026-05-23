<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class PendingPaymentsHandler extends BaseHandler
{

    public function handle(): void
    {
        $this->requireMethod('GET');

        $userId = (string)($this->user['id'] ?? '');
        if ($userId === '') {
            FaoximaResponse::ok(['pending' => []]);
        }

        try {
            $rows = FaoximaDb::fetchAll(
                "SELECT id, id_order, time, price, payment_Status, Payment_Method,
                        dec_not_confirmed, crypto_currency, crypto_iranian_mode
                   FROM Payment_report
                  WHERE id_user = :u
                    AND payment_Status IN ('Unpaid','waiting','AwaitingHash','pending')
                    AND Payment_Method IN ('plisio','nowpayment','digitaltron','arze digital offline','cart to cart','carttocart_pv')
                    AND source = 'miniapp'
                  ORDER BY id DESC
                  LIMIT 8",
                [':u' => $userId]
            );
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('PendingPayments fetch failed', ['err' => $e->getMessage()]);
            FaoximaResponse::ok(['pending' => []]);
        }

        if (!is_array($rows) || empty($rows)) {
            FaoximaResponse::ok(['pending' => []]);
        }

        $now = time();
        $pending = [];
        foreach ($rows as $r) {
            $createdAt = $this->parseLegacyTime((string)($r['time'] ?? ''));
            if ($createdAt === null) continue;

            $method = (string)$r['Payment_Method'];
            $windowSec = $this->methodWindow($method, (int)($r['crypto_iranian_mode'] ?? 0) === 1);
            $expiresAt = $createdAt + $windowSec;
            if ($expiresAt < $now) continue;

            $pending[] = [
                'order_id'      => (string)$r['id_order'],
                'method'        => $method,
                'amount'        => (int)$r['price'],
                'status'        => (string)$r['payment_Status'],
                'created_at'    => $createdAt,
                'expires_at'    => $expiresAt,
                'remaining_sec' => max(0, $expiresAt - $now),
                'currency_code' => trim((string)($r['crypto_currency'] ?? '')) ?: null,
                'iranian_mode'  => (int)($r['crypto_iranian_mode'] ?? 0) === 1,
            ];
        }

        FaoximaResponse::ok(['pending' => $pending]);
    }


    private function methodWindow(string $method, bool $iranian): int
    {
        $m = strtolower(trim($method));
        if (in_array($m, ['plisio', 'nowpayment', 'digitaltron'], true)) {
            return 600;
        }
        if (in_array($m, ['cart to cart', 'carttocart_pv'], true)) {
            return 1800;
        }
        if ($m === 'arze digital offline') {
            return $iranian ? 7200 : 86400;
        }
        return 1800;
    }


    private function parseLegacyTime(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (ctype_digit($raw)) return (int)$raw;

        $raw = strtr($raw, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);

        $ts = strtotime(str_replace('/', '-', $raw));
        return $ts === false ? null : $ts;
    }
}
