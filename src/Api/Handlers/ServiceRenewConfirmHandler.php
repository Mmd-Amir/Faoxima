<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use InvalidArgumentException;
use ManagePanel;
use RuntimeException;
use Throwable;

final class ServiceRenewConfirmHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $username = \Faoxima\Api\Runtime\Input::string($this->data, 'username');
        if ($username === '') {
            \Faoxima\Api\Runtime\Response::badRequest('username is required');
        }


        $invoice = \Faoxima\Api\Runtime\Db::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            \Faoxima\Api\Runtime\Response::notFound('Service not found');
        }

        if (!in_array((string)$invoice['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_status_invalid_restart', '❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.'));
        }

        $requestId = \Faoxima\Api\Runtime\Input::string($this->data, 'operation_id');
        if ($requestId !== '' && !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $requestId)) {
            \Faoxima\Api\Runtime\Response::badRequest('شناسه عملیات نامعتبر است');
        }
        $discountOperationId = $requestId === '' ? bin2hex(random_bytes(16))
            : substr(hash('sha256', 'miniapp_renewal:' . $this->user['id'] . ':' . $invoice['id_invoice'] . ':' . $requestId), 0, 32);
        $existingRenewal = \Faoxima\Services\Fulfillment\WalletExtensions::blockingOperation(
            \Faoxima\Api\Runtime\Db::pdo(), (string) $this->user['id'], $invoice['username'], 'wallet_renewal',
            (string) $invoice['id_invoice'], $requestId === '' ? '' : $discountOperationId
        );
        if ($existingRenewal !== null) {
            \Faoxima\Api\Runtime\Response::fail(409, 'درخواست تمدید قبلاً ثبت شده است.', ['operation_id' => $existingRenewal]);
            return;
        }

        $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (empty($panel)) {
            \Faoxima\Api\Runtime\Response::notFound('Panel not found');
        }
        if (($panel['status_extend'] ?? '') === 'off_extend') {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_extend_unavailable_panel', '❌ امکان تمدید در این پنل وجود ندارد'));
        }

        $nationalPanel = method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel);

        $panel['_custom_quote_user'] = [(string)$this->user['id'], (string)($this->user['agent'] ?? 'f'), (int)($this->user['pricediscount'] ?? 0)];
        $agent = (string)($this->user['agent'] ?? 'f');


        $custom = \Faoxima\Api\Runtime\Input::array($this->data, 'custom');
        if (!empty($custom)) {
            $product = $this->buildCustomProduct($panel, $custom, $agent);
        } else {
            $code = \Faoxima\Api\Runtime\Input::string($this->data, 'product_code');
            if ($code === '') {
                \Faoxima\Api\Runtime\Response::badRequest('product_code or custom is required');
            }
            $row = \Faoxima\Api\Runtime\Db::fetchOne(
                "SELECT * FROM product
                  WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND code_product = :code
                    AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))
                  LIMIT 1",
                [':loc' => $invoice['Service_location'], ':code' => $code, ':agent' => $agent]
            );
            if (!is_array($row)) {
                \Faoxima\Api\Runtime\Response::fail(404, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_error_restart', '❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.'));
            }
            if (!$this->productIsAllowedForAgent($row, $agent)) {
                \Faoxima\Api\Runtime\Response::fail(403, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_product_not_allowed_for_agent', 'این محصول برای نوع کاربری شما فعال نیست'));
            }
            $product = $row;
        }

        $reservation = null;
        if (!$nationalPanel) {
            $managePanelCheck = new ManagePanel();
            $snapshotCheck = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($managePanelCheck, (string)$panel['name_panel'], (string)$invoice['username'], (string)($panel['type'] ?? ''));
            if (\Faoxima\Services\Renewal\Reservations::rxReservationUiEnabled($panel)) {
                $usableSnapshot = !empty($snapshotCheck['ok']) && !empty($snapshotCheck['exists']) ? $snapshotCheck : null;
                $reservation = \Faoxima\Services\Renewal\ReservationCriteria::reservationPanelSelection($panel);
                $selectionCheck = \Faoxima\Services\Renewal\ReservationCriteria::reservationNormalizeSelection($reservation, $usableSnapshot, time());
                if (empty($selectionCheck['ok'])) {
                    \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('api_confirm_selection', (string)$selectionCheck['reason'], ['panel' => $panel, 'username' => (string)$invoice['username'], 'invoice_id' => (string)$invoice['id_invoice'], 'invoice_status' => $invoice['Status'] ?? null, 'method' => (string)($panel['Methodextend'] ?? ''), 'volume_gb' => $product['Volume_constraint'] ?? null, 'days' => $product['Service_time'] ?? null, 'snapshot' => $snapshotCheck, 'detail' => $selectionCheck['detail'] ?? null, 'mutation' => 'none']);
                    \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText((string)$selectionCheck['reason'], $selectionCheck));
                }
            }
            $previewCheck = \Faoxima\Services\Renewal\ProviderRenewal::renewalPreviewForService(\Faoxima\Api\Runtime\Db::pdo(), $managePanelCheck, $panel, (string)$invoice['username'], $product['Volume_constraint'] ?? 0, $product['Service_time'] ?? 0, [
                'reservation' => $reservation,
                'snapshot' => $snapshotCheck,
                'id_invoice' => (string)$invoice['id_invoice'],
                'stage' => 'api_confirm',
            ]);
            if (!empty($previewCheck['blocked'])) {
                \Faoxima\Api\Runtime\Response::fail(409, (string)($previewCheck['text'] ?? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reason_generic')));
            }
        }

        $fxKinds = !empty($custom) ? ['custom_volume', 'custom_time'] : 'product';
        $fxBasePrice = !empty($custom)
            ? \Faoxima\Orders\Pricing\ExchangePricing::fxCustomBasePrice($panel, $agent, $product['Volume_constraint'] ?? 0, $product['Service_time'] ?? 0)
            : $product['price_product'];
        if (empty($custom)) {
            $product = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($product, $panel);
        }
        $product['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($product['price_product'], $panel, $fxKinds);
        if (!empty($custom)) {
            try {
                $panel = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_api_quote_panel($panel, $this->user, $product, 'extend', \Faoxima\Api\Runtime\Input::string($this->data, 'discount_code'));
            } catch (InvalidArgumentException $e) {
                \Faoxima\Api\Runtime\Response::badRequest($e->getMessage());
            }
        }
        if (\Faoxima\Orders\Pricing\CustomDuration::fx_custom_quote_context($panel, $fxKinds) !== null && !\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteTokenValid(\Faoxima\Api\Runtime\Input::string($this->data, 'fx_quote'), $panel, $fxKinds)) {
            $fxDisplayPrice = empty($custom)
                ? \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount((int) round(\Faoxima\Orders\Pricing\ExchangePricing::fxApplyUserDiscount((float) $product['price_product'], (int)($this->user['pricediscount'] ?? 0))), $panel, $fxKinds)
                : $panel['_custom_quote_total'];
            $changed = \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedApiObj($fxDisplayPrice, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteToken($panel, $fxKinds));
            if (!empty($custom)) {
                $changed['price_per_gb'] = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_resolve($panel, $this->user['agent'] ?? 'f', $product['Volume_constraint'], $product['Service_time'])['price_per_gb'];
                $changed['duration_pricing'] = \Faoxima\Orders\Pricing\CustomDuration::fx_duration_config($panel, $this->user['agent'] ?? 'f')['enabled'];
            }
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedApiMessage(), $changed);
        }
        $fxListPrice = (float) $product['price_product'];


        $discountCode = \Faoxima\Api\Runtime\Input::string($this->data, 'discount_code');
        if ($discountCode !== '') {
            $dv = \Faoxima\Orders\Discounts\DiscountRules::validateSellForCategories(\Faoxima\Api\Runtime\Db::pdo(),
                $discountCode,
                'extend',
                (string)($product['code_product'] ?? ''),
                (string)($panel['code_panel'] ?? ''),
                \Faoxima\Orders\Catalog\NameCascades::nmProductCategoryList($product),
                $this->user
            );
            if (empty($dv['ok'])) {
                \Faoxima\Api\Runtime\Response::fail(422, (string)($dv['reason'] ?? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_invalid_discount_code', '❌ کد تخفیف نامعتبر است.')));
            }
            $discPriceBefore = (float)$product['price_product'];
            $product['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(\Faoxima\Orders\Discounts\DiscountRules::applyToPrice($dv['row'], $discPriceBefore), $panel, $fxKinds);
            \Faoxima\Orders\Discounts\DiscountRules::logOrderDiscount(\Faoxima\Api\Runtime\Db::pdo(), [
                'id_user' => $this->user['id'],
                'id_invoice' => $invoice['id_invoice'] ?? null,
                'code' => $discountCode,
                'kind' => 'sell',
                'value_type' => $dv['value_type'],
                'value_raw' => $dv['value'],
                'price_before' => $discPriceBefore,
                'discount_amount' => $discPriceBefore - (float)$product['price_product'],
                'price_after' => $product['price_product'],
                'section' => 'extend',
            ]);
        }


        $discount = (int)($this->user['pricediscount'] ?? 0);
        $finalPrice = (float)$product['price_product'];
        if ($discount !== 0) {
            $finalPrice = $finalPrice - (($finalPrice * $discount) / 100);
        }
        $finalPrice = (int) round($finalPrice);
        $finalPrice = (int) \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($finalPrice, $panel, $fxKinds);
        $fxSnapshot = \Faoxima\Orders\Pricing\ExchangePricing::fxPricingSnapshot($panel, $fxKinds, $fxBasePrice, $finalPrice, $fxListPrice - $finalPrice, ['product' => (string)($product['code_product'] ?? ''), 'source_channel' => 'miniapp']);


        if (!empty($custom)) {
            $fxSnapshot = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_snapshot($panel, $agent, $product['Volume_constraint'], $product['Service_time'], $finalPrice, $fxSnapshot);
        }

        $maxBuyAgent = (int)($this->user['maxbuyagent'] ?? 0);
        $balance = (float)($this->user['Balance'] ?? 0);
        if ($maxBuyAgent !== 0 && $agent === 'n2') {
            if (($balance - $finalPrice) < (-1 * $maxBuyAgent)) {
                \Faoxima\Api\Runtime\Response::fail(403, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_serviceaction_purchase_limit_exhausted', '❌ مبلغ مجاز خرید شما به اتمام رسیده است.'));
            }
        }


        $walletState = is_callable([\Faoxima\Orders\Checkout\Checkout::class, 'available']) ? \Faoxima\Orders\Checkout\Checkout::available(\Faoxima\Api\Runtime\Db::pdo(), $this->user['id']) : ['balance' => (int) $balance, 'available' => (int) $balance];
        $shortfall = $finalPrice - (float) $walletState['available'];
        if ($shortfall > 0 && $agent !== 'n2') {
            $directBuyEnabled = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($panel, 'directbuy');
            if (!$directBuyEnabled) {
                \Faoxima\Api\Runtime\Response::fail(402, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_insufficient_balance', '❌ موجودی کیف پول شما کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.'));
            }


            $orderId = bin2hex(random_bytes(4));

            $idOrder = bin2hex(random_bytes(2));
            $soCode = \Faoxima\Services\Renewal\Plans::renewalNormalizeProductCode((string)$product['code_product']);

            $oldDataLimit = '';
            $oldExpire = '';
            try {
                $mp = new ManagePanel();
                $remote = $mp->DataUser($invoice['Service_location'], $invoice['username']);
                if (is_array($remote)) {
                    $oldDataLimit = (string)($remote['data_limit'] ?? '');
                    $oldExpire    = (string)($remote['expire'] ?? '');
                }
            } catch (Throwable $e) {
            }

            $soValue = json_encode([
                'volumebuy'    => (int)$product['Volume_constraint'],
                'Service_time' => (int)$product['Service_time'],
                'oldvolume'    => $oldDataLimit,
                'oldtime'      => $oldExpire,
                'code_product' => $soCode,
                'id_order'     => $idOrder,
                'reservation'  => $reservation,
            ], JSON_UNESCAPED_UNICODE);
            $soValue = \Faoxima\Orders\Pricing\ExchangePricing::fxValueWithSnapshot($soValue, $fxSnapshot);

            try {
                $pdo = \Faoxima\Api\Runtime\Db::pdo();
                $stmt = $pdo->prepare(
                    "INSERT IGNORE INTO service_other
                        (id_user, username, value, type, time, price, output, status)
                     VALUES (:u, :n, :v, 'extend_user', :t, :p, '', 'unpaid')"
                );
                $stmt->execute([
                    ':u' => $this->user['id'],
                    ':n' => $invoice['username'],
                    ':v' => $soValue,
                    ':t' => date('Y/m/d H:i:s'),
                    ':p' => (int)round((float)$product['price_product']),
                ]);
            } catch (Throwable $e) {
                \Faoxima\Api\Runtime\Response::fail(500, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_request_save_failed', '❌ خطا در ثبت درخواست تمدید. لطفاً دوباره تلاش کنید.'));
            }

            $opened = $this->openCheckout('getextenduser', 'getextenduser|' . $invoice['username'] . '%' . $idOrder, (string) $invoice['username'], (int) $finalPrice, ['id_order' => $idOrder]);
            $amountDue = (int) $opened['checkout']['external_amount'];

            \Faoxima\Core\Database\Db::update('user', 'Processing_value',      $amountDue,                                'id', $this->user['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_one',  $invoice['username'] . '%' . $idOrder,     'id', $this->user['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow',  'getextenduser',                           'id', $this->user['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_four', '',                                        'id', $this->user['id']);


            \Faoxima\Api\Runtime\Response::ok(array_merge($this->checkoutResponseFields($opened), [
                'kind'        => 'requires_payment',
                'amount_due'  => $amountDue,
                'balance'     => $balance,
                'price'       => $finalPrice,
                'username'    => (string)$invoice['username'],
                'order_id'    => $orderId,
                'product'     => [
                    'name'        => (string)$product['name_product'],
                    'code'        => (string)$product['code_product'],
                    'volume_gb'   => (int)$product['Volume_constraint'],
                    'time_days'   => (int)$product['Service_time'],
                    'price'       => $finalPrice,
                ],
                'message'     => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_insufficient_balance_pay_shortfall', 'موجودی کیف پول کافی نیست. لطفاً مبلغ کسری را پرداخت کنید.'),
            ]));
            return;
        }


        $cashback = 0;
        if ($finalPrice > 0) {
            $cashbackKey = $agent === 'f' ? 'chashbackextend' : 'chashbackextend_agent';
            $row = \Faoxima\Core\Database\Db::select('shopSetting', '*', 'Namevalue', $cashbackKey, 'select');
            $rawCashback = is_array($row) ? (string)($row['value'] ?? '') : '';
            $rate = 0;
            if ($agent === 'f') {
                $rate = (int)$rawCashback;
            } else {
                $decoded = json_decode($rawCashback, true);
                if (is_array($decoded)) {
                    $rate = (int)($decoded[$agent] ?? 0);
                }
            }
            $renewCashbackEligible = !is_callable([\Faoxima\Cashback\Eligibility::class, 'shopCashbackEligible'])
                || \Faoxima\Cashback\Eligibility::shopCashbackEligible(\Faoxima\Api\Runtime\Db::pdo(), 'chashbackextend', $this->user['register'] ?? null, 'getextenduser');
            if ($renewCashbackEligible && $rate > 0) {
                $cashback = (int) round(($product['price_product'] * $rate) / 100);
                $finalPrice = $finalPrice - $cashback;
                if ($finalPrice < 0) $finalPrice = 0;
            }
        }


        $pdo = \Faoxima\Api\Runtime\Db::pdo();
        $funding = \Faoxima\Services\Renewal\WalletRenewal::reserve(
            $pdo, $this->user, $invoice, $discountOperationId, $finalPrice, $discountCode,
            \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_ledger_debit_note', 'تمدید سرویس')
        );
        if (empty($funding['ok'])) {
            $reason = (string) ($funding['reason'] ?? '');
            if (!empty($funding['blocking_operation'])) {
                \Faoxima\Api\Runtime\Response::fail(409, 'درخواست تمدید قبلاً ثبت شده است.', ['operation_id' => $funding['blocking_operation']]);
                return;
            }
            if (str_starts_with($reason, 'discount_')) {
                \Faoxima\Api\Runtime\Response::fail(409, 'کد تخفیف دیگر قابل استفاده نیست. لطفاً تمدید را دوباره انجام دهید.');
                return;
            }
            if ($reason === 'insufficient_balance') {
                \Faoxima\Api\Runtime\Response::fail(402, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_concurrent_balance_conflict', 'موجودی کافی نیست.'));
                return;
            }
            \Faoxima\Api\Runtime\Response::fail(503, 'برداشت برای تمدید تکمیل نشد؛ با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $discountOperationId]);
            return;
        }
        if (!\Faoxima\Services\Renewal\WalletRenewal::claim($pdo, $funding)) {
            \Faoxima\Api\Runtime\Response::fail(503, 'وضعیت ثبت تمدید در حال بررسی است.', ['operation_id' => $discountOperationId]);
            return;
        }
        $newBalance = $funding['new_balance'];

        if ($nationalPanel) {
            $stockNew = method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockReserveForProduct')
                ? \Faoxima\Services\Fulfillment\Inventory::nmStockReserveForProduct(\Faoxima\Api\Runtime\Db::pdo(), $panel, $product, $this->user['id'], $invoice['id_invoice'], 'miniapp_extend_national_stock')
                : false;
            if (!is_array($stockNew) || (string)($stockNew['content'] ?? '') === '') {
                if (is_array($stockNew) && is_callable([\Faoxima\Services\Fulfillment\Inventory::class, 'nmStockReleaseReservation'])) \Faoxima\Services\Fulfillment\Inventory::nmStockReleaseReservation(\Faoxima\Api\Runtime\Db::pdo(), $stockNew);
                try {
                    if (!\Faoxima\Services\Renewal\WalletRenewal::result($pdo, $funding, 'refundable')) {
                        throw new RuntimeException('Cannot record stock refund eligibility');
                    }
                    \Faoxima\Services\Renewal\WalletRenewal::refund($pdo, $funding, (string) $this->user['id'],
                        \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_stock_refund_note', 'بازگشت وجه به دلیل خطای انبار'));
                } catch (Throwable $error) {
                    \Faoxima\Api\Runtime\Logger::error('Renewal stock refund needs review', ['operation_id' => $discountOperationId]);
                    \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_extend_error', '❌ خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید'));
                    return;
                }
                \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_stock_depleted', '❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.'));
            }

            $now = time();
            \Faoxima\Core\Database\Db::update('invoice', 'name_product',  $product['name_product'],      'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'price_product', $product['price_product'],     'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'Volume',        $product['Volume_constraint'], 'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'Service_time',  $product['Service_time'],      'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'Status',        'active',                      'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'time_sell',     $now,                          'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'user_info',     $stockNew['content'],          'id_invoice', $invoice['id_invoice']);
            if ($discountCode !== '' && isset($discPriceBefore)) {
                \Faoxima\Core\Database\Db::update('invoice', 'discount_code', $discountCode, 'id_invoice', $invoice['id_invoice']);
                \Faoxima\Core\Database\Db::update('invoice', 'discount_amount', (string)($discPriceBefore - (float)$product['price_product']), 'id_invoice', $invoice['id_invoice']);
                \Faoxima\Core\Database\Db::update('invoice', 'price_before_discount', (string)$discPriceBefore, 'id_invoice', $invoice['id_invoice']);
            }
            try { \Faoxima\Core\Database\Db::update('invoice', 'source_panel_code', $panel['code_panel'], 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) {}

            if (!empty($stockNew['id']) && method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockMarkDelivered')) {
                \Faoxima\Services\Fulfillment\Inventory::nmStockMarkDelivered(\Faoxima\Api\Runtime\Db::pdo(), $stockNew['id'], $this->user['id'], $invoice['id_invoice'], ['mode' => 'miniapp_extend_national_stock']);
            }

            \Faoxima\Orders\Discounts\DiscountRules::logSale(\Faoxima\Api\Runtime\Db::pdo(), [
                'id_user' => $this->user['id'],
                'id_invoice' => $invoice['id_invoice'] ?? null,
                'Service_location' => $invoice['Service_location'],
                'kind' => 'renewal',
                'name_product' => $product['name_product'],
                'amount' => $finalPrice,
                'source' => 'miniapp',
            ]);

            if ((int)($this->setting['scorestatus'] ?? 0) === 1) {
                \Faoxima\Core\Database\Db::update('user', 'score', (int)($this->user['score'] ?? 0) + 2, 'id', $this->user['id']);
            }

            $this->reportSuccess(
                \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_national_stock_report_tpl', "✅ <b>تمدید سرویس (انبار شبکه‌ملی)</b>\n<blockquote>▫️آیدی کاربر : {user_id}</blockquote>\n<blockquote>▫️نام کاربری سرویس : {username}</blockquote>\n<blockquote>▫️محصول : {product_name}</blockquote>\n<blockquote>▫️حجم : {volume} گیگ</blockquote>\n<blockquote>▫️زمان : {service_time} روز</blockquote>\n<blockquote>▫️مبلغ : {price} تومان</blockquote>\n<blockquote>▫️پنل : {panel_name}</blockquote>"), [
                    'user_id' => $this->user['id'],
                    'username' => $invoice['username'],
                    'product_name' => $product['name_product'],
                    'volume' => (int)$product['Volume_constraint'],
                    'service_time' => (int)$product['Service_time'],
                    'price' => \Faoxima\Core\Support\Formatting::rxFormatToman($finalPrice),
                    'panel_name' => $panel['name_panel'],
                ]),
                [
                    'user_id'    => $this->user['id'],
                    'amount'     => $product['name_product'],
                    'price'      => number_format((float)$finalPrice),
                    'panel_name' => $panel['name_panel'] ?? '',
                    'category'   => $product['category'] ?? '',
                ]
            );

            \Faoxima\Services\Renewal\WalletRenewal::result($pdo, $funding, 'applied');
            \Faoxima\Api\Runtime\Response::ok([
                'kind'          => 'done',
                'message'       => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_national_stock_success', '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد.'),
                'cashback'      => $cashback,
                'balance_after' => $newBalance,
                'product'       => [
                    'name'      => (string)$product['name_product'],
                    'code'      => (string)$product['code_product'],
                    'time_days' => (int)$product['Service_time'],
                    'price'     => $finalPrice,
                ],
            ]);
            return;
        }


        $managePanel = new ManagePanel();
        $renewRef = $discountOperationId;


        try {
            $providerProduct = $product;
            $providerProduct['Volume_constraint'] = (int) $product['Volume_constraint'];
            $providerProduct['Service_time'] = (int) $product['Service_time'];
            $providerProduct['code_product'] = (string) $product['code_product'];
            $extend = \Faoxima\Services\Renewal\ProviderRenewal::apply(
                $managePanel, $panel, $providerProduct, (string) $invoice['username'], null,
                [
                    'source' => 'miniapp',
                    'ref' => $renewRef,
                    'op_id' => $renewRef,
                    'id_user' => $this->user['id'],
                    'price' => $finalPrice,
                    'reservation' => array_merge(is_array($reservation) ? $reservation : [], ['payment_ref' => $renewRef]),
                ]
            );
        } catch (Throwable $error) {
            \Faoxima\Api\Runtime\Logger::exception($error, 'Renewal provider outcome uncertain', [
                'user_id' => $this->user['id'],
                'username' => $invoice['username'] ?? null,
                'operation_id' => $discountOperationId,
                'provider_ref' => $renewRef,
            ]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_extend_error', '❌ خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید'));
            return;
        }

        $outcome = \Faoxima\Services\Renewal\ProviderRenewal::walletRenewalOutcome($extend);
        $resultSaved = \Faoxima\Services\Renewal\WalletRenewal::result($pdo, $funding, $outcome, empty($extend['queued']));
        if (!$resultSaved) {
            \Faoxima\Api\Runtime\Response::fail(503, 'وضعیت ثبت تمدید در حال بررسی است.', ['operation_id' => $discountOperationId]);
            return;
        }
        $renewPending = $outcome === 'pending';
        if ($outcome === 'refundable' || $outcome === 'uncertain') {
            if ($outcome === 'refundable' && $resultSaved) {
                try {
                    \Faoxima\Services\Renewal\WalletRenewal::refund($pdo, $funding, (string) $this->user['id'],
                        \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_extend_refund_note', 'بازگشت وجه به دلیل خطای تمدید سرویس'));
                } catch (Throwable $error) {
                    \Faoxima\Api\Runtime\Logger::error('Renewal refund needs review', ['operation_id' => $discountOperationId]);
                    \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_extend_error', '❌ خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید'));
                    return;
                }
            }
            $reason = is_array($extend) ? \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extend['msg'] ?? $extend, $extend['detail'] ?? null) : htmlspecialchars((string) $extend, ENT_QUOTES, 'UTF-8');
            \Faoxima\Api\Runtime\Logger::error('Renewal was not confirmed', [
                'user_id' => $this->user['id'], 'panel' => $panel['name_panel'], 'username' => $invoice['username'],
                'outcome' => $outcome, 'operation_id' => $discountOperationId, 'provider_ref' => $renewRef, 'reason' => $reason,
            ]);
            $this->reportError(
                \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_extend_failed_report_tpl', "خطای تمدید سرویس\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>دلیل خطا : {reason}</blockquote>"), [
                    'panel_name' => $panel['name_panel'],
                    'username' => \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($invoice['username'], $panel),
                    'reason' => $reason,
                ])
            );

            if ($outcome === 'refundable' && is_array($extend) && ($extend['code'] ?? '') === 'manual_stock_empty') {
                \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_manual_stock_empty', '❌ موجودی انبار برای این محصول تمام شده است. مبلغی از حساب شما کسر نشد.'));
            }

            if ($outcome === 'refundable' && is_array($extend) && ($extend['code'] ?? '') === 'queued_renewal_exists') {
                \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_queued_renewal_exists', '❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد.'));
            }

            if ($outcome === 'refundable' && \Faoxima\Services\Renewal\ProviderRenewal::renewalFailureKnown($extend)) {
                \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Services\Renewal\RenewalText::rxRenewalFailureText($extend));
            }

            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_extend_error', '❌ خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید'));
            return;
        }


        try {
            $orderRand = bin2hex(random_bytes(2));
            $oldDataLimit = '';
            $oldExpire = '';
            if (is_array($extend['rx_renewal']['before'] ?? null)) {
                $oldDataLimit = (string)($extend['rx_renewal']['before']['data_limit'] ?? '');
                $oldExpire    = (string)($extend['rx_renewal']['before']['expire'] ?? '');
            } elseif (is_array($snapshotCheck ?? null) && !empty($snapshotCheck['ok']) && !empty($snapshotCheck['exists'])) {
                $oldDataLimit = (string)($snapshotCheck['data_limit'] ?? '');
                $oldExpire    = (string)($snapshotCheck['expire'] ?? '');
            }

            $value = json_encode([
                'volumebuy'    => (int)$product['Volume_constraint'],
                'Service_time' => (int)$product['Service_time'],
                'oldvolume'    => $oldDataLimit,
                'oldtime'      => $oldExpire,
                'code_product' => \Faoxima\Services\Renewal\Plans::renewalNormalizeProductCode((string)$product['code_product']),
                'id_order'     => $orderRand,
                'reservation'  => $reservation,
            ], JSON_UNESCAPED_UNICODE);
            $value = \Faoxima\Orders\Pricing\ExchangePricing::fxValueWithSnapshot($value, $fxSnapshot);

            $pdo = \Faoxima\Api\Runtime\Db::pdo();
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO service_other
                    (id_user, username, value, type, time, price, output, status)
                 VALUES (:u, :n, :v, 'extend_user', :t, :p, :o, 'paid')"
            );
            $stmt->execute([
                ':u' => $this->user['id'],
                ':n' => $invoice['username'],
                ':v' => $value,
                ':t' => date('Y/m/d H:i:s'),
                ':p' => (int)$product['price_product'],
                ':o' => json_encode($extend, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::warn('service_other insert failed (extend)', ['err' => $e->getMessage()]);
        }


        $renewApplied = !$renewPending && empty($extend['queued']);
        \Faoxima\Orders\Discounts\DiscountRules::logSale(\Faoxima\Api\Runtime\Db::pdo(), [
            'id_user' => $this->user['id'],
            'id_invoice' => $invoice['id_invoice'] ?? null,
            'Service_location' => $invoice['Service_location'],
            'kind' => 'renewal',
            'name_product' => $product['name_product'],
            'amount' => $finalPrice,
            'source' => 'miniapp',
        ]);

        if ($discountCode !== '' && isset($discPriceBefore)) {
            \Faoxima\Core\Database\Db::update('invoice', 'discount_code', $discountCode, 'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'discount_amount', (string)($discPriceBefore - (float)$product['price_product']), 'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'price_before_discount', (string)$discPriceBefore, 'id_invoice', $invoice['id_invoice']);
        }

        if ($renewApplied && ($panel['type'] ?? '') === 'Manualsale') {
            \Faoxima\Core\Database\Db::update('invoice', 'name_product',  $product['name_product'],      'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'price_product', $product['price_product'],     'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'Volume',        $product['Volume_constraint'], 'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'Service_time',  $product['Service_time'],      'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'time_sell',     time(),                        'id_invoice', $invoice['id_invoice']);
        }


        if ($renewApplied && ($invoice['name_product'] ?? '') === 'سرویس تست') {
            \Faoxima\Core\Database\Db::update('invoice', 'name_product',  $product['name_product'],  'id_invoice', $invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice', 'price_product', $product['price_product'], 'id_invoice', $invoice['id_invoice']);
        } elseif ($renewApplied && \Faoxima\Services\Renewal\Plans::renewalNormalizeProductCode((string)($product['code_product'] ?? '')) !== 'custom_volume' && \Faoxima\Services\Renewal\ProviderRenewal::renewalIsAppliedVerified($extend)) {
            \Faoxima\Services\Renewal\ProviderRenewal::renewalSyncInvoiceName($invoice['id_invoice'] ?? '', $product['name_product'] ?? '');
        }


        if ((int)($this->setting['scorestatus'] ?? 0) === 1) {
            $newScore = (int)($this->user['score'] ?? 0) + 2;
            \Faoxima\Core\Database\Db::update('user', 'score', $newScore, 'id', $this->user['id']);
        }

        $this->reportSuccess(
            \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_report_tpl', "✅ <b>تمدید سرویس</b>\n<blockquote>▫️آیدی کاربر : {user_id}</blockquote>\n<blockquote>▫️نام کاربری سرویس : {username}</blockquote>\n<blockquote>▫️محصول : {product_name}</blockquote>\n<blockquote>▫️حجم : {volume} گیگ</blockquote>\n<blockquote>▫️زمان : {service_time} روز</blockquote>\n<blockquote>▫️مبلغ : {price} تومان</blockquote>\n<blockquote>▫️پنل : {panel_name}</blockquote>"), [
                'user_id' => $this->user['id'],
                'username' => \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($invoice['username'], $panel),
                'product_name' => $product['name_product'],
                'volume' => (int)$product['Volume_constraint'],
                'service_time' => (int)$product['Service_time'],
                'price' => \Faoxima\Core\Support\Formatting::rxFormatToman($finalPrice),
                'panel_name' => $panel['name_panel'],
            ]),
            [
                'user_id'    => $this->user['id'],
                'amount'     => $product['name_product'],
                'price'      => number_format((float)$finalPrice),
                'panel_name' => $panel['name_panel'] ?? '',
                'category'   => $product['category'] ?? '',
            ]
        );

        \Faoxima\Api\Runtime\Logger::debug('Inline renewal completed', [
            'user_id'   => $this->user['id'],
            'username'  => $invoice['username'],
            'product'   => $product['code_product'] ?? null,
            'amount'    => $finalPrice,
            'cashback'  => $cashback,
        ]);


        if (!empty($extend['queued'])) {
            $renewMessage = (string)($extend['reservation']['confirmation'] ?? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_confirmed'));
        } else {
            $renewMessage = \Faoxima\Services\Renewal\RenewalText::rxRenewalDecorateSuccess(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_success', '✅ سرویس شما با موفقیت تمدید شد.'), $extend, (string)$invoice['username'], (string)($panel['name_panel'] ?? ''));
        }

        if (!empty($custom)) {
            $renewMessage .= \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($panel, $agent, $product['Volume_constraint'], $product['Service_time'], $finalPrice);
        }

        \Faoxima\Api\Runtime\Response::ok([
            'kind'          => 'done',
            'state'         => $renewPending ? 'pending_verification' : (!empty($extend['queued']) ? 'reserved' : 'applied'),
            'operation_id'  => $extend['operation_id'] ?? null,
            'reservation'   => $extend['reservation'] ?? null,
            'message'       => $renewMessage,
            'cashback'      => $cashback,
            'balance_after' => $newBalance,
            'product'       => [
                'name'      => (string)$product['name_product'],
                'code'      => (string)$product['code_product'],
                'volume_gb' => (int)$product['Volume_constraint'],
                'time_days' => (int)$product['Service_time'],
                'price'     => $finalPrice,
            ],
        ]);
    }


    private function buildCustomProduct(array $panel, array $custom, string $agent): array
    {
        $volume = (int)($custom['volume_gb'] ?? 0);
        $time   = (int)($custom['time_days']  ?? 0);

        $minVol  = (int) $this->jsonAgentValue($panel['mainvolume'] ?? '', $agent);
        $maxVol  = (int) $this->jsonAgentValue($panel['maxvolume']  ?? '', $agent);
        $minTime = (int) $this->jsonAgentValue($panel['maintime']   ?? '', $agent);
        $maxTime = (int) $this->jsonAgentValue($panel['maxtime']    ?? '', $agent);

        if ($volume > $maxVol || $volume < $minVol) {
            \Faoxima\Api\Runtime\Response::badRequest(\Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_invalid_volume_range_tpl', '❌ حجم نامعتبر است (بین {min} و {max} گیگابایت)'), ['min' => $minVol, 'max' => $maxVol]));
        }
        if ($time > $maxTime || $time < $minTime) {
            \Faoxima\Api\Runtime\Response::badRequest(\Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_invalid_time_range_tpl', '❌ زمان نامعتبر است (بین {min} و {max} روز)'), ['min' => $minTime, 'max' => $maxTime]));
        }

        try {
            $durationPricing = \Faoxima\Orders\Pricing\CustomDuration::fx_duration_config($panel, $agent)['enabled'];
            $price = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price(\Faoxima\Orders\Pricing\CustomDuration::fx_custom_renew_panel($panel, $agent), $agent, $durationPricing ? ($custom['volume_gb'] ?? 0) : $volume, $durationPricing ? ($custom['time_days'] ?? 0) : $time);
        } catch (InvalidArgumentException $e) {
            \Faoxima\Api\Runtime\Response::badRequest($e->getMessage());
        }

        return [
            'code_product'      => 'customvolume',
            'name_product'      => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_custom_service_name', '⚙️ سرویس دلخواه'),
            'Volume_constraint' => $volume,
            'Service_time'      => $time,
            'Location'          => $panel['name_panel'],
            'agent'             => $agent,
            'price_product'     => $price,
        ];
    }

    private function jsonAgentValue($raw, string $agent, $default = '')
    {
        if (is_array($raw)) {
            return $this->pickAgent($raw, $agent, $default);
        }
        if (!is_string($raw)) return $default;
        $raw = trim($raw);
        if ($raw === '') return $default;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return is_numeric($raw) ? $raw : $default;
        }
        return $this->pickAgent($decoded, $agent, $default);
    }

    private function pickAgent(array $map, string $agent, $default)
    {
        foreach ([$agent, 'allusers', 'f'] as $key) {
            if (array_key_exists($key, $map)) {
                $val = $map[$key];
                if ($val !== '' && $val !== null) {
                    return $val;
                }
            }
        }
        return $default;
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

    private function reportSuccess(string $text, ?array $publicLogVars = null): void
    {
        if ($publicLogVars !== null && method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
            \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('renewal', $publicLogVars, $this->setting);
        }

        $channel = (string)($this->setting['Channel_Report'] ?? '');
        if ($channel === '') return;
        $row = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'buyreport', 'select');
        $topic = is_array($row) ? (string)($row['idreport'] ?? '') : '';
        $reply = json_encode([
            'inline_keyboard' => [[
                ['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewconfirm_manage_user_btn', '👤 مدیریت کاربر'), 'callback_data' => 'manageuser_' . $this->user['id']],
            ]],
        ]);
        try {
            \Faoxima\Telegram\Support\Transport::rx_sendTopicReport([
                'chat_id'           => $channel,
                'message_thread_id' => $topic,
                'text'              => $text,
                'parse_mode'        => 'HTML',
                'reply_markup'      => $reply,
            ], ['flow' => 'miniapp_renew', 'user_id' => (string)($this->user['id'] ?? '')]);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::warn('renew success report failed', ['err' => $e->getMessage()]);
        }
    }
}
