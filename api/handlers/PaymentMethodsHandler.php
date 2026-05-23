<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class PaymentMethodsHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $user = $this->user;
        $agent = $user['agent'] ?? 'f';


        $self = $this;
        $get = function (string $name) use ($self): string {
            return $self->paySetting($name);
        };

        $cart           = $get('Cartstatus');
        $cartPv         = $get('Cartstatuspv');
        $cartUsername   = $get('CartDirect');
        $aqayepardakht  = $get('statusaqayepardakht');
        $zarinpal       = $get('zarinpalstatus');
        $zarinpey       = $get('zarinpeystatus');
        $iranpay1Active = $get('statusSwapWallet');
        $iranpay2Active = $get('statustarnado');
        $iranpay3Active = $get('statusiranpay3');
        $plisio         = $get('nowpaymentstatus');
        $nowpayment     = $get('statusnowpayment');
        $digi           = $get('digistatus');
        $notverify      = $get('paymentstatussnotverify');
        $tgStar         = $get('statusstar');
        $payVerify      = $get('checkpaycartfirst');


        $minBalanceJson = $get('minbalance');
        $maxBalanceJson = $get('maxbalance');
        $minBalance = $this->jsonAgentValue($minBalanceJson, $agent, 0);
        $maxBalance = $this->jsonAgentValue($maxBalanceJson, $agent, 10000000);


        $perMethodKeys = [
            'carttocart'    => ['minbalancecart',          'maxbalancecart'],
            'carttocart_pv' => ['minbalancecart',          'maxbalancecart'],
            'aqayepardakht' => ['minbalanceaqayepardakht', 'maxbalanceaqayepardakht'],
            'zarinpal'      => ['minbalancezarinpal',      'maxbalancezarinpal'],
            'zarinpey'      => ['minbalancezarinpey',      'maxbalancezarinpey'],
            'plisio'        => ['minbalanceplisio',        'maxbalanceplisio'],
            'nowpayment'    => ['minbalancenowpayment',    'maxbalancenowpayment'],
            'digitaltron'   => ['minbalancedigitaltron',   'maxbalancedigitaltron'],
            'iranpay1'      => ['minbalanceiranpay1',      'maxbalanceiranpay1'],
            'iranpay2'      => ['minbalanceiranpay2',      'maxbalanceiranpay2'],
            'iranpay3'      => ['minbalanceiranpay',       'maxbalanceiranpay'],
        ];
        $methodLimitsResolver = function (string $methodId) use ($perMethodKeys, $get, $minBalance, $maxBalance): array {
            if (isset($perMethodKeys[$methodId])) {
                [$minK, $maxK] = $perMethodKeys[$methodId];
                $m = (int) ($get($minK) ?: 0);
                $x = (int) ($get($maxK) ?: 0);
                if ($m > 0 && $x > 0) return [$m, $x];
            }
            return [(int)$minBalance, (int)$maxBalance];
        };


        $labels = [];
        $rows = select('textbot', '*', null, null, 'fetchAll') ?: [];
        foreach ($rows as $r) {
            if (!isset($r['id_text'])) continue;
            $labels[$r['id_text']] = (string)($r['text'] ?? '');
        }
        $L = static function (string $key, string $default = '') use ($labels): string {
            $v = isset($labels[$key]) ? trim($labels[$key]) : '';
            return $v !== '' ? $v : $default;
        };


        $paymentExits = (int) FaoximaDb::fetchScalar(
            "SELECT COUNT(*) FROM Payment_report WHERE id_user = :u AND payment_Status = 'paid'",
            [':u' => $user['id']]
        );


        $userCardPaymentEnabled = (int)($user['cardpayment'] ?? 1) === 1;


        $methods = [];


        if ($cart === 'oncard' && $userCardPaymentEnabled) {

            $hide = ($paymentExits === 0 && $payVerify === 'onpayverify');
            if (!$hide) {
                if ($cartPv === 'oncardpv' && $cartUsername !== '') {
                    $methods[] = [
                        'id'    => 'carttocart_pv',
                        'label' => $L('carttocart', '🔌 کارت به کارت'),
                        'icon'  => '💳',
                        'kind'  => 'url',
                        'url'   => 'https://t.me/' . ltrim($cartUsername, '@'),
                    ];
                } else {
                    $methods[] = [
                        'id'    => 'carttocart',
                        'label' => $L('carttocart', '🔌 کارت به کارت'),
                        'icon'  => '💳',
                        'kind'  => 'form',
                    ];
                }
            }
        }


        if ($plisio === 'onnowpayment') {
            $methods[] = [
                'id'    => 'plisio',
                'label' => $L('textnowpayment', 'پرداخت ارزی (Plisio)'),
                'icon'  => 'bitcoin',
                'kind'  => 'form',
            ];
        }
        if ($nowpayment === '1') {
            $methods[] = [
                'id'    => 'nowpayment',
                'label' => $L('textsnowpayment', 'NowPayments'),
                'icon'  => 'bitcoin',
                'kind'  => 'form',
            ];
        }
        if ($digi === 'ondigi') {
            $methods[] = [
                'id'    => 'digitaltron',
                'label' => $L('textnowpaymenttron', 'پرداخت با ترون'),
                'icon'  => 'tron',
                'kind'  => 'crypto_offline',
            ];
        }


        if ($iranpay1Active === 'onSwapinoBot') {
            $methods[] = [
                'id'    => 'iranpay1',
                'label' => $L('iranpay2', '🌸 درگاه پرداخت ریالی'),
                'icon'  => '🌸',
                'kind'  => 'form',
            ];
        }
        if ($iranpay2Active === 'onternado') {
            $methods[] = [
                'id'    => 'iranpay2',
                'label' => $L('iranpay3', '🌸 درگاه پرداخت ریالی دوم'),
                'icon'  => '🌸',
                'kind'  => 'form',
            ];
        }
        if ($iranpay3Active === 'oniranpay3' && $paymentExits >= 2) {
            $methods[] = [
                'id'    => 'iranpay3',
                'label' => $L('iranpay1', '🌸 درگاه پرداخت ریالی سوم'),
                'icon'  => '🌸',
                'kind'  => 'form',
            ];
        }

        if ($aqayepardakht === 'onaqayepardakht') {
            $methods[] = [
                'id'    => 'aqayepardakht',
                'label' => $L('aqayepardakht', '🌸 آقای پرداخت'),
                'icon'  => '🌸',
                'kind'  => 'form',
            ];
        }
        if ($zarinpal === 'onzarinpal') {
            $methods[] = [
                'id'    => 'zarinpal',
                'label' => $L('zarinpal', '🟡 زرین‌پال'),
                'icon'  => '🟡',
                'kind'  => 'form',
            ];
        }
        if ($zarinpey === 'onzarinpey') {
            $methods[] = [
                'id'    => 'zarinpey',
                'label' => $L('zarinpey', '🟠 زرین پی'),
                'icon'  => '🟠',
                'kind'  => 'form',
            ];
        }

        $cryptoOfflineStatus = function_exists('crypto_pay_setting')
            ? crypto_pay_setting('cryptocheck_status', 'offcrypto')
            : 'offcrypto';
        if ($cryptoOfflineStatus === 'oncrypto') {
            $methods[] = [
                'id'    => 'crypto_offline',
                'label' => $L('cryptopay', '🪙 ارز آفلاین (هش‌چکر)'),
                'icon'  => '🟢',
                'kind'  => 'crypto_offline',
            ];
        }

        if ($notverify === 'onverifypay') {
            $methods[] = [
                'id'    => 'paymentnotverify',
                'label' => $L('textpaymentnotverify', '✅ تایید پرداخت دستی'),
                'icon'  => '✅',
                'kind'  => 'form',
            ];
        }
        if ((int)$tgStar === 1) {
            $methods[] = [
                'id'    => 'startelegrams',
                'label' => $L('text_star_telegram', '⭐ پرداخت با Telegram Stars'),
                'icon'  => '⭐',
                'kind'  => 'callback',
            ];
        }


        foreach ($methods as &$m) {
            $mid = (string)($m['id'] ?? '');
            if ($mid === '') continue;
            [$mMin, $mMax] = $methodLimitsResolver($mid);
            $m['min'] = $mMin;
            $m['max'] = $mMax;
        }
        unset($m);

        FaoximaResponse::ok([
            'methods'  => $methods,
            'limits'   => [
                'min' => (int)$minBalance,
                'max' => (int)$maxBalance,
            ],
            'balance'  => (float)($user['Balance'] ?? 0),
            'currency' => 'تومان',
        ]);
    }

    private function jsonAgentValue(string $json, string $agent, $default)
    {
        if ($json === '') return $default;
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return $default;
        return $decoded[$agent] ?? $default;
    }
}

