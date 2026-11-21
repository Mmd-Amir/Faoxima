<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class CryptoCurrenciesHandler extends \Faoxima\Api\Handlers\BaseHandler
{

    private const CURRENCY_META = [
        'TRX'        => ['icon_key' => 'tron',   'color' => '#e53935', 'fa_name' => 'ترون',           'network' => 'TRON', 'memo' => false],
        'USDT_TRC20' => ['icon_key' => 'tether', 'color' => '#26a17b', 'fa_name' => 'تتر روی ترون',    'network' => 'TRON', 'memo' => false],
        'TON'        => ['icon_key' => 'ton',    'color' => '#0098ea', 'fa_name' => 'تون',            'network' => 'TON',  'memo' => true],
        'USDT_TON'   => ['icon_key' => 'tether', 'color' => '#26a17b', 'fa_name' => 'تتر روی تون',     'network' => 'TON',  'memo' => true],
    ];

    public function handle(): void
    {
        $this->requireMethod('GET');

        if (!method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallets') || !method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_supported_currencies')) {
            \Faoxima\Api\Runtime\Response::fail(503, 'crypto offline درگاه روی این سرور تنظیم نشده است');
        }

        $autoSupported = \Faoxima\Payments\Crypto\ManualCrypto::crypto_supported_currencies();
        $manualSupported = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
        $supported = $autoSupported + $manualSupported;
        $activeWallets = \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallets();

        $currencies = [];
        $hasAuto = false;
        $hasManual = false;
        foreach ($activeWallets as $w) {
            $code = strtoupper((string)($w['currency'] ?? ''));
            if ($code === '' || !isset($supported[$code])) continue;
            $isManual = isset($manualSupported[$code]);
            if ($isManual) { $hasManual = true; } else { $hasAuto = true; }

            $meta = self::CURRENCY_META[$code] ?? [
                'icon_key' => 'coin',
                'color'    => '#d4b878',
                'fa_name'  => $supported[$code]['fa_short'] ?? $code,
                'network'  => $supported[$code]['network'] ?? '',
                'memo'     => false,
            ];
            $currencies[] = [
                'code'              => $code,
                'name'              => $meta['fa_name'],
                'label'             => $supported[$code]['label'] ?? $meta['fa_name'],
                'icon_key'          => $meta['icon_key'],
                'color'             => $meta['color'],
                'network'           => $supported[$code]['network'] ?? $meta['network'],
                'wallet_address'    => (string)($w['wallet_address'] ?? ''),
                'memo_required'     => (bool)$meta['memo'],
                'verification_mode' => $isManual ? 'manual' : 'automated',
                'is_manual'         => $isManual,
                'receipt_required'  => $isManual,
            ];
        }

        $minRow = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_pay_setting') ? (int) \Faoxima\Payments\Crypto\ManualCrypto::crypto_pay_setting('cryptocheck_min_irt', '0') : 0;
        $maxRow = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_pay_setting') ? (int) \Faoxima\Payments\Crypto\ManualCrypto::crypto_pay_setting('cryptocheck_max_irt', '0') : 0;

        if ($hasAuto && $hasManual) {
            $hint = 'برای شبکه‌های خودکار فقط هش تراکنش کافی است. برای شبکه‌های دستی، بعد از هش باید عکس رسید نیز ارسال شود و تایید نهایی توسط ادمین انجام می‌شود.';
        } elseif ($hasManual) {
            $hint = 'این شبکه‌ها به‌صورت خودکار بررسی نمی‌شوند. بعد از هش باید عکس رسید تراکنش نیز ارسال شود و تایید نهایی توسط ادمین انجام می‌شود.';
        } else {
            $hint = 'پس از پرداخت، فقط هش (Hash / TxID) تراکنش کافی است — نیازی به ارسال عکس نیست.';
        }

        \Faoxima\Api\Runtime\Response::ok([
            'currencies'           => $currencies,
            'min_amount_toman'     => $minRow,
            'max_amount_toman'     => $maxRow,
            'hint'                 => $hint,
        ]);
    }
}
