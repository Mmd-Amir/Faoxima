<?php



if (!function_exists('crypto_ton_address_hash')) {


    function crypto_ton_address_hash(string $addr): ?string
    {
        $addr = trim($addr);
        if ($addr === '') return null;


        if (preg_match('/^-?\d+:([0-9a-fA-F]{64})$/', $addr, $m)) {
            return strtolower($m[1]);
        }


        if (preg_match('~^[A-Za-z0-9_\-+/]{47,48}={0,2}$~', $addr)) {
            $b64 = strtr($addr, '-_', '+/');
            $pad = strlen($b64) % 4;
            if ($pad > 0) $b64 .= str_repeat('=', 4 - $pad);
            $bytes = @base64_decode($b64, true);
            if ($bytes === false || strlen($bytes) !== 36) return null;


            return strtolower(bin2hex(substr($bytes, 2, 32)));
        }
        return null;
    }
}

if (!function_exists('crypto_addresses_match_ton')) {
    function crypto_addresses_match_ton(string $a, string $b): bool
    {
        $ha = crypto_ton_address_hash($a);
        $hb = crypto_ton_address_hash($b);
        if ($ha === null || $hb === null) return false;
        return $ha === $hb;
    }
}

if (!function_exists('crypto_ton_extract_dest')) {


    function crypto_ton_extract_dest($field): string
    {
        if (is_string($field)) return $field;
        if (is_array($field)) {
            if (isset($field['address']) && is_string($field['address'])) {
                return $field['address'];
            }
        }
        return '';
    }
}

if (!function_exists('crypto_extract_ton_comment')) {
    function crypto_extract_ton_comment($msg): string
    {
        if (!is_array($msg)) return '';
        if (isset($msg['comment']) && is_string($msg['comment']) && $msg['comment'] !== '') {
            return trim($msg['comment']);
        }
        if (isset($msg['decoded_op_name']) && (string) $msg['decoded_op_name'] === 'text_comment') {
            $body = $msg['decoded_body'] ?? [];
            if (is_array($body) && isset($body['text']) && is_string($body['text'])) {
                return trim($body['text']);
            }
        }
        if (isset($msg['message']) && is_string($msg['message']) && $msg['message'] !== '') {
            return trim($msg['message']);
        }
        if (isset($msg['payload']) && is_string($msg['payload']) && $msg['payload'] !== '') {
            return trim($msg['payload']);
        }
        return '';
    }
}

if (!function_exists('crypto_memo_matches')) {
    function crypto_memo_matches(string $expected, string $observed): bool
    {
        $e = trim($expected);
        $o = trim($observed);
        if ($e === '') return true;
        if ($o === '') return false;
        if (strcasecmp($e, $o) === 0) return true;
        $eNorm = preg_replace('/\s+/u', '', $e);
        $oNorm = preg_replace('/\s+/u', '', $o);
        return $eNorm !== '' && strcasecmp((string) $eNorm, (string) $oNorm) === 0;
    }
}

