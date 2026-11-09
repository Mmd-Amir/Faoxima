<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/DiscountSupport.php';

final class ServiceRenewConfirmHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $username = FaoximaInput::string($this->data, 'username');
        if ($username === '') {
            FaoximaResponse::badRequest('username is required');
        }


        $invoice = FaoximaDb::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            FaoximaResponse::notFound('Service not found');
        }

        if (!in_array((string)$invoice['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_renewconfirm_status_invalid_restart', '❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.'));
        }

        $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (empty($panel)) {
            FaoximaResponse::notFound('Panel not found');
        }
        if (($panel['status_extend'] ?? '') === 'off_extend') {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_renewconfirm_extend_unavailable_panel', '❌ امکان تمدید در این پنل وجود ندارد'));
        }

        $nationalPanel = function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel);

        $panel['_custom_quote_user'] = [(string)$this->user['id'], (string)($this->user['agent'] ?? 'f'), (int)($this->user['pricediscount'] ?? 0)];
        $agent = (string)($this->user['agent'] ?? 'f');


        $custom = FaoximaInput::array($this->data, 'custom');
        if (!empty($custom)) {
            $product = $this->buildCustomProduct($panel, $custom, $agent);
        } else {
            $code = FaoximaInput::string($this->data, 'product_code');
            if ($code === '') {
                FaoximaResponse::badRequest('product_code or custom is required');
            }
            $row = FaoximaDb::fetchOne(
                "SELECT * FROM product
                  WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND code_product = :code
                    AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))
                  LIMIT 1",
                [':loc' => $invoice['Service_location'], ':code' => $code, ':agent' => $agent]
            );
            if (!is_array($row)) {
                FaoximaResponse::fail(404, faoxima_textbot_get('dyn_renewconfirm_error_restart', '❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.'));
            }
            if (!$this->productIsAllowedForAgent($row, $agent)) {
                FaoximaResponse::fail(403, faoxima_textbot_get('dyn_purchase_product_not_allowed_for_agent', 'این محصول برای نوع کاربری شما فعال نیست'));
            }
            $product = $row;
        }

        $reservation = null;
        if (!$nationalPanel) {
            $managePanelCheck = new ManagePanel();
            $snapshotCheck = rx_pf_panel_snapshot($managePanelCheck, (string)$panel['name_panel'], (string)$invoice['username'], (string)($panel['type'] ?? ''));
            if (rxReservationUiEnabled($panel)) {
                $usableSnapshot = !empty($snapshotCheck['ok']) && !empty($snapshotCheck['exists']) ? $snapshotCheck : null;
                $reservation = rxReservationPanelSelection($panel);
                $selectionCheck = rxReservationNormalizeSelection($reservation, $usableSnapshot, time());
                if (empty($selectionCheck['ok'])) {
                    rx_renewal_log('api_confirm_selection', (string)$selectionCheck['reason'], ['panel' => $panel, 'username' => (string)$invoice['username'], 'invoice_id' => (string)$invoice['id_invoice'], 'invoice_status' => $invoice['Status'] ?? null, 'method' => (string)($panel['Methodextend'] ?? ''), 'volume_gb' => $product['Volume_constraint'] ?? null, 'days' => $product['Service_time'] ?? null, 'snapshot' => $snapshotCheck, 'detail' => $selectionCheck['detail'] ?? null, 'mutation' => 'none']);
                    FaoximaResponse::fail(422, rxRenewalReasonText((string)$selectionCheck['reason'], $selectionCheck));
                }
            }
            $previewCheck = rxRenewalPreviewForService($managePanelCheck, $panel, (string)$invoice['username'], $product['Volume_constraint'] ?? 0, $product['Service_time'] ?? 0, [
                'reservation' => $reservation,
                'snapshot' => $snapshotCheck,
                'id_invoice' => (string)$invoice['id_invoice'],
                'stage' => 'api_confirm',
            ]);
            if (!empty($previewCheck['blocked'])) {
                FaoximaResponse::fail(409, (string)($previewCheck['text'] ?? rxRenewalText('reason_generic')));
            }
        }

        $fxKinds = !empty($custom) ? ['custom_volume', 'custom_time'] : 'product';
        $fxBasePrice = !empty($custom)
            ? fx_custom_base_price($panel, $agent, $product['Volume_constraint'] ?? 0, $product['Service_time'] ?? 0)
            : $product['price_product'];
        if (empty($custom)) {
            $product = fx_apply_to_product($product, $panel);
        }
        $product['price_product'] = fx_finalize_amount($product['price_product'], $panel, $fxKinds);
        if (!empty($custom)) {
            try {
                $panel = fx_custom_api_quote_panel($panel, $this->user, $product, 'extend', FaoximaInput::string($this->data, 'discount_code'));
            } catch (InvalidArgumentException $e) {
                FaoximaResponse::badRequest($e->getMessage());
            }
        }
        if (fx_custom_quote_context($panel, $fxKinds) !== null && !fx_quote_token_valid(FaoximaInput::string($this->data, 'fx_quote'), $panel, $fxKinds)) {
            $fxDisplayPrice = empty($custom)
                ? fx_finalize_amount((int) round(fx_apply_user_discount((float) $product['price_product'], (int)($this->user['pricediscount'] ?? 0))), $panel, $fxKinds)
                : $panel['_custom_quote_total'];
            $changed = fx_price_changed_api_obj($fxDisplayPrice, fx_quote_token($panel, $fxKinds));
            if (!empty($custom)) {
                $changed['price_per_gb'] = fx_custom_resolve($panel, $this->user['agent'] ?? 'f', $product['Volume_constraint'], $product['Service_time'])['price_per_gb'];
                $changed['duration_pricing'] = fx_duration_config($panel, $this->user['agent'] ?? 'f')['enabled'];
            }
            FaoximaResponse::fail(409, fx_price_changed_api_message(), $changed);
        }
        $fxListPrice = (float) $product['price_product'];


        $discountCode = FaoximaInput::string($this->data, 'discount_code');
        if ($discountCode !== '') {
            $dv = MiniDiscount::validateSellForCategories(
                $discountCode,
                'extend',
                (string)($product['code_product'] ?? ''),
                (string)($panel['code_panel'] ?? ''),
                nmProductCategoryList($product),
                $this->user
            );
            if (empty($dv['ok'])) {
                FaoximaResponse::fail(422, (string)($dv['reason'] ?? faoxima_textbot_get('dyn_purchase_invalid_discount_code', '❌ کد تخفیف نامعتبر است.')));
            }
            $discPriceBefore = (float)$product['price_product'];
            $product['price_product'] = fx_finalize_amount(MiniDiscount::applyToPrice($dv['row'], $discPriceBefore), $panel, $fxKinds);
            MiniDiscount::logOrderDiscount([
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
        $finalPrice = (int) fx_finalize_amount($finalPrice, $panel, $fxKinds);
        $fxSnapshot = fx_pricing_snapshot($panel, $fxKinds, $fxBasePrice, $finalPrice, $fxListPrice - $finalPrice, ['product' => (string)($product['code_product'] ?? ''), 'source_channel' => 'miniapp']);


        if (!empty($custom)) {
            $fxSnapshot = fx_custom_snapshot($panel, $agent, $product['Volume_constraint'], $product['Service_time'], $finalPrice, $fxSnapshot);
        }

        $maxBuyAgent = (int)($this->user['maxbuyagent'] ?? 0);
        $balance = (float)($this->user['Balance'] ?? 0);
        if ($maxBuyAgent !== 0 && $agent === 'n2') {
            if (($balance - $finalPrice) < (-1 * $maxBuyAgent)) {
                FaoximaResponse::fail(403, faoxima_textbot_get('dyn_serviceaction_purchase_limit_exhausted', '❌ مبلغ مجاز خرید شما به اتمام رسیده است.'));
            }
        }


        $walletState = function_exists('rx_wallet_available') ? rx_wallet_available($this->user['id']) : ['balance' => (int) $balance, 'available' => (int) $balance];
        $shortfall = $finalPrice - (float) $walletState['available'];
        if ($shortfall > 0 && $agent !== 'n2') {
            $directBuyEnabled = panel_feature_enabled($panel, 'directbuy');
            if (!$directBuyEnabled) {
                FaoximaResponse::fail(402, faoxima_textbot_get('dyn_renewconfirm_insufficient_balance', '❌ موجودی کیف پول شما کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.'));
            }


            $orderId = bin2hex(random_bytes(4));

            $idOrder = bin2hex(random_bytes(2));
            $soCode = rxRenewalNormalizeProductCode((string)$product['code_product']);

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
            $soValue = fx_value_with_snapshot($soValue, $fxSnapshot);

            try {
                $pdo = FaoximaDb::pdo();
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
                FaoximaResponse::fail(500, faoxima_textbot_get('dyn_renewconfirm_request_save_failed', '❌ خطا در ثبت درخواست تمدید. لطفاً دوباره تلاش کنید.'));
            }

            $opened = $this->openCheckout('getextenduser', 'getextenduser|' . $invoice['username'] . '%' . $idOrder, (string) $invoice['username'], (int) $finalPrice, ['id_order' => $idOrder]);
            $amountDue = (int) $opened['checkout']['external_amount'];

            update('user', 'Processing_value',      $amountDue,                                'id', $this->user['id']);
            update('user', 'Processing_value_one',  $invoice['username'] . '%' . $idOrder,     'id', $this->user['id']);
            update('user', 'Processing_value_tow',  'getextenduser',                           'id', $this->user['id']);
            update('user', 'Processing_value_four', '',                                        'id', $this->user['id']);


            FaoximaResponse::ok(array_merge($this->checkoutResponseFields($opened), [
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
                'message'     => faoxima_textbot_get('dyn_renewconfirm_insufficient_balance_pay_shortfall', 'موجودی کیف پول کافی نیست. لطفاً مبلغ کسری را پرداخت کنید.'),
            ]));
            return;
        }


        $cashback = 0;
        if ($finalPrice > 0) {
            $cashbackKey = $agent === 'f' ? 'chashbackextend' : 'chashbackextend_agent';
            $row = select('shopSetting', '*', 'Namevalue', $cashbackKey, 'select');
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
            $renewCashbackEligible = !function_exists('rx_shopCashbackEligible')
                || rx_shopCashbackEligible('chashbackextend', $this->user['register'] ?? null, 'getextenduser');
            if ($renewCashbackEligible && $rate > 0) {
                $cashback = (int) round(($product['price_product'] * $rate) / 100);
                $finalPrice = $finalPrice - $cashback;
                if ($finalPrice < 0) $finalPrice = 0;
            }
        }


        $discountOperationId = 'renew_' . bin2hex(random_bytes(16));
        $balanceCharged = false;
        if ($finalPrice > 0 || $discountCode !== '') {
            $allowNeg = ($agent === 'n2') ? (int)($this->user['maxbuyagent'] ?? 0) : 0;
            $charge = MiniDiscount::chargeWallet($discountCode, $this->user, $finalPrice, $allowNeg, $discountOperationId);
            if (empty($charge['ok'])) {
                FaoximaLogger::warn('Atomic balance charge failed at renew', [
                    'user_id' => $this->user['id'],
                    'invoice' => $invoice['id_invoice'] ?? null,
                    'price'   => $finalPrice,
                    'reason'  => $charge['reason'] ?? 'unknown',
                ]);
                if (str_starts_with((string)($charge['reason'] ?? ''), 'discount_')) {
                    FaoximaResponse::fail(409, 'کد تخفیف دیگر قابل استفاده نیست. لطفاً تمدید را دوباره انجام دهید.');
                }
                if (($charge['reason'] ?? '') !== 'insufficient_balance') {
                    FaoximaResponse::fail(503, 'برداشت برای تمدید تکمیل نشد؛ با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $discountOperationId]);
                }
                FaoximaResponse::fail(402, faoxima_textbot_get('dyn_purchase_concurrent_balance_conflict', '❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.'));
            }
            $newBalance = $charge['new_balance'];
            $balanceCharged = $finalPrice > 0;
            if ($balanceCharged && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($this->user['id'], 'debit', $finalPrice, 'service_action', faoxima_textbot_get('dyn_renewconfirm_ledger_debit_note', 'تمدید سرویس'), $discountOperationId, 'invoice', (string)($invoice['id_invoice'] ?? ''));
            }
        } else {
            $newBalance = $balance;
        }


        if ($nationalPanel) {
            $stockNew = function_exists('nmStockReserveForProduct')
                ? nmStockReserveForProduct($panel, $product, $this->user['id'], $invoice['id_invoice'], 'miniapp_extend_national_stock')
                : false;
            if (!is_array($stockNew) || (string)($stockNew['content'] ?? '') === '') {
                MiniDiscount::releaseWalletDiscount($discountCode, $this->user, $discountOperationId);
                if (is_array($stockNew) && function_exists('nmStockReleaseReservation')) nmStockReleaseReservation($stockNew);
                if ($balanceCharged) {
                    if (balance_atomic_credit($this->user['id'], $finalPrice)) {
                        if (function_exists('wallet_ledger_record')) {
                            wallet_ledger_record($this->user['id'], 'credit', $finalPrice, 'refund', faoxima_textbot_get('dyn_renewconfirm_stock_refund_note', 'بازگشت وجه به دلیل خطای انبار'), null, 'invoice', (string)($invoice['id_invoice'] ?? ''));
                        }
                    } else {
                        FaoximaLogger::error('refund credit failed', ['user' => $this->user['id'], 'amount' => $finalPrice]);
                    }
                }
                FaoximaResponse::fail(409, faoxima_textbot_get('dyn_renewconfirm_stock_depleted', '❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.'));
            }

            $now = time();
            update('invoice', 'name_product',  $product['name_product'],      'id_invoice', $invoice['id_invoice']);
            update('invoice', 'price_product', $product['price_product'],     'id_invoice', $invoice['id_invoice']);
            update('invoice', 'Volume',        $product['Volume_constraint'], 'id_invoice', $invoice['id_invoice']);
            update('invoice', 'Service_time',  $product['Service_time'],      'id_invoice', $invoice['id_invoice']);
            update('invoice', 'Status',        'active',                      'id_invoice', $invoice['id_invoice']);
            update('invoice', 'time_sell',     $now,                          'id_invoice', $invoice['id_invoice']);
            update('invoice', 'user_info',     $stockNew['content'],          'id_invoice', $invoice['id_invoice']);
            if ($discountCode !== '' && isset($discPriceBefore)) {
                update('invoice', 'discount_code', $discountCode, 'id_invoice', $invoice['id_invoice']);
                update('invoice', 'discount_amount', (string)($discPriceBefore - (float)$product['price_product']), 'id_invoice', $invoice['id_invoice']);
                update('invoice', 'price_before_discount', (string)$discPriceBefore, 'id_invoice', $invoice['id_invoice']);
            }
            try { update('invoice', 'source_panel_code', $panel['code_panel'], 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) {}

            if (!empty($stockNew['id']) && function_exists('nmStockMarkDelivered')) {
                nmStockMarkDelivered($stockNew['id'], $this->user['id'], $invoice['id_invoice'], ['mode' => 'miniapp_extend_national_stock']);
            }

            MiniDiscount::logSale([
                'id_user' => $this->user['id'],
                'id_invoice' => $invoice['id_invoice'] ?? null,
                'Service_location' => $invoice['Service_location'],
                'kind' => 'renewal',
                'name_product' => $product['name_product'],
                'amount' => $finalPrice,
                'source' => 'miniapp',
            ]);

            if ((int)($this->setting['scorestatus'] ?? 0) === 1) {
                update('user', 'score', (int)($this->user['score'] ?? 0) + 2, 'id', $this->user['id']);
            }

            $this->reportSuccess(
                faoxima_render_text(faoxima_textbot_get('dyn_renewconfirm_national_stock_report_tpl', "✅ <b>تمدید سرویس (انبار شبکه‌ملی)</b>\n<blockquote>▫️آیدی کاربر : {user_id}</blockquote>\n<blockquote>▫️نام کاربری سرویس : {username}</blockquote>\n<blockquote>▫️محصول : {product_name}</blockquote>\n<blockquote>▫️حجم : {volume} گیگ</blockquote>\n<blockquote>▫️زمان : {service_time} روز</blockquote>\n<blockquote>▫️مبلغ : {price} تومان</blockquote>\n<blockquote>▫️پنل : {panel_name}</blockquote>"), [
                    'user_id' => $this->user['id'],
                    'username' => $invoice['username'],
                    'product_name' => $product['name_product'],
                    'volume' => (int)$product['Volume_constraint'],
                    'service_time' => (int)$product['Service_time'],
                    'price' => rxFormatToman($finalPrice),
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

            FaoximaResponse::ok([
                'kind'          => 'done',
                'message'       => faoxima_textbot_get('dyn_renewconfirm_national_stock_success', '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد.'),
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
        $renewRef = 'miniapp:' . $this->user['id'] . ':' . bin2hex(random_bytes(4));


        try {
            $extend = $managePanel->extend(
                $panel['Methodextend'] ?? '',
                (int)$product['Volume_constraint'],
                (int)$product['Service_time'],
                (string)$invoice['username'],
                (string)$product['code_product'],
                (string)$panel['code_panel'],
                null,
                [
                    'source'      => 'miniapp',
                    'ref'         => $renewRef,
                    'id_user'     => $this->user['id'],
                    'price'       => $finalPrice,
                    'reservation' => array_merge(is_array($reservation) ? $reservation : [], ['payment_ref' => $renewRef]),
                ]
            );
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'ManagePanel->extend threw at renew', [
                'user_id'  => $this->user['id'],
                'username' => $invoice['username'] ?? null,
            ]);
            MiniDiscount::releaseWalletDiscount($discountCode, $this->user, $discountOperationId);
            if ($balanceCharged) {
                if (balance_atomic_credit($this->user['id'], $finalPrice)) {
                    if (function_exists('wallet_ledger_record')) {
                        wallet_ledger_record($this->user['id'], 'credit', $finalPrice, 'refund', faoxima_textbot_get('dyn_renewconfirm_extend_refund_note', 'بازگشت وجه به دلیل خطای تمدید سرویس'), null, 'invoice', (string)($invoice['id_invoice'] ?? ''));
                    }
                } else {
                    FaoximaLogger::error('refund credit failed', ['user' => $this->user['id'], 'amount' => $finalPrice]);
                }
            }
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_renewconfirm_extend_error', '❌ خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید'));
        }


        $renewPending = rxRenewalIsPending($extend);
        if (!is_array($extend) || (($extend['status'] ?? null) === false && !$renewPending)) {
            MiniDiscount::releaseWalletDiscount($discountCode, $this->user, $discountOperationId);
            $reason = is_array($extend) ? rx_panel_error_text($extend['msg'] ?? $extend, $extend['detail'] ?? null) : htmlspecialchars((string)$extend, ENT_QUOTES, 'UTF-8');
            FaoximaLogger::error('ManagePanel->extend failed', [
                'user_id'  => $this->user['id'],
                'panel'    => $panel['name_panel'],
                'username' => $invoice['username'],
                'reason'   => $reason,
            ]);

            if ($balanceCharged) {
                if (balance_atomic_credit($this->user['id'], $finalPrice)) {
                    if (function_exists('wallet_ledger_record')) {
                        wallet_ledger_record($this->user['id'], 'credit', $finalPrice, 'refund', faoxima_textbot_get('dyn_renewconfirm_extend_refund_note', 'بازگشت وجه به دلیل خطای تمدید سرویس'), null, 'invoice', (string)($invoice['id_invoice'] ?? ''));
                    }
                } else {
                    FaoximaLogger::error('refund credit failed', ['user' => $this->user['id'], 'amount' => $finalPrice]);
                }
            }
            $this->reportError(
                faoxima_render_text(faoxima_textbot_get('dyn_renewconfirm_extend_failed_report_tpl', "خطای تمدید سرویس\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>دلیل خطا : {reason}</blockquote>"), [
                    'panel_name' => $panel['name_panel'],
                    'username' => guardDisplayUsername($invoice['username'], $panel),
                    'reason' => $reason,
                ])
            );

            if (is_array($extend) && ($extend['code'] ?? '') === 'manual_stock_empty') {
                FaoximaResponse::fail(409, faoxima_textbot_get('dyn_renewconfirm_manual_stock_empty', '❌ موجودی انبار برای این محصول تمام شده است. مبلغی از حساب شما کسر نشد.'));
            }

            if (is_array($extend) && ($extend['code'] ?? '') === 'queued_renewal_exists') {
                FaoximaResponse::fail(409, faoxima_textbot_get('dyn_renewconfirm_queued_renewal_exists', '❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد.'));
            }

            if (rxRenewalFailureKnown($extend)) {
                FaoximaResponse::fail(409, rxRenewalFailureText($extend));
            }

            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_renewconfirm_extend_error', '❌ خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید'));
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
                'code_product' => rxRenewalNormalizeProductCode((string)$product['code_product']),
                'id_order'     => $orderRand,
                'reservation'  => $reservation,
            ], JSON_UNESCAPED_UNICODE);
            $value = fx_value_with_snapshot($value, $fxSnapshot);

            $pdo = FaoximaDb::pdo();
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
            FaoximaLogger::warn('service_other insert failed (extend)', ['err' => $e->getMessage()]);
        }


        $renewApplied = !$renewPending && empty($extend['queued']);
        if ($renewApplied && (string)($invoice['Status'] ?? '') !== 'disablebyadmin') {
            update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
        }

        MiniDiscount::logSale([
            'id_user' => $this->user['id'],
            'id_invoice' => $invoice['id_invoice'] ?? null,
            'Service_location' => $invoice['Service_location'],
            'kind' => 'renewal',
            'name_product' => $product['name_product'],
            'amount' => $finalPrice,
            'source' => 'miniapp',
        ]);

        if ($discountCode !== '' && isset($discPriceBefore)) {
            update('invoice', 'discount_code', $discountCode, 'id_invoice', $invoice['id_invoice']);
            update('invoice', 'discount_amount', (string)($discPriceBefore - (float)$product['price_product']), 'id_invoice', $invoice['id_invoice']);
            update('invoice', 'price_before_discount', (string)$discPriceBefore, 'id_invoice', $invoice['id_invoice']);
        }

        if ($renewApplied && ($panel['type'] ?? '') === 'Manualsale') {
            update('invoice', 'name_product',  $product['name_product'],      'id_invoice', $invoice['id_invoice']);
            update('invoice', 'price_product', $product['price_product'],     'id_invoice', $invoice['id_invoice']);
            update('invoice', 'Volume',        $product['Volume_constraint'], 'id_invoice', $invoice['id_invoice']);
            update('invoice', 'Service_time',  $product['Service_time'],      'id_invoice', $invoice['id_invoice']);
            update('invoice', 'time_sell',     time(),                        'id_invoice', $invoice['id_invoice']);
        }


        if ($renewApplied && ($invoice['name_product'] ?? '') === 'سرویس تست') {
            update('invoice', 'name_product',  $product['name_product'],  'id_invoice', $invoice['id_invoice']);
            update('invoice', 'price_product', $product['price_product'], 'id_invoice', $invoice['id_invoice']);
        } elseif ($renewApplied && rxRenewalNormalizeProductCode((string)($product['code_product'] ?? '')) !== 'custom_volume' && rxRenewalIsAppliedVerified($extend)) {
            rxRenewalSyncInvoiceName($invoice['id_invoice'] ?? '', $product['name_product'] ?? '');
        }


        if ((int)($this->setting['scorestatus'] ?? 0) === 1) {
            $newScore = (int)($this->user['score'] ?? 0) + 2;
            update('user', 'score', $newScore, 'id', $this->user['id']);
        }

        $this->reportSuccess(
            faoxima_render_text(faoxima_textbot_get('dyn_renewconfirm_report_tpl', "✅ <b>تمدید سرویس</b>\n<blockquote>▫️آیدی کاربر : {user_id}</blockquote>\n<blockquote>▫️نام کاربری سرویس : {username}</blockquote>\n<blockquote>▫️محصول : {product_name}</blockquote>\n<blockquote>▫️حجم : {volume} گیگ</blockquote>\n<blockquote>▫️زمان : {service_time} روز</blockquote>\n<blockquote>▫️مبلغ : {price} تومان</blockquote>\n<blockquote>▫️پنل : {panel_name}</blockquote>"), [
                'user_id' => $this->user['id'],
                'username' => guardDisplayUsername($invoice['username'], $panel),
                'product_name' => $product['name_product'],
                'volume' => (int)$product['Volume_constraint'],
                'service_time' => (int)$product['Service_time'],
                'price' => rxFormatToman($finalPrice),
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

        FaoximaLogger::debug('Inline renewal completed', [
            'user_id'   => $this->user['id'],
            'username'  => $invoice['username'],
            'product'   => $product['code_product'] ?? null,
            'amount'    => $finalPrice,
            'cashback'  => $cashback,
        ]);


        if (!empty($extend['queued'])) {
            $renewMessage = (string)($extend['reservation']['confirmation'] ?? rxRenewalText('reservation_confirmed'));
        } else {
            $renewMessage = rxRenewalDecorateSuccess(faoxima_textbot_get('dyn_renewconfirm_success', '✅ سرویس شما با موفقیت تمدید شد.'), $extend, (string)$invoice['username'], (string)($panel['name_panel'] ?? ''));
        }

        if (!empty($custom)) {
            $renewMessage .= fx_custom_price_text($panel, $agent, $product['Volume_constraint'], $product['Service_time'], $finalPrice);
        }

        FaoximaResponse::ok([
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
            FaoximaResponse::badRequest(faoxima_render_text(faoxima_textbot_get('dyn_renewconfirm_invalid_volume_range_tpl', '❌ حجم نامعتبر است (بین {min} و {max} گیگابایت)'), ['min' => $minVol, 'max' => $maxVol]));
        }
        if ($time > $maxTime || $time < $minTime) {
            FaoximaResponse::badRequest(faoxima_render_text(faoxima_textbot_get('dyn_renewconfirm_invalid_time_range_tpl', '❌ زمان نامعتبر است (بین {min} و {max} روز)'), ['min' => $minTime, 'max' => $maxTime]));
        }

        try {
            $durationPricing = fx_duration_config($panel, $agent)['enabled'];
            $price = fx_custom_price(fx_custom_renew_panel($panel, $agent), $agent, $durationPricing ? ($custom['volume_gb'] ?? 0) : $volume, $durationPricing ? ($custom['time_days'] ?? 0) : $time);
        } catch (InvalidArgumentException $e) {
            FaoximaResponse::badRequest($e->getMessage());
        }

        return [
            'code_product'      => 'customvolume',
            'name_product'      => faoxima_textbot_get('dyn_renewconfirm_custom_service_name', '⚙️ سرویس دلخواه'),
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
        $errorRow = select('topicid', 'idreport', 'report', 'errorreport', 'select');
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
        if ($publicLogVars !== null && function_exists('faoxima_public_purchase_log_event')) {
            faoxima_public_purchase_log_event('renewal', $publicLogVars, $this->setting);
        }

        $channel = (string)($this->setting['Channel_Report'] ?? '');
        if ($channel === '') return;
        $row = select('topicid', 'idreport', 'report', 'buyreport', 'select');
        $topic = is_array($row) ? (string)($row['idreport'] ?? '') : '';
        $reply = json_encode([
            'inline_keyboard' => [[
                ['text' => faoxima_textbot_get('dyn_renewconfirm_manage_user_btn', '👤 مدیریت کاربر'), 'callback_data' => 'manageuser_' . $this->user['id']],
            ]],
        ]);
        try {
            rx_sendTopicReport([
                'chat_id'           => $channel,
                'message_thread_id' => $topic,
                'text'              => $text,
                'parse_mode'        => 'HTML',
                'reply_markup'      => $reply,
            ], ['flow' => 'miniapp_renew', 'user_id' => (string)($this->user['id'] ?? '')]);
        } catch (Throwable $e) {
            FaoximaLogger::warn('renew success report failed', ['err' => $e->getMessage()]);
        }
    }
}
