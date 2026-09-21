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
        $zarinpal       = $get('zarinpalstatus');
        $iranpay2Active = $get('statustarnado');
        $tonpayActive   = $get('statustonpay');
        $cubepayActive  = $get('statuscubepay');
        $blupalActive   = $get('statusblupal');
        $varizaActive   = $get('statusvariza');
        $abangatewayActive = $get('statusabangateway');
        $abangatewayUrl    = trim((string) $get('urlabangateway'));
        $abangatewayKey    = trim((string) $get('apiabangateway'));
        $atlaspayActive = $get('statusatlaspay');
        $tetrapayActive = $get('statustetrapay');
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
            'zarinpal'      => ['minbalancezarinpal',      'maxbalancezarinpal'],
            'plisio'        => ['minbalanceplisio',        'maxbalanceplisio'],
            'nowpayment'    => ['minbalancenowpayment',    'maxbalancenowpayment'],
            'digitaltron'   => ['minbalancedigitaltron',   'maxbalancedigitaltron'],
            'iranpay2'      => ['minbalanceiranpay2',      'maxbalanceiranpay2'],
            'tonpay'        => ['minbalancetonpay',        'maxbalancetonpay'],
            'cubepay'       => ['minbalancecubepay',       'maxbalancecubepay'],
            'blupal'        => ['minbalanceblupal',        'maxbalanceblupal'],
            'variza'        => ['minbalancevariza',        'maxbalancevariza'],
            'abangateway'   => ['minbalanceabangateway',   'maxbalanceabangateway'],
            'atlaspay'      => ['minbalanceatlaspay',      'maxbalanceatlaspay'],
            'tetrapay'      => ['minbalancetetrapay',      'maxbalancetetrapay'],
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
                        'label' => $L('carttocart', '🔌 کارت‌به‌کارت'),
                        'icon'  => '💳',
                        'kind'  => 'url',
                        'url'   => 'https://t.me/' . ltrim($cartUsername, '@'),
                    ];
                } else {
                    $methods[] = [
                        'id'    => 'carttocart',
                        'label' => $L('carttocart', '🔌 کارت‌به‌کارت'),
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


        if ($iranpay2Active === 'onternado') {
            $methods[] = [
                'id'    => 'iranpay2',
                'label' => $L('iranpay3', 'ترونادو'),
                'icon'  => '🌸',
                'kind'  => 'form',
            ];
        }
        if ($tonpayActive === 'ontonpay') {
            $methods[] = [
                'id'    => 'tonpay',
                'label' => $L('tonpay', '💠 تون‌پی'),
                'icon'  => '💠',
                'kind'  => 'form',
            ];
        }
        if ($cubepayActive === 'oncubepay') {
            $methods[] = [
                'id'    => 'cubepay',
                'label' => $L('cubepay', '🟦 کیوب‌پی'),
                'icon'  => '🟦',
                'kind'  => 'form',
            ];
        }
        if ($atlaspayActive === 'onatlaspay') {
            $methods[] = [
                'id'    => 'atlaspay',
                'label' => $L('atlaspay', '🌐 اطلس‌پی'),
                'icon'  => '🌐',
                'kind'  => 'form',
            ];
        }
        if ($tetrapayActive === 'ontetrapay') {
            $methods[] = [
                'id'    => 'tetrapay',
                'label' => $L('tetrapay', '🔷 تتراپی'),
                'icon'  => '🔷',
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
        if ($blupalActive === 'onblupal') {
            $methods[] = [
                'id'    => 'blupal',
                'label' => $L('blupal', '💙 بلوپال'),
                'icon'  => '💙',
                'kind'  => 'form',
            ];
        }
        if ($varizaActive === 'onvariza') {
            $methods[] = [
                'id'    => 'variza',
                'label' => $L('variza', '💳 واریزا'),
                'icon'  => '💳',
                'kind'  => 'form',
            ];
        }
        // Listed only when it can take money: on, with an https address and a key.
        if ($abangatewayActive === 'onabangateway' && stripos($abangatewayUrl, 'https://') === 0 && $abangatewayKey !== '' && $abangatewayKey !== '0') {
            $methods[] = [
                'id'    => 'abangateway',
                'label' => $L('abangateway', '💳 آبان گیت وی'),
                'icon'  => '💳',
                'kind'  => 'form',
            ];
        }
        $cryptoOfflineStatus = function_exists('crypto_pay_setting')
            ? crypto_pay_setting('cryptocheck_status', 'offcrypto')
            : 'offcrypto';
        if ($cryptoOfflineStatus === 'oncrypto') {
            $methods[] = [
                'id'    => 'crypto_offline',
                'label' => $L('textnowpaymenttron', $L('cryptopay', '🪙 ارز آفلاین (هش‌چکر)')),
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

        $envMin = null;
        $envMax = null;
        foreach ($methods as $m) {
            $mm = (int)($m['min'] ?? 0);
            $xx = (int)($m['max'] ?? 0);
            if ($mm > 0) $envMin = ($envMin === null) ? $mm : min($envMin, $mm);
            if ($xx > 0) $envMax = ($envMax === null) ? $xx : max($envMax, $xx);
        }
        $displayMin = $envMin !== null ? $envMin : (int)$minBalance;
        $displayMax = $envMax !== null ? $envMax : (int)$maxBalance;

        $randomWalletDefaultAmounts = [50000, 75000, 100000, 150000, 200000, 250000, 500000, 1000000];
        $randomWalletDecoded = json_decode($get('randomwallet_amounts'), true);
        $randomWalletAmounts = (is_array($randomWalletDecoded) && count($randomWalletDecoded) === 8)
            ? array_values(array_filter($randomWalletDecoded, 'is_numeric'))
            : $randomWalletDefaultAmounts;
        if (count($randomWalletAmounts) !== 8) {
            $randomWalletAmounts = $randomWalletDefaultAmounts;
        }

        FaoximaResponse::ok([
            'methods'  => $methods,
            'limits'   => [
                'min' => $displayMin,
                'max' => $displayMax,
            ],
            'randomWallet' => [
                'enabled' => $get('randomwallet_status') === '1',
                'amounts' => $randomWalletAmounts,
            ],
            'balance'  => (float)($user['Balance'] ?? 0),
            'currency' => 'تومان',
        ]);
    }

    private function jsonAgentValue(string $json, string $agent, $default)
    {
        $json = trim($json);
        if ($json === '') return $default;

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return is_numeric($json) ? $json : $default;
        }

        foreach ([$agent, 'allusers', 'f'] as $key) {
            if (array_key_exists($key, $decoded)) {
                $val = $decoded[$key];
                if ($val !== '' && $val !== null) {
                    return $val;
                }
            }
        }

        return $default;
    }
}