if (!function_exists('crypto_check_ton_tx')) {
    function crypto_check_ton_tx(string $hash, string $expectedTo, float $expectedAmount, ?string $jettonMaster = null, bool $iranianMode = false, string $expectedMemo = ''): array
    {
        $hash = trim($hash);
        if ($hash === '') return ['ok' => false, 'reason' => 'bad-hash-format'];

        $apiKey = crypto_pay_setting('cryptocheck_tonapi_key', '');
        $headers = ['Accept: application/json'];
        if ($apiKey !== '') $headers[] = 'Authorization: Bearer ' . $apiKey;


        $event = crypto_http_get_json('https://tonapi.io/v2/events/' . urlencode($hash), $headers);
        $eventActions = is_array($event) ? ($event['actions'] ?? []) : [];
        if (!is_array($eventActions)) $eventActions = [];

        $expectedHash = crypto_ton_address_hash($expectedTo);
        $diagDests = [];


        if ($jettonMaster === null) {
            $foundDestMatch = false;
            $observedAtMatch = null;
            foreach ($eventActions as $a) {
                if (!is_array($a)) continue;
                $type = (string) ($a['type'] ?? '');
                if ($type !== 'TonTransfer') continue;
                $tt = $a['TonTransfer'] ?? $a['ton_transfer'] ?? null;
                if (!is_array($tt)) continue;
                $rcpt = crypto_ton_extract_dest($tt['recipient'] ?? null);
                if ($rcpt === '') continue;
                $diagDests[] = $rcpt;
                $rcptHash = crypto_ton_address_hash($rcpt);
                if ($rcptHash === null || $expectedHash === null || $rcptHash !== $expectedHash) continue;
                $foundDestMatch = true;
                $valueNano = (float) ($tt['amount'] ?? 0);
                $amountTon = $valueNano / 1000000000.0;
                $observedAtMatch = $amountTon;
                if (crypto_amount_within_tolerance($expectedAmount, $amountTon, $iranianMode)) {
                    if ($expectedMemo !== '') {
                        $observedComment = crypto_extract_ton_comment($tt);
                        if (!crypto_memo_matches($expectedMemo, $observedComment)) {
                            return ['ok' => false, 'reason' => 'memo-mismatch', 'detail' => ['observed' => $observedComment, 'want' => $expectedMemo]];
                        }
                    }
                    $sender = crypto_ton_extract_dest($tt['sender'] ?? null);
                    return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amountTon, 'to' => $rcpt, 'via' => 'events', 'sender' => $sender]];
                }
            }
            if ($foundDestMatch) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $observedAtMatch, 'want' => $expectedAmount]];
            }


            $tx = crypto_http_get_json('https://tonapi.io/v2/blockchain/transactions/' . urlencode($hash), $headers);
            if (!is_array($tx)) {
                if (empty($eventActions)) {
                    return ['ok' => false, 'reason' => 'tx-not-found'];
                }
                return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['want' => $expectedTo, 'seen' => $diagDests]];
            }
            if (!empty($tx['error'])) {
                return ['ok' => false, 'reason' => 'tx-not-found', 'detail' => $tx];
            }

            $candidates = [];
            if (!empty($tx['in_msg']) && is_array($tx['in_msg'])) {
                $im = $tx['in_msg'];
                $value = (float) ($im['value'] ?? 0);
                if ($value > 0) $candidates[] = $im;
            }
            if (!empty($tx['out_msgs']) && is_array($tx['out_msgs'])) {
                foreach ($tx['out_msgs'] as $om) {
                    if (is_array($om) && (float) ($om['value'] ?? 0) > 0) $candidates[] = $om;
                }
            }
            foreach ($candidates as $msg) {
                $dest = crypto_ton_extract_dest($msg['destination'] ?? null);
                if ($dest === '') continue;
                $diagDests[] = $dest;
                $destHash = crypto_ton_address_hash($dest);
                if ($destHash === null || $expectedHash === null || $destHash !== $expectedHash) continue;
                $foundDestMatch = true;
                $valueNano = (float) ($msg['value'] ?? 0);
                $amountTon = $valueNano / 1000000000.0;
                $observedAtMatch = $amountTon;
                if (crypto_amount_within_tolerance($expectedAmount, $amountTon, $iranianMode)) {
                    if ($expectedMemo !== '') {
                        $observedComment = crypto_extract_ton_comment($msg);
                        if (!crypto_memo_matches($expectedMemo, $observedComment)) {
                            return ['ok' => false, 'reason' => 'memo-mismatch', 'detail' => ['observed' => $observedComment, 'want' => $expectedMemo]];
                        }
                    }
                    $sender = crypto_ton_extract_dest($msg['source'] ?? null);
                    return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amountTon, 'to' => $dest, 'via' => 'tx', 'sender' => $sender]];
                }
            }
            if ($foundDestMatch) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $observedAtMatch, 'want' => $expectedAmount]];
            }
            return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['want' => $expectedTo, 'expectedHash' => $expectedHash, 'seenDests' => $diagDests]];
        }


        $foundDestMatch = false;
        $observedAtMatch = null;
        $expectedJettonHash = crypto_ton_address_hash($jettonMaster);
        foreach ($eventActions as $a) {
            if (!is_array($a)) continue;
            if (($a['type'] ?? '') !== 'JettonTransfer') continue;
            $jt = $a['JettonTransfer'] ?? $a['jetton_transfer'] ?? null;
            if (!is_array($jt)) continue;
            $jettonAddr = crypto_ton_extract_dest($jt['jetton']['address'] ?? ($jt['jetton'] ?? null));
            $jettonAddrHash = crypto_ton_address_hash($jettonAddr);
            if ($jettonAddrHash === null || $expectedJettonHash === null || $jettonAddrHash !== $expectedJettonHash) continue;
            $recipient = crypto_ton_extract_dest($jt['recipient'] ?? null);
            $diagDests[] = $recipient;
            $rcptHash = crypto_ton_address_hash($recipient);
            if ($rcptHash === null || $expectedHash === null || $rcptHash !== $expectedHash) continue;
            $foundDestMatch = true;
            $decimals = (int) ($jt['jetton']['decimals'] ?? 6);
            $raw = (string) ($jt['amount'] ?? '0');
            $amount = (float) $raw / pow(10, $decimals);
            $observedAtMatch = $amount;
            if (crypto_amount_within_tolerance($expectedAmount, $amount, $iranianMode)) {
                if ($expectedMemo !== '') {
                    $observedComment = crypto_extract_ton_comment($jt);
                    if ($observedComment === '' && isset($jt['payload'])) {
                        $observedComment = crypto_extract_ton_comment(['comment' => is_string($jt['payload']) ? $jt['payload'] : '']);
                    }
                    if (!crypto_memo_matches($expectedMemo, $observedComment)) {
                        return ['ok' => false, 'reason' => 'memo-mismatch', 'detail' => ['observed' => $observedComment, 'want' => $expectedMemo]];
                    }
                }
                $sender = crypto_ton_extract_dest($jt['sender'] ?? null);
                return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amount, 'to' => $recipient, 'sender' => $sender]];
            }
        }
        if ($foundDestMatch) {
            return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $observedAtMatch, 'want' => $expectedAmount]];
        }
        if (empty($eventActions)) {
            return ['ok' => false, 'reason' => 'tx-not-found'];
        }
        return ['ok' => false, 'reason' => 'no-matching-jetton-transfer', 'detail' => ['want' => $expectedTo, 'seenDests' => $diagDests]];
    }
}

