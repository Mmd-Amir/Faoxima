<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class PendingPaymentsHandler extends BaseHandler
{
    private const MAX_AGE_SEC = 86400;

    public function handle(): void
    {
        $this->requireMethod('GET');

        $userId = (string)($this->user['id'] ?? '');
        if ($userId === '') {
            FaoximaResponse::ok(['pending' => []]);
        }

        $sbvSourceSql = (function_exists('rx_sbv_has_schema') && rx_sbv_has_schema())
            ? " OR EXISTS (SELECT 1 FROM card_sms_payment csp WHERE csp.id_order = Payment_report.id_order)"
            : '';
        try {
            $rows = FaoximaDb::fetchAll(
                "SELECT id, id_order, time, price, payment_Status, Payment_Method,
                        dec_not_confirmed, crypto_currency, crypto_tx_hash, tonpay_invoice_id, cubepay_payment_link, blupal_invoice_id, atlaspay_order_id
                   FROM Payment_report
                  WHERE id_user = :u
                    AND payment_Status IN ('Unpaid','waiting','AwaitingHash','pending','expire')
                     AND Payment_Method IN ('plisio','nowpayment','digitaltron','arze digital offline','cart to cart','carttocart_pv','iranpay2','tonpay','cubepay','blupal','variza','noapay','abangateway','atlaspay')
                    AND (source = 'miniapp'" . $sbvSourceSql . " OR (Payment_Method = 'tonpay' AND tonpay_gateway_mode = 'custom') OR (Payment_Method = 'atlaspay' AND atlaspay_gateway_mode = 'custom'))
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
        $sbvMap = [];
        if (function_exists('rx_sbv_has_schema') && rx_sbv_has_schema()) {
            $sbvIds = array_values(array_filter(array_map(static function ($r) {
                return (string) ($r['id_order'] ?? '');
            }, $rows)));
            if (!empty($sbvIds)) {
                try {
                    $sbvRows = FaoximaDb::fetchAll(
                        'SELECT id_order, expires_at, state FROM card_sms_payment WHERE id_order IN (' . implode(',', array_fill(0, count($sbvIds), '?')) . ')',
                        $sbvIds
                    );
                    foreach ((array) $sbvRows as $sr) {
                        $sbvMap[(string) $sr['id_order']] = $sr;
                    }
                } catch (Throwable $e) {
                    $sbvMap = [];
                }
            }
        }
        $sbvActive = function_exists('rx_sbv_active_payment') ? rx_sbv_active_payment($userId) : null;
        $seenMethods = [];
        $pending = [];
        $labels = [];
        try {
            $txtRows = FaoximaDb::fetchAll("SELECT id_text, text FROM textbot");
            if (is_array($txtRows)) {
                foreach ($txtRows as $tr) {
                    if (isset($tr['id_text'])) {
                        $labels[$tr['id_text']] = trim((string)($tr['text'] ?? ''));
                    }
                }
            }
        } catch (Throwable $e) {}

        $resolveMethodLabel = static function (string $method) use ($labels): string {
            $m = strtolower(trim($method));
            switch ($m) {
                case 'cart to cart':
                case 'carttocart_pv':
                case 'carttocart':
                    return !empty($labels['carttocart']) ? $labels['carttocart'] : 'کارت‌به‌کارت';
                case 'iranpay2':
                    return !empty($labels['iranpay3']) ? $labels['iranpay3'] : 'ترونادو';
                case 'tonpay':
                    return !empty($labels['tonpay']) ? $labels['tonpay'] : 'تون‌پی';
                case 'cubepay':
                    return !empty($labels['cubepay']) ? $labels['cubepay'] : 'کیوب‌پی';
                case 'blupal':
                    return !empty($labels['blupal']) ? $labels['blupal'] : 'بلوپال';
                case 'variza':
                    return !empty($labels['variza']) ? $labels['variza'] : 'واریزا';
                case 'noapay':
                    return !empty($labels['noapay']) ? $labels['noapay'] : 'NoaPay';
                case 'abangateway':
                    return !empty($labels['abangateway']) ? $labels['abangateway'] : 'آبان گیت وی';
                case 'atlaspay':
                    return !empty($labels['atlaspay']) ? $labels['atlaspay'] : 'اطلس‌پی';
                case 'zarinpal':
                    return !empty($labels['zarinpal']) ? $labels['zarinpal'] : 'زرین‌پال';
                case 'plisio':
                    return !empty($labels['textnowpayment']) ? $labels['textnowpayment'] : 'پرداخت ارزی (Plisio)';
                case 'nowpayment':
                    return !empty($labels['textsnowpayment']) ? $labels['textsnowpayment'] : 'NowPayments';
                case 'digitaltron':
                    return !empty($labels['textnowpaymenttron']) ? $labels['textnowpaymenttron'] : 'پرداخت با ترون';
                case 'arze digital offline':
                case 'crypto_offline':
                    return !empty($labels['textnowpaymenttron']) ? $labels['textnowpaymenttron'] : (!empty($labels['cryptopay']) ? $labels['cryptopay'] : 'ارز آفلاین');
                case 'star telegram':
                case 'startelegrams':
                    return !empty($labels['text_star_telegram']) ? $labels['text_star_telegram'] : 'Telegram Stars';
                case 'paymentnotverify':
                    return !empty($labels['textpaymentnotverify']) ? $labels['textpaymentnotverify'] : 'تایید پرداخت دستی';
                default:
                    return $method;
            }
        };

        foreach ($rows as $r) {
            $createdAt = $this->parseLegacyTime((string)($r['time'] ?? ''));
            if ($createdAt === null) continue;

            $method = (string)$r['Payment_Method'];
            $methodLc = strtolower($method);
            $hashVal = trim((string)($r['crypto_tx_hash'] ?? ''));
            $decVal = trim((string)($r['dec_not_confirmed'] ?? ''));

            if ($methodLc === 'arze digital offline') {
                if ($hashVal === '') continue;
            } elseif (in_array($methodLc, ['cart to cart', 'carttocart_pv'], true)) {
                if ($decVal === '' && !isset($sbvMap[(string) $r['id_order']])) continue;
            } elseif ($methodLc === 'tonpay') {
                if (trim((string)($r['tonpay_invoice_id'] ?? '')) === '') continue;
            } elseif ($methodLc === 'cubepay') {
                if (trim((string)($r['cubepay_payment_link'] ?? '')) === '') continue;
            } elseif ($methodLc === 'blupal') {
                if (trim((string)($r['blupal_invoice_id'] ?? '')) === '') continue;
            } elseif ($methodLc === 'atlaspay') {
                if (trim((string)($r['atlaspay_order_id'] ?? '')) === '') continue;
            } elseif (in_array($methodLc, ['plisio', 'nowpayment', 'digitaltron', 'iranpay2', 'variza', 'noapay', 'abangateway'], true)) {
                if ($decVal === '') continue;
            }

            if ($createdAt + self::MAX_AGE_SEC < $now && !($methodLc === 'tonpay' && (string) ($r['payment_Status'] ?? '') !== 'expire' && function_exists('tonpayCustomFetchReport') && tonpayCustomFetchReport((string) $r['id_order']) !== null)) continue;

            $status = (string)($r['payment_Status'] ?? '');
            $windowSec = $this->methodWindow($method, false);
            $expiresAt = isset($sbvMap[(string) $r['id_order']]) ? (int) $sbvMap[(string) $r['id_order']]['expires_at'] : $createdAt + $windowSec;
            if ($methodLc === 'tonpay' && $status !== 'expire' && function_exists('tonpayCustomFetchReport')) {
                $customRow = tonpayCustomFetchReport((string) $r['id_order']);
                if ($customRow !== null && (int) ($customRow['tonpay_review_deadline_at'] ?? 0) > 0) {
                    $expiresAt = (int) $customRow['tonpay_review_deadline_at'];
                }
            }
            if ($methodLc === 'atlaspay' && function_exists('atlaspayCustomFetchReport')) {
                $atlasRow = atlaspayCustomFetchReport((string) $r['id_order']);
                if ($atlasRow !== null) {
                    $atlasState = atlaspayCustomBuildState($atlasRow);
                    if ($atlasState['deadline_at'] > 0 && $atlasState['phase'] === 'awaiting_payment') {
                        $expiresAt = $atlasState['deadline_at'];
                    } elseif ($atlasState['open'] || in_array($atlasState['phase'], ['confirming', 'manual_review'], true)) {
                        $expiresAt = max($now + 60, $createdAt + 7200);
                    }
                }
            }
            if ($status !== 'expire' && $expiresAt < $now) {
                continue;
            }

            if (isset($seenMethods[$methodLc])) continue;
            $seenMethods[$methodLc] = true;

            $pending[] = [
                'order_id'      => (string)$r['id_order'],
                'method'        => $method,
                'method_label'  => $resolveMethodLabel($method),
                'amount'        => (int)$r['price'],
                'status'        => (string)$r['payment_Status'],
                'created_at'    => $createdAt,
                'expires_at'    => $expiresAt,
                'remaining_sec' => max(0, $expiresAt - $now),
                'currency_code' => trim((string)($r['crypto_currency'] ?? '')) ?: null,
                'verify'        => isset($sbvMap[(string) $r['id_order']]),
                'cancellable'   => isset($sbvMap[(string) $r['id_order']]) && $sbvActive !== null && (string) $sbvActive['id_order'] === (string) $r['id_order'] && !empty($sbvActive['cancellable']),
                'resumable'     => isset($sbvMap[(string) $r['id_order']]) && $sbvActive !== null && (string) $sbvActive['id_order'] === (string) $r['id_order'] && !empty($sbvActive['resumable']),
            ];
            if (isset($sbvMap[(string) $r['id_order']]) && $sbvActive !== null && (string) $sbvActive['id_order'] === (string) $r['id_order']) {
                $pending[count($pending) - 1]['amount'] = (int) $sbvActive['payable_toman'];
            }
        }

        FaoximaResponse::ok(['pending' => $pending]);
    }


    private function methodWindow(string $method, bool $iranian): int
    {
        if (strtolower($method) === 'cubepay') {
            return 3600;
        }
        if (strtolower($method) === 'noapay') { return 3600; }
        if (strtolower($method) === 'variza') {
            return 3600;
        }
        if (strtolower($method) === 'atlaspay') {
            return 1200;
        }
        if (strtolower($method) === 'tonpay') {
            return 86400;
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
