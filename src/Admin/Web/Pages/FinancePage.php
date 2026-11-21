<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class FinancePage
{
    private const GROUPS = [
        'cart' => [
            'title' => 'کارت به کارت',
            'icon'  => 'wallet',
            'fields' => [
                ['type' => 'toggle', 'name' => 'Cartstatus',          'label' => 'فعال‌سازی کارت به کارت',     'on' => 'oncard',        'off' => 'offcard'],
                ['type' => 'toggle', 'name' => 'Cartstatuspv',        'label' => 'دریافت رسید در پی‌وی',       'on' => 'oncardpv',      'off' => 'offcardpv'],
                ['type' => 'text',   'name' => 'cardnumber',          'label' => 'شماره کارت',                  'placeholder' => '6037-XXXX-XXXX-XXXX'],
                ['type' => 'text',   'name' => 'namecard',            'label' => 'نام صاحب کارت',               'placeholder' => 'علی محمدی'],
                ['type' => 'text',   'name' => 'CartDirect',          'label' => 'پل ارتباطی کارت',             'placeholder' => '@username'],
                ['type' => 'toggle', 'name' => 'statuscardautoconfirm','label' => 'تایید خودکار رسید کارت',     'on' => 'onautoconfirm', 'off' => 'offautoconfirm'],
                ['type' => 'toggle', 'name' => 'autoconfirmcart',     'label' => 'تایید خودکار سفارش (بدون تایید رسید)', 'on' => 'onauto', 'off' => 'offauto'],
                ['type' => 'toggle', 'name' => 'checkpaycartfirst',   'label' => 'بررسی پرداخت اولیه',          'on' => 'onpayverify',   'off' => 'offpayverify'],
                ['type' => 'number', 'name' => 'minbalancecart',      'label' => 'حداقل مبلغ شارژ (تومان)',     'placeholder' => '20000', 'money' => true],
                ['type' => 'number', 'name' => 'maxbalancecart',      'label' => 'حداکثر مبلغ شارژ (تومان)',    'placeholder' => '1000000', 'money' => true],
                ['type' => 'number', 'name' => 'helpcart',            'label' => 'توضیحات راهنما (شناسه پیام)', 'placeholder' => '2'],
                ['type' => 'number', 'name' => 'chashbackcart',       'label' => 'درصد کش‌بک (٪)',              'placeholder' => '0'],
            ],
        ],

        'rial_gateways' => [
            'title' => 'درگاه‌های ریالی',
            'icon'  => 'dollar-sign',
            'fields' => [

                ['type' => 'toggle', 'name' => 'zarinpalstatus',      'label' => 'فعال‌سازی زرین‌پال',           'on' => 'onzarinpal',    'off' => 'offzarinpal'],
                ['type' => 'secret', 'name' => 'merchant_zarinpal',   'label' => 'مرچنت زرین‌پال',               'placeholder' => 'مرچنت زرین‌پال'],
                ['type' => 'number', 'name' => 'helpzarinpal',        'label' => 'راهنما زرین‌پال (شناسه پیام)', 'placeholder' => '2'],
                ['type' => 'number', 'name' => 'chashbackzarinpal',   'label' => 'کش‌بک زرین‌پال (٪)',           'placeholder' => '0'],


                ['type' => 'secret', 'name' => 'apiiranpay',          'label' => 'API IRanpay',                  'placeholder' => 'API key'],
                ['type' => 'number', 'name' => 'chashbackiranpay2',   'label' => 'کش‌بک IRanpay۲ (٪)',           'placeholder' => '0'],
            ],
        ],

        'crypto_gateways' => [
            'title' => 'درگاه‌های ارزی / کریپتو',
            'icon'  => 'globe',
            'fields' => [

                ['type' => 'toggle', 'name' => 'nowpaymentstatus',    'label' => 'فعال‌سازی NowPayment',          'on' => 'onnowpayment',  'off' => 'offnowpayment'],
                ['type' => 'secret', 'name' => 'api_nowpayment',      'label' => 'API NowPayment',                'placeholder' => 'API key'],
                ['type' => 'secret', 'name' => 'nowpayment_ipn_secret','label' => 'IPN Secret NowPayment',        'placeholder' => 'IPN secret (اختیاری ولی توصیه‌شده)'],
                ['type' => 'number', 'name' => 'helpnowpayment',      'label' => 'راهنما (شناسه پیام)',          'placeholder' => '2'],
                ['type' => 'number', 'name' => 'cashbacknowpayment',  'label' => 'کش‌بک (٪)',                     'placeholder' => '0'],
                ['type' => 'number', 'name' => 'minbalancenowpayment','label' => 'حداقل مبلغ (تومان)',           'placeholder' => '20000', 'money' => true],
                ['type' => 'number', 'name' => 'maxbalancenowpayment','label' => 'حداکثر مبلغ (تومان)',          'placeholder' => '1000000', 'money' => true],


                ['type' => 'secret', 'name' => 'api_plisio',          'label' => 'API Plisio',                    'placeholder' => 'Plisio secret key'],
                ['type' => 'number', 'name' => 'helpplisio',          'label' => 'راهنما Plisio',                 'placeholder' => '2'],
                ['type' => 'number', 'name' => 'chashbackplisio',     'label' => 'کش‌بک Plisio (٪)',              'placeholder' => '0'],
                ['type' => 'number', 'name' => 'minbalanceplisio',    'label' => 'حداقل Plisio (تومان)',          'placeholder' => '20000', 'money' => true],
                ['type' => 'number', 'name' => 'maxbalanceplisio',    'label' => 'حداکثر Plisio (تومان)',         'placeholder' => '1000000', 'money' => true],


                ['type' => 'number', 'name' => 'helpperfectmony',     'label' => 'راهنما پرفکت مانی',            'placeholder' => '2'],
                ['type' => 'number', 'name' => 'chashbackperfect',    'label' => 'کش‌بک پرفکت (٪)',               'placeholder' => '0'],
                ['type' => 'number', 'name' => 'minbalanceperfect',   'label' => 'حداقل پرفکت (تومان)',          'placeholder' => '20000', 'money' => true],
                ['type' => 'number', 'name' => 'maxbalanceperfect',   'label' => 'حداکثر پرفکت (تومان)',         'placeholder' => '1000000', 'money' => true],


                ['type' => 'toggle', 'name' => 'statusstar',          'label' => 'فعال‌سازی Star Telegram',       'on' => '1',             'off' => '0'],
                ['type' => 'number', 'name' => 'helpstar',            'label' => 'راهنما Star (شناسه پیام)',     'placeholder' => '2'],
                ['type' => 'number', 'name' => 'chashbackstar',       'label' => 'کش‌بک Star (٪)',                'placeholder' => '0'],
                ['type' => 'number', 'name' => 'minbalancestar',      'label' => 'حداقل Star (تومان)',           'placeholder' => '20000', 'money' => true],
                ['type' => 'number', 'name' => 'maxbalancestar',      'label' => 'حداکثر Star (تومان)',          'placeholder' => '1000000', 'money' => true],
            ],
        ],

        'limits' => [
            'title' => 'حدود کلی شارژ',
            'icon'  => 'sliders',
            'fields' => [
                ['type' => 'number', 'name' => 'minbalance',          'label' => 'حداقل کلی شارژ کیف پول (تومان)','placeholder' => '20000', 'money' => true],
                ['type' => 'number', 'name' => 'maxbalance',          'label' => 'حداکثر کلی شارژ کیف پول (تومان)','placeholder' => '1000000', 'money' => true],
            ],
        ],
    ];

    public static function load(PDO $pdo): array
    {
        return [self::GROUPS, self::paymentSettings($pdo), self::textOverrides($pdo)];
    }

    public static function save(PDO $pdo, array $post, array $paymentSettings, array $textOverrides): string
    {
        [$saved, $rejected] = FinanceRequestHelpers::fx_finance_save_settings($pdo, self::GROUPS, $post, $paymentSettings, $textOverrides);
        return 'finance.php?saved=' . $saved . ($rejected > 0 ? '&edge_rejected=' . $rejected : '');
    }

    private static function paymentSettings(PDO $pdo): array
    {
        $paySettings = [];
        try {
            $r = $pdo->query("SELECT NamePay, ValuePay FROM PaySetting");
            if ($r) {
                foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $paySettings[(string)$row['NamePay']] = (string)$row['ValuePay'];
                }
            }
        } catch (\Throwable $e) {
            error_log('[panel/finance] load failed: ' . $e->getMessage());
        }
        return $paySettings;
    }

    private static function textOverrides(PDO $pdo): array
    {
        $textbotRows = [];
        try {
            $textbotKeys = [];
            foreach (self::GROUPS as $g) {
                foreach ($g['fields'] as $f) {
                    if (($f['type'] ?? '') === 'textbot') $textbotKeys[] = $f['name'];
                }
            }
            if (!empty($textbotKeys)) {
                $placeholders = implode(',', array_fill(0, count($textbotKeys), '?'));
                $stmt = $pdo->prepare("SELECT id_text, text FROM textbot WHERE id_text IN ($placeholders)");
                $stmt->execute($textbotKeys);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $textbotRows[(string)$row['id_text']] = (string)$row['text'];
                }
            }
        } catch (\Throwable $e) {
            error_log('[panel/finance] textbot load failed: ' . $e->getMessage());
        }
        return $textbotRows;
    }
}