if (!function_exists('crypto_check_payment')) {
    function crypto_check_payment(array $row): array
    {
        $currency = (string) ($row['crypto_currency'] ?? '');
        if ($currency === '') return ['ok' => false, 'reason' => 'no-currency'];
        $hash = (string) ($row['crypto_tx_hash'] ?? '');
        if ($hash === '') return ['ok' => false, 'reason' => 'no-hash'];
        $expectedTo = (string) ($row['crypto_wallet_to'] ?? '');
        if ($expectedTo === '') return ['ok' => false, 'reason' => 'no-recipient-on-row'];
        $expectedAmount = (float) ($row['crypto_amount'] ?? 0);
        if ($expectedAmount <= 0) return ['ok' => false, 'reason' => 'no-expected-amount'];

        $iranianMode = !empty($row['crypto_iranian_mode']) && (int) $row['crypto_iranian_mode'] === 1;

        $usdtTrc20 = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
        $usdtJettonMaster = 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs';

        $expectedMemo = '';
        if (in_array($currency, ['TON', 'USDT_TON'], true)) {
            $wallet = function_exists('crypto_active_wallet') ? crypto_active_wallet($currency) : null;
            if (is_array($wallet)) {
                $expectedMemo = trim((string) ($wallet['wallet_memo'] ?? ''));
            }
        }

        switch ($currency) {
            case 'TRX':        $verify = crypto_check_tron_tx($hash, $expectedTo, $expectedAmount, null, $iranianMode); break;
            case 'USDT_TRC20': $verify = crypto_check_tron_tx($hash, $expectedTo, $expectedAmount, $usdtTrc20, $iranianMode); break;
            case 'TON':        $verify = crypto_check_ton_tx($hash, $expectedTo, $expectedAmount, null, $iranianMode, $expectedMemo); break;
            case 'USDT_TON':   $verify = crypto_check_ton_tx($hash, $expectedTo, $expectedAmount, $usdtJettonMaster, $iranianMode, $expectedMemo); break;
            default:           return ['ok' => false, 'reason' => 'unsupported-currency'];
        }

        if (!is_array($verify) || empty($verify['ok'])) {
            return is_array($verify) ? $verify : ['ok' => false, 'reason' => 'verify-failed'];
        }

        $sender = trim((string) ($verify['detail']['sender'] ?? ''));

        if (!isset($verify['detail']) || !is_array($verify['detail'])) {
            $verify['detail'] = [];
        }
        $verify['detail']['sender'] = $sender;
        return $verify;
    }
}

if (!function_exists('crypto_explorer_url')) {
    function crypto_explorer_url(string $currency, string $hash): string
    {
        if ($currency === 'TRX' || $currency === 'USDT_TRC20') {
            return 'https://tronscan.org/#/transaction/' . $hash;
        }
        if ($currency === 'TON' || $currency === 'USDT_TON') {
            return 'https://tonviewer.com/transaction/' . $hash;
        }
        return $hash;
    }
}