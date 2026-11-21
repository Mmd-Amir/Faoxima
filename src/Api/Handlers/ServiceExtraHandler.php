<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use ManagePanel;
use RuntimeException;
use Throwable;

final class ServiceExtraHandler extends \Faoxima\Api\Handlers\BaseHandler
{


    public ?string $mode = null;

    public function handle(): void
    {
        $mode = $this->mode ?? \Faoxima\Api\Runtime\Input::string($this->data, '_mode');
        if ($mode === 'quote') {
            $this->requireMethod('GET');
            $this->handleQuote();
            return;
        }
        if ($mode === 'confirm') {
            $this->requireMethod('POST');
            $this->handleConfirm();
            return;
        }
        \Faoxima\Api\Runtime\Response::badRequest('Unknown mode for service_extra');
    }

    private function handleQuote(): void
    {
        [$invoice, $panel, $kind, $pricePerUnit, $bounds, $fxKind] = $this->resolveContext('GET');

        $amount = \Faoxima\Api\Runtime\Input::int($this->data, 'amount', 0);
        $total = null;
        if ($amount > 0) {
            $total = $amount * $pricePerUnit;
            $total = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($total, $panel, $fxKind);
        }

        \Faoxima\Api\Runtime\Response::ok([
            'username'        => (string)$invoice['username'],
            'kind'            => $kind,
            'price_per_unit'  => (int)$pricePerUnit,
            'unit_label'      => $kind === 'time' ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_unit_day', 'روز') : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_unit_gigabyte', 'گیگابایت'),
            'min'             => $bounds[0],
            'max'             => $bounds[1],
            'amount'          => $amount,
            'total_price'     => $total === null ? null : (int)$total,
            'balance'         => (float)($this->user['Balance'] ?? 0),
            'fx_enabled'      => \Faoxima\Orders\Pricing\ExchangePricing::fxContext($panel, $fxKind) !== null,
            'fx_quote'        => \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteToken($panel, $fxKind),
            'fx_round_step'   => \Faoxima\Orders\Pricing\ExchangePricing::fxContext($panel, $fxKind)['round_step'] ?? null,
        ]);
    }

    private function handleConfirm(): void
    {
        [$invoice, $panel, $kind, $pricePerUnit, $bounds, $fxKind] = $this->resolveContext('POST');

        $amount = \Faoxima\Api\Runtime\Input::int($this->data, 'amount', 0);
        if ($amount <= 0) {
            \Faoxima\Api\Runtime\Response::badRequest('amount must be > 0');
        }
        if (!ctype_digit((string)$amount)) {
            \Faoxima\Api\Runtime\Response::badRequest('amount must be a positive integer');
        }
        if (count($bounds) === 2 && $bounds[0] > 0 && $amount < $bounds[0]) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_amount_min_tpl', '❌ مقدار باید حداقل {min} باشد'), ['min' => $bounds[0]]));
        }
        if (count($bounds) === 2 && $bounds[1] > 0 && $amount > $bounds[1]) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_amount_max_tpl', '❌ مقدار باید حداکثر {max} باشد'), ['max' => $bounds[1]]));
        }

        $price = (float) ($amount * $pricePerUnit);
        $price = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($price, $panel, $fxKind);
        if (\Faoxima\Orders\Pricing\ExchangePricing::fxContext($panel, $fxKind) !== null && !\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteTokenValid(\Faoxima\Api\Runtime\Input::string($this->data, 'fx_quote'), $panel, $fxKind)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedApiMessage(), \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedApiObj((int) $price, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteToken($panel, $fxKind)));
        }
        $fxListPrice = (float) $price;


        $discountCode = \Faoxima\Api\Runtime\Input::string($this->data, 'discount_code');
        $discPriceBefore = null;
        if ($discountCode !== '') {
            $sourceProduct = \Faoxima\Api\Runtime\Db::fetchOne(
                "SELECT category FROM product WHERE name_product = :n AND (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1",
                [':n' => (string)($invoice['name_product'] ?? ''), ':loc' => (string)($invoice['Service_location'] ?? ''), ':agent' => (string)($this->user['agent'] ?? 'f')]
            );
            $categoryList = is_array($sourceProduct) ? \Faoxima\Orders\Catalog\NameCascades::nmProductCategoryList($sourceProduct) : [];

            $dv = \Faoxima\Orders\Discounts\DiscountRules::validateSellForCategories(\Faoxima\Api\Runtime\Db::pdo(),
                $discountCode,
                $kind === 'time' ? 'time' : 'volume',
                '',
                (string)($panel['code_panel'] ?? ''),
                $categoryList,
                $this->user
            );
            if (empty($dv['ok'])) {
                \Faoxima\Api\Runtime\Response::fail(422, (string)($dv['reason'] ?? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_invalid_discount_code', '❌ کد تخفیف نامعتبر است.')));
            }
            $discPriceBefore = $price;
            $price = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(\Faoxima\Orders\Discounts\DiscountRules::applyToPrice($dv['row'], $price), $panel, $fxKind);
            \Faoxima\Orders\Discounts\DiscountRules::logOrderDiscount(\Faoxima\Api\Runtime\Db::pdo(), [
                'id_user' => $this->user['id'],
                'id_invoice' => $invoice['id_invoice'] ?? null,
                'code' => $discountCode,
                'kind' => 'sell',
                'value_type' => $dv['value_type'],
                'value_raw' => $dv['value'],
                'price_before' => $discPriceBefore,
                'discount_amount' => $discPriceBefore - $price,
                'price_after' => $price,
                'section' => $kind === 'time' ? 'time' : 'volume',
            ]);
        }


        $discount = (int)($this->user['pricediscount'] ?? 0);
        $finalPrice = $price;
        if ($discount !== 0) {
            $finalPrice = $finalPrice - (($finalPrice * $discount) / 100);
        }
        $finalPrice = (int) round($finalPrice);
        $finalPrice = (int) \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($finalPrice, $panel, $fxKind);
        $fxBaseUnit = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($panel, $this->fxPriceField($invoice, $kind), (string)($this->user['agent'] ?? 'f'));
        $fxSnapshot = \Faoxima\Orders\Pricing\ExchangePricing::fxPricingSnapshot($panel, $fxKind, $amount * ($fxBaseUnit ?? 0), $finalPrice, $fxListPrice - $finalPrice, ['quantity' => $amount, 'unit_price' => (int) $pricePerUnit, 'source_channel' => 'miniapp']);

        $requestId = \Faoxima\Api\Runtime\Input::string($this->data, 'operation_id');
        if ($requestId !== '' && !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $requestId)) {
            \Faoxima\Api\Runtime\Response::badRequest('شناسه عملیات نامعتبر است');
        }
        $operationId = $requestId === '' ? 'extra_' . bin2hex(random_bytes(16))
            : 'extra_' . substr(hash('sha256', json_encode([(string) $this->user['id'], $invoice['username'], $kind, strtolower($requestId)], JSON_THROW_ON_ERROR)), 0, 48);
        $operationType = $kind === 'time' ? 'extra_time_user' : 'extra_user';
        $existing = \Faoxima\Services\Fulfillment\WalletExtensions::blockingOperation(
            \Faoxima\Api\Runtime\Db::pdo(), (string) $this->user['id'], (string) $invoice['username'],
            $operationType, (string) $invoice['id_invoice'], $operationId
        );
        if ($existing !== null) {
            \Faoxima\Api\Runtime\Response::fail(503, 'وضعیت ثبت خرید مشخص نیست؛ با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $existing]);
            return;
        }

        $balance = (float)($this->user['Balance'] ?? 0);
        $agent = (string)($this->user['agent'] ?? 'f');


        $maxBuyAgent = (int)($this->user['maxbuyagent'] ?? 0);
        if ($maxBuyAgent !== 0 && $agent === 'n2') {
            if (($balance - $finalPrice) < (-1 * $maxBuyAgent)) {
                \Faoxima\Api\Runtime\Response::fail(403, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceaction_purchase_limit_exhausted', '❌ مبلغ مجاز خرید شما به اتمام رسیده است.'));
            }
        }


        $walletState = is_callable([\Faoxima\Orders\Checkout\Checkout::class, 'available']) ? \Faoxima\Orders\Checkout\Checkout::available(\Faoxima\Api\Runtime\Db::pdo(), $this->user['id']) : ['balance' => (int) $balance, 'available' => (int) $balance];
        $shortfall = $finalPrice - (float) $walletState['available'];
        if ($shortfall > 0 && $agent !== 'n2') {
            $directBuy = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($invoice['Service_location'], 'directbuy') ? 'ondirectbuy' : 'offdirectbuy';
            if ($directBuy !== 'ondirectbuy') {
                \Faoxima\Api\Runtime\Response::fail(402, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_insufficient_balance', '❌ موجودی کیف پول شما کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.'));
            }

            $orderId = bin2hex(random_bytes(4));

            $extendStateTow = $kind === 'time' ? 'getextratimeuser' : 'getextravolumeuser';
            $opened = $this->openCheckout($extendStateTow, $extendStateTow . '|' . $invoice['username'] . '%' . $amount, (string) $invoice['username'], (int) $finalPrice, ['extra_kind' => $kind, 'extra_amount' => $amount]);
            $amountDue = (int) $opened['checkout']['external_amount'];
            \Faoxima\Core\Database\Db::update('user', 'Processing_value',     $amountDue,                              'id', $this->user['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', $invoice['username'] . '%' . $amount, 'id', $this->user['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', $extendStateTow,                       'id', $this->user['id']);

            \Faoxima\Api\Runtime\Response::ok(array_merge($this->checkoutResponseFields($opened), [
                'kind'        => 'requires_payment',
                'amount_due'  => $amountDue,
                'balance'     => $balance,
                'price'       => $finalPrice,
                'username'    => (string)$invoice['username'],
                'order_id'    => $orderId,
                'extra_kind'  => $kind,
                'extra_amount'=> $amount,
                'message'     => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_insufficient_balance_pay_shortfall', 'موجودی کیف پول کافی نیست. لطفاً مبلغ کسری را پرداخت کنید.'),
            ]));
            return;
        }


        $pendingRowId = 0;
        $managePanel = new ManagePanel();
        $beforeSnapshot = is_callable([\Faoxima\Services\Fulfillment\ProviderState::class, 'panelSnapshot'])
            ? \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($managePanel, (string)$panel['name_panel'], (string)$invoice['username'], (string)($panel['type'] ?? ''))
            : null;
        $targetState = null;
        if (is_array($beforeSnapshot) && !empty($beforeSnapshot['ok']) && !empty($beforeSnapshot['exists'])) {
            $targetState = $kind === 'time'
                ? ['expire' => max(time(), (int)($beforeSnapshot['expire'] ?? 0)) + ((int)$amount * 86400)]
                : ['data_limit' => (int)($beforeSnapshot['data_limit'] ?? 0) + ((int)$amount * 1073741824)];
        }
        $operationValue = [
            'operation_id' => $operationId,
            'invoice_id' => (string)($invoice['id_invoice'] ?? ''),
            'panel' => (string)$panel['name_panel'],
            'kind' => $kind,
            'amount' => (int)$amount,
            'discount_code' => $discountCode,
            'before' => empty($beforeSnapshot['exists']) ? null : \Faoxima\Services\Fulfillment\ProviderState::limits($beforeSnapshot),
            'target' => $targetState,
            'wallet_state' => 'charged',
        ];
        $pdo = \Faoxima\Api\Runtime\Db::pdo();
        $ledgerNote = $kind === 'time'
            ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_ledger_debit_time', 'خرید زمان اضافه')
            : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_ledger_debit_volume', 'خرید حجم اضافه');
        $reservation = \Faoxima\Services\Fulfillment\WalletExtensions::reserve(
            $pdo, $this->user, (string) $invoice['username'], $operationValue, $operationType, $finalPrice, $agent, $ledgerNote
        );
        if (!$reservation['ok']) {
            $reason = $reservation['reason'];
            if (!empty($reservation['blocking_operation'])) {
                try {
                    \Faoxima\Services\Fulfillment\WalletExtensions::reconcileUncertain(
                        $pdo, (string) $this->user['id'], $reservation['blocking_operation'], $managePanel, $panel
                    );
                } catch (Throwable $error) {
                    \Faoxima\Api\Runtime\Logger::warn('Extension read-back could not be recorded', ['operation_id' => $reservation['blocking_operation']]);
                }
            }
            \Faoxima\Api\Runtime\Logger::error('Service extra operation could not be recorded', ['operation_id' => $operationId, 'error' => $reason]);
            if ($reservation['unknown'] || in_array($reason, ['operation_pending', 'operation_already_recorded'], true)) {
                \Faoxima\Api\Runtime\Response::fail(503, 'وضعیت ثبت خرید مشخص نیست؛ با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $reservation['blocking_operation'] ?? $operationId]);
            }
            if (str_starts_with($reason, 'discount_')) {
                \Faoxima\Api\Runtime\Response::fail(409, 'کد تخفیف دیگر قابل استفاده نیست. لطفاً خرید را دوباره انجام دهید.');
            }
            if ($reason === 'insufficient_balance') {
                \Faoxima\Api\Runtime\Response::fail(402, 'موجودی کیف پول شما کافی نیست.');
            }
            \Faoxima\Api\Runtime\Response::fail(500, 'ثبت درخواست خرید انجام نشد؛ مبلغی کسر نشد. لطفاً دوباره تلاش کنید.');
            return;
        }
        $pendingRowId = $reservation['id'];
        $newBalance = $reservation['new_balance'];

        $execution = \Faoxima\Services\Fulfillment\WalletExtensions::applyReserved(
            $pdo, $pendingRowId, (string) $this->user['id'], $operationValue,
            $managePanel, $panel, (string) $invoice['username'], (int) $amount
        );
        $result = $execution['result'];
        $operationValue = $execution['order'];
        $beforeSnapshot = $operationValue['before'];
        if ($execution['outcome'] !== 'applied') {
            $reason = is_array($result) ? \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($result['msg'] ?? $result, $result['detail'] ?? null) : htmlspecialchars((string)$result, ENT_QUOTES, 'UTF-8');
            \Faoxima\Api\Runtime\Logger::error('ManagePanel extra_* failed', [
                'kind'     => $kind,
                'user_id'  => $this->user['id'],
                'panel'    => $panel['name_panel'],
                'username' => $invoice['username'],
                'reason'   => $reason,
            ]);

            $uncertain = $execution['outcome'] === 'uncertain';
                if ($uncertain) {
                    $errorTitle = $kind === 'time'
                        ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_error_title_time', 'خطای خرید زمان اضافه')
                        : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_error_title_volume', 'خطای خرید حجم اضافه');
                    $this->reportError(
                        \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_uncertain_report_tpl', "{error_title}\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>نتیجه عملیات در پنل قابل تشخیص نیست : {reason}</blockquote>"), [
                            'error_title' => $errorTitle,
                            'panel_name' => $panel['name_panel'],
                            'username' => \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($invoice['username'], $panel),
                            'reason' => $reason,
                        ]) . "\n<blockquote>شناسه کاربر: <code>" . htmlspecialchars((string)$this->user['id'], ENT_QUOTES, 'UTF-8') . "</code></blockquote>"
                        . "\n<blockquote>شناسه عملیات: <code>" . $operationId . "</code></blockquote>"
                        . "\n<blockquote>مبلغ: " . number_format($finalPrice) . " تومان</blockquote>"
                        . "\n<blockquote>مقدار درخواست: " . (int)$amount . ($kind === 'time' ? ' روز' : ' گیگابایت') . "</blockquote>"
                    );
                    \Faoxima\Api\Runtime\Logger::critical('extra outcome uncertain; no refund issued', [
                        'user_id' => $this->user['id'],
                        'invoice' => $operationId,
                        'kind' => $kind,
                    ]);
                    \Faoxima\Api\Runtime\Response::fail(503, 'نتیجه عملیات در پنل مشخص نیست؛ لطفاً با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $operationId]);
                }

                try {
                    \Faoxima\Services\Fulfillment\WalletExtensions::refund(
                        $pdo, $pendingRowId, $this->user, $operationValue, $finalPrice, $result,
                        \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_refund_note', 'بازگشت وجه به دلیل خطا در سرویس اضافه')
                    );
                } catch (Throwable $e) {
                    \Faoxima\Api\Runtime\Logger::critical('Service extra refund requires review', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
                    \Faoxima\Api\Runtime\Response::fail(503, 'بازگشت وجه تکمیل نشد؛ لطفاً با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $operationId]);
                }
                $errorTitle = $kind === 'time'
                    ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_error_title_time', 'خطای خرید زمان اضافه')
                    : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_error_title_volume', 'خطای خرید حجم اضافه');
                $this->reportError(
                    \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_error_report_tpl', "{error_title}\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>دلیل خطا : {reason}</blockquote>"), [
                        'error_title' => $errorTitle,
                        'panel_name' => $panel['name_panel'],
                        'username' => \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($invoice['username'], $panel),
                        'reason' => $reason,
                    ])
                );
                $human = $kind === 'time'
                    ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_generic_error_time', '❌ خطایی در خرید زمان اضافه رخ داده با پشتیبانی در ارتباط باشید')
                    : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_generic_error_volume', '❌ خطایی در خرید حجم اضافه رخ داده با پشتیبانی در ارتباط باشید');
                \Faoxima\Api\Runtime\Response::fail(502, $human);
        }

        \Faoxima\Orders\Discounts\DiscountRules::logSale(\Faoxima\Api\Runtime\Db::pdo(), [
            'id_user' => $this->user['id'],
            'id_invoice' => $invoice['id_invoice'] ?? null,
            'Service_location' => $invoice['Service_location'],
            'kind' => $kind === 'time' ? 'time' : 'volume',
            'name_product' => $invoice['name_product'] ?? null,
            'amount' => $finalPrice,
            'source' => 'miniapp',
        ]);


        try {
            $oldDataLimit = '';
            $oldExpire = '';
            if (is_array($beforeSnapshot) && !empty($beforeSnapshot['ok']) && !empty($beforeSnapshot['exists'])) {
                $oldDataLimit = (string)($beforeSnapshot['data_limit'] ?? '');
                $oldExpire    = (string)($beforeSnapshot['expire'] ?? '');
            } else {
                try {
                    $remote = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
                    if (is_array($remote)) {
                        $oldDataLimit = (string)($remote['data_limit'] ?? '');
                        $oldExpire    = (string)($remote['expire'] ?? '');
                    }
                } catch (Throwable $e) {  }
            }

            if ($kind === 'time') {
                $value = json_encode([
                    'day'             => $amount,
                    'priceـper_day'   => (int)$pricePerUnit,
                    'old_volume'      => $oldDataLimit,
                    'expire_old'      => $oldExpire,
                ], JSON_UNESCAPED_UNICODE);
                $value = \Faoxima\Orders\Pricing\ExchangePricing::fxValueWithSnapshot($value, $fxSnapshot);
                $type = 'extra_time_user';
            } else {
                $value = json_encode([
                    'volume_value'    => $amount,
                    'priceـper_gig'   => (int)$pricePerUnit,
                    'old_volume'      => $oldDataLimit,
                    'expire_old'      => $oldExpire,
                ], JSON_UNESCAPED_UNICODE);
                $value = \Faoxima\Orders\Pricing\ExchangePricing::fxValueWithSnapshot($value, $fxSnapshot);
                $type = 'extra_user';
            }

            if (!\Faoxima\Services\Fulfillment\WalletExtensions::complete(
                $pdo, $pendingRowId, (string) $this->user['id'], $operationValue, (array) json_decode($value, true), $result
            )) {
                throw new RuntimeException('extension_completion_unavailable');
            }
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::warn('service_other completion failed (extra)', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(503, 'نتیجه عملیات در پنل مشخص نیست؛ لطفاً با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $operationId]);
            return;
        }

        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
            \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event($kind === 'time' ? 'time_extra' : 'volume_topup', [
                'user_id' => $this->user['id'],
                'amount'  => $amount,
                'price'   => number_format((float)$finalPrice),
            ], $this->setting);
        }

        \Faoxima\Api\Runtime\Logger::debug('Inline extra purchase completed', [
            'kind'     => $kind,
            'user_id'  => $this->user['id'],
            'username' => $invoice['username'],
            'amount'   => $amount,
            'price'    => $finalPrice,
        ]);

        $msg = $kind === 'time'
            ? \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_success_time_tpl', '✅ {amount} روز به سرویس شما اضافه شد.'), ['amount' => $amount])
            : \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_success_volume_tpl', '✅ {amount} گیگابایت به سرویس شما اضافه شد.'), ['amount' => $amount]);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'          => 'done',
            'message'       => $msg,
            'extra_kind'    => $kind,
            'extra_amount'  => $amount,
            'price'         => $finalPrice,
            'balance_after' => $newBalance,
            'operation_id'  => $operationId,
        ]);
    }


    private function resolveContext(string $method): array
    {
        $username = \Faoxima\Api\Runtime\Input::string($this->data, 'username');
        if ($username === '') {
            \Faoxima\Api\Runtime\Response::badRequest('username is required');
        }
        $kind = strtolower(\Faoxima\Api\Runtime\Input::string($this->data, 'kind'));
        if (!in_array($kind, ['time', 'volume'], true)) {
            \Faoxima\Api\Runtime\Response::badRequest("kind must be 'time' or 'volume'");
        }

        $invoice = \Faoxima\Api\Runtime\Db::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            \Faoxima\Api\Runtime\Response::notFound('Service not found');
        }

        if ($method === 'POST'
            && !in_array((string)$invoice['Status'], ['active','end_of_time','end_of_volume','sendedwarn','send_on_hold'], true)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_status_invalid_restart', '❌ خرید با خطا مواجه گردید مراحل را مجدد انجام دهید.'));
        }

        $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (empty($panel)) {
            \Faoxima\Api\Runtime\Response::notFound('Panel not found');
        }
        if (($panel['status_extend'] ?? '') === 'off_extend') {
            \Faoxima\Api\Runtime\Response::fail(409, $kind === 'time'
                ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_time_unavailable_panel', '❌ امکان خرید زمان اضافه در این پنل وجود ندارد')
                : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_volume_unavailable_panel', '❌ امکان خرید حجم اضافه در این پنل وجود ندارد'));
        }


        $featKey = $kind === 'time' ? 'timeextra' : 'extravolume';
        if (!\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($panel, $featKey)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_feature_unavailable', '❌ این قابلیت درحال حاضر در دسترس نیست'));
        }


        if ($kind === 'time' && ($invoice['name_product'] ?? '') === 'سرویس تست') {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_test_service_unavailable', '❌ این قابلیت برای سرویس تست در دسترس نیست'));
        }

        $agent = (string)($this->user['agent'] ?? 'f');
        $isCustomService = in_array($invoice['name_product'] ?? '', ['🛍 حجم دلخواه', '⚙️ سرویس دلخواه'], true);
        $priceField = $this->fxPriceField($invoice, $kind);
        if ($isCustomService) {
            $fxKind = $kind === 'time' ? 'custom_time' : 'custom_volume';
        } else {
            $fxKind = $kind === 'time' ? 'extra_time' : 'extra_volume';
        }
        $pricePerUnit = (int) \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($this->jsonAgentValue($panel[$priceField] ?? '', $agent, 0), $panel, $fxKind);
        if ($pricePerUnit <= 0) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceextra_tariff_not_configured', '❌ تعرفه خرید اضافه روی این پنل تنظیم نشده است.'));
        }


        if ($kind === 'time') {
            $min = max(1, (int) $this->jsonAgentValue($panel['maintime'] ?? '', $agent, 1));
            $max = (int) $this->jsonAgentValue($panel['maxtime'] ?? '', $agent, 0);
        } else {
            $min = max(1, (int) $this->jsonAgentValue($panel['mainvolume'] ?? '', $agent, 1));
            $max = (int) $this->jsonAgentValue($panel['maxvolume'] ?? '', $agent, 0);
        }
        if ($max <= 0) $max = 9999;

        return [$invoice, $panel, $kind, $pricePerUnit, [$min, $max], $fxKind];
    }

    private function fxPriceField(array $invoice, string $kind): string
    {
        if (in_array($invoice['name_product'] ?? '', ['🛍 حجم دلخواه', '⚙️ سرویس دلخواه'], true)) {
            return $kind === 'time' ? 'pricecustomtime' : 'pricecustomvolume';
        }
        return $kind === 'time' ? 'priceextratime' : 'priceextravolume';
    }

    private function jsonAgentValue($raw, string $agent, $default = '')
    {
        if (is_array($raw)) return $raw[$agent] ?? $default;
        if (!is_string($raw) || $raw === '') return $default;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return $default;
        return $decoded[$agent] ?? $default;
    }

    private function reportError(string $text): void
    {
        $channel = (string)($this->setting['Channel_Report'] ?? '');
        if ($channel === '') return;
        $errorRow = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'errorreport', 'select');
        $topic = is_array($errorRow) ? (string)($errorRow['idreport'] ?? '') : '';
        try {
            telegram('sendmessage', [
                'chat_id'           => $channel,
                'message_thread_id' => $topic,
                'text'              => $text,
                'parse_mode'        => 'HTML',
            ]);
        } catch (Throwable $e) {  }
    }
}

