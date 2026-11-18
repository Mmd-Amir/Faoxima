<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/DiscountSupport.php';
require_once __DIR__ . '/service_output.php';

final class PurchaseHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        global $textbotlang, $connect;

        $managePanel = new ManagePanel();
        $errorReport   = (string)(select('topicid', 'idreport', 'report', 'errorreport',  'select')['idreport'] ?? '');
        $porsantReport = (string)(select('topicid', 'idreport', 'report', 'porsantreport', 'select')['idreport'] ?? '');
        $buyReport     = (string)(select('topicid', 'idreport', 'report', 'buyreport',     'select')['idreport'] ?? '');


        $codePanel = $this->resolveCountryId();
        if ($codePanel === '') {
            FaoximaResponse::badRequest('country_id is required');
        }
        $panel = select('marzban_panel', '*', 'code_panel', $codePanel, 'select');
        if (empty($panel)) {
            FaoximaResponse::fail(404, faoxima_textbot_get('dyn_purchase_panel_not_found', 'پنل انتخابی موجود نیست.'));
        }
        if (jovadMiniAppReject($panel)) {
            return;
        }
        if (($panel['status'] ?? '') === 'disable') {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_purchase_panel_disabled', 'پنل انتخابی درحال حاضر فعال نیست'));
        }
        if (panel_creation_limit_reached($panel)) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_purchase_panel_limit_reached', 'ظرفیت ساخت کانفیگ در این پنل تکمیل شده است.'));
        }


        $panel['_custom_quote_user'] = [(string)$this->user['id'], (string)($this->user['agent'] ?? 'f'), (int)($this->user['pricediscount'] ?? 0)];
        $customService = FaoximaInput::array($this->data, 'custom_service');
        if (empty($customService)) {
            $serviceId = FaoximaInput::string($this->data, 'service_id');
            if ($serviceId === '') {
                FaoximaResponse::badRequest('service_id is required');
            }
            $product = FaoximaDb::fetchOne(
                "SELECT * FROM product
                  WHERE code_product = :code
                    AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all')
                    AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))
                  LIMIT 1",
                [
                    ':code' => $serviceId,
                    ':location' => (string)($panel['name_panel'] ?? ''),
                    ':agent' => (string)($this->user['agent'] ?? 'f'),
                ]
            );
        } else {
            $product = $this->buildCustomProduct($panel, $customService);
        }

        if (empty($product)) {
            FaoximaResponse::fail(404, faoxima_textbot_get('dyn_purchase_product_not_found', 'محصول انتخابی پیدا نشد'));
        }

        if (!$this->productIsAllowedForAgent((array)$product, $this->user['agent'])) {
            FaoximaResponse::fail(403, faoxima_textbot_get('dyn_purchase_product_not_allowed_for_agent', 'این محصول برای نوع کاربری شما فعال نیست'));
        }

        $fxKinds = empty($customService) ? 'product' : ['custom_volume', 'custom_time'];
        if (empty($customService)) {
            $product = fx_apply_to_product($product, $panel);
        }
        if (!empty($customService)) {
            try {
                $panel = fx_custom_api_quote_panel($panel, $this->user, $product, 'buy', FaoximaInput::string($this->data, 'discount_code'));
            } catch (InvalidArgumentException $e) {
                FaoximaResponse::badRequest($e->getMessage());
            }
        }
        if (fx_custom_quote_context($panel, $fxKinds) !== null && !fx_quote_token_valid(FaoximaInput::string($this->data, 'fx_quote'), $panel, $fxKinds)) {
            $fxDisplayPrice = empty($customService)
                ? fx_finalize_amount(fx_apply_user_discount($product['price_product'], (int)($this->user['pricediscount'] ?? 0)), $panel, $fxKinds)
                : $panel['_custom_quote_total'];
            $changed = fx_price_changed_api_obj($fxDisplayPrice, fx_quote_token($panel, $fxKinds));
            if (!empty($customService)) {
                $changed['price_per_gb'] = fx_custom_resolve($panel, $this->user['agent'] ?? 'f', $product['Volume_constraint'], $product['Service_time'])['price_per_gb'];
                $changed['duration_pricing'] = fx_duration_config($panel, $this->user['agent'] ?? 'f')['enabled'];
            }
            FaoximaResponse::fail(409, fx_price_changed_api_message(), $changed);
        }
        $fxListPrice = (float) fx_finalize_amount($product['price_product'], $panel, $fxKinds);
        $fxBasePrice = empty($customService)
            ? ($product['fx_base_price'] ?? $product['price_product'])
            : fx_custom_base_price($panel, $this->user['agent'] ?? 'f', $product['Volume_constraint'] ?? 0, $product['Service_time'] ?? 0);


        $discount = (int)($this->user['pricediscount'] ?? 0);
        if ($discount !== 0) {
            $discount = min(100, max(0, $discount));
            $delta = ($product['price_product'] * $discount) / 100;
            $product['price_product'] = $product['price_product'] - $delta;
        }

        $discountCode = FaoximaInput::string($this->data, 'discount_code');
        $discPriceBefore = null;
        $discPriceAfter = null;
        if ($discountCode !== '') {
            $dv = MiniDiscount::validateSellForCategories(
                $discountCode,
                'buy',
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
            $discPriceAfter = (float)$product['price_product'];
            $discAmount = $discPriceBefore - $discPriceAfter;
            MiniDiscount::logOrderDiscount([
                'id_user' => $this->user['id'],
                'code' => $discountCode,
                'kind' => 'sell',
                'value_type' => $dv['value_type'],
                'value_raw' => $dv['value'],
                'price_before' => $discPriceBefore,
                'discount_amount' => $discAmount,
                'price_after' => $discPriceAfter,
                'section' => 'buy',
            ]);
        }


        $product['price_product'] = fx_finalize_amount($product['price_product'], $panel, $fxKinds);
        $fxSnapshot = fx_pricing_snapshot($panel, $fxKinds, $fxBasePrice, $product['price_product'], $fxListPrice - (float) $product['price_product'], ['product' => (string)($product['code_product'] ?? ''), 'source_channel' => 'miniapp']);

        if (!empty($customService)) {
            $fxSnapshot = fx_custom_snapshot($panel, $this->user['agent'] ?? 'f', $product['Volume_constraint'], $product['Service_time'], $product['price_product'], $fxSnapshot);
        }

        $orderId = bin2hex(random_bytes(4));
        $customUsername = FaoximaInput::nullableString($this->data, 'custom_username');
        $methodUsername = (string)($panel['MethodUsername'] ?? '');
        if ($methodUsername === 'نام کاربری دلخواه' || $methodUsername === 'متن دلخواه کاربر + رندوم') {
            $trimmedCustom = trim((string)$customUsername);
            if ($trimmedCustom === '') {
                FaoximaResponse::fail(422, faoxima_textbot_get('dyn_purchase_custom_username_required', 'برای این پنل، انتخاب نام کاربری دلخواه ضروری است.'));
            }
            if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $trimmedCustom)) {
                FaoximaResponse::fail(422, faoxima_textbot_get('dyn_purchase_custom_username_invalid', 'نام کاربری معتبر نیست. فقط حروف انگلیسی، عدد، _ . - (۳ تا ۴۰ کاراکتر).'));
            }
            $customUsername = $trimmedCustom;
        }
        $usernameAc = generateUsername(
            $this->user['id'],
            $methodUsername,
            $this->user['username'] ?? '',
            $orderId,
            $customUsername,
            $panel['namecustom'] ?? '',
            $this->user['namecustom'] ?? ''
        );
        $usernameAc = strtolower((string)$usernameAc);
        $requestedUsernameAc = $usernameAc;


        $existsLocal = FaoximaDb::fetchScalar(
            'SELECT 1 FROM invoice WHERE username = :u LIMIT 1',
            [':u' => $usernameAc]
        );
        $remoteCheck = null;
        if (($panel['type'] ?? '') !== 'Manualsale') {
            $remoteCheck = $managePanel->DataUser($panel['name_panel'], $usernameAc);
        }
        $usernameWasRenamed = $existsLocal || (is_array($remoteCheck) && isset($remoteCheck['username']));
        if ($usernameWasRenamed) {
            $usernameAc = rand(1000000, 9999999) . '-' . $usernameAc;
        }


        $customNote = FaoximaInput::nullableString($this->data, 'custom_note');
        if ($customNote !== null && strlen($customNote) <= 1) {
            $customNote = null;
        }

        $notifications = json_encode(['volume' => false, 'time' => false]);
        $serviceTime = (int)$product['Service_time'];


        $walletState = function_exists('rx_wallet_available') ? rx_wallet_available($this->user['id']) : ['balance' => (int) $this->user['Balance'], 'available' => (int) $this->user['Balance']];
        $shortfall = (float)$product['price_product'] - (float)$walletState['available'];
        if ($shortfall > 0.0) {
            $directBuyEnabled = panel_feature_enabled($panel, 'directbuy');

            if (!$directBuyEnabled) {
                FaoximaResponse::fail(402, faoxima_textbot_get('dyn_purchase_balance_below_price', 'موجودی کمتر از قیمت محصول است'));
            }


            try {
                FaoximaDb::execute(
                    "INSERT INTO invoice
                        (id_user, id_invoice, username, time_sell, Service_location, name_product,
                         price_product, Volume, Service_time, Status, note, refral, notifctions, ip_limit, hwid_limit,
                         symbolic_limit_enabled, symbolic_limit_users, discount_code, discount_amount, price_before_discount)
                     VALUES (:id_user, :id_invoice, :username, :time_sell, :location, :name_product,
                             :price, :volume, :service_time, :status, :note, :refral, :notifs, :ip_limit, :hwid_limit,
                             :symbolic_limit_enabled, :symbolic_limit_users, :discount_code, :discount_amount, :price_before_discount)",
                    [
                        ':id_user'      => $this->user['id'],
                        ':id_invoice'   => $orderId,
                        ':username'     => $usernameAc,
                        ':time_sell'    => time(),
                        ':location'     => $panel['name_panel'],
                        ':name_product' => $product['name_product'],
                        ':price'        => $product['price_product'],
                        ':volume'       => $product['Volume_constraint'],
                        ':service_time' => $serviceTime,
                        ':status'       => 'unpaid',
                        ':note'         => $customNote,
                        ':refral'       => $this->user['affiliates'],
                        ':notifs'       => $notifications,
                        ':ip_limit'     => (string)(int)($product['ip_limit'] ?? 0),
                        ':hwid_limit'   => (string)(int)($product['hwid_limit'] ?? 0),
                        ':symbolic_limit_enabled' => (string)($product['symbolic_limit_enabled'] ?? '0'),
                        ':symbolic_limit_users'   => (string)(int)($product['symbolic_limit_users'] ?? 0),
                        ':discount_code' => $discountCode !== '' ? $discountCode : null,
                        ':discount_amount' => $discPriceBefore !== null ? (string)$discAmount : '0',
                        ':price_before_discount' => $discPriceBefore !== null ? (string)$discPriceBefore : null,
                    ]
                );
            } catch (Throwable $e) {
                if ($e instanceof PDOException && (string)$e->getCode() === '23000') {
                    FaoximaResponse::fail(409, faoxima_textbot_get('dyn_purchase_username_already_taken', 'این نام کاربری قبلاً ثبت شده است، لطفاً دوباره تلاش کنید.'));
                }
                FaoximaLogger::exception($e, 'Unpaid invoice insert failed', ['user_id' => $this->user['id']]);
                FaoximaResponse::serverError(faoxima_textbot_get('dyn_purchase_invoice_save_failed', 'خطا در ذخیره فاکتور'));
            }
            fx_store_invoice_snapshot($orderId, $fxSnapshot);

            $opened = $this->openCheckout('getconfigafterpay', 'getconfigafterpay|' . $usernameAc, (string) $usernameAc, (int) round((float) $product['price_product']), ['order_id' => $orderId]);
            $amountDue = (int) $opened['checkout']['external_amount'];

            FaoximaLogger::debug('Direct-buy initiated', [
                'user_id'    => $this->user['id'],
                'order_id'   => $orderId,
                'username'   => $usernameAc,
                'amount_due' => $amountDue,
                'price'      => $product['price_product'],
                'balance'    => $this->user['Balance'],
                'checkout_id' => $opened['checkout']['checkout_id'],
            ]);

            $payload = array_merge($this->checkoutResponseFields($opened), [
                'success'          => true,
                'status'           => true,
                'message'          => faoxima_textbot_get('dyn_purchase_insufficient_balance_pay_shortfall', 'موجودی کافی نیست — لطفاً مبلغ کسری را پرداخت کنید'),
                'requires_payment' => true,
                'amount_due'       => $amountDue,
                'balance'          => (float)$this->user['Balance'],
                'available_balance' => (float)$walletState['available'],
                'price'            => (float)$product['price_product'],
                'username'         => $usernameAc,
                'username_requested' => $requestedUsernameAc,
                'username_was_changed' => $usernameWasRenamed,
                'order_id'         => $orderId,
                'product'          => [
                    'name'       => (string)$product['name_product'],
                    'traffic_gb' => (int)$product['Volume_constraint'],
                    'time_days'  => $serviceTime,
                ],
                'panel'            => [
                    'id'   => (string)$panel['code_panel'],
                    'name' => (string)$panel['name_panel'],
                ],
            ]);
            if (function_exists('__miniapp_emit')) {
                __miniapp_emit(200, $payload);
            } else {
                while (ob_get_level() > 0) { @ob_end_clean(); }
                if (!headers_sent()) {
                    http_response_code(200);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode($payload, JSON_UNESCAPED_UNICODE);
                $GLOBALS['__miniapp_response_sent'] = true;
            }
            exit;
        }


        $rxPanelLimitLockKey = panel_limit_lock_acquire((string)($panel['name_panel'] ?? ''));
        if (panel_creation_limit_reached_unlocked($panel)) {
            panel_limit_lock_release($rxPanelLimitLockKey);
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_purchase_panel_limit_reached', 'ظرفیت ساخت کانفیگ در این پنل تکمیل شده است.'));
        }
        try {
            FaoximaDb::execute(
                "INSERT INTO invoice
                    (id_user, id_invoice, username, time_sell, Service_location, name_product,
                     price_product, Volume, Service_time, Status, note, refral, notifctions, ip_limit, hwid_limit,
                     symbolic_limit_enabled, symbolic_limit_users, discount_code, discount_amount, price_before_discount)
                 VALUES (:id_user, :id_invoice, :username, :time_sell, :location, :name_product,
                         :price, :volume, :service_time, :status, :note, :refral, :notifs, :ip_limit, :hwid_limit,
                         :symbolic_limit_enabled, :symbolic_limit_users, :discount_code, :discount_amount, :price_before_discount)",
                [
                    ':id_user'      => $this->user['id'],
                    ':id_invoice'   => $orderId,
                    ':username'     => $usernameAc,
                    ':time_sell'    => time(),
                    ':location'     => $panel['name_panel'],
                    ':name_product' => $product['name_product'],
                    ':price'        => $product['price_product'],
                    ':volume'       => $product['Volume_constraint'],
                    ':service_time' => $serviceTime,
                    ':status'       => 'processing',
                    ':note'         => $customNote,
                    ':refral'       => $this->user['affiliates'],
                    ':notifs'       => $notifications,
                    ':ip_limit'     => (string)(int)($product['ip_limit'] ?? 0),
                    ':hwid_limit'   => (string)(int)($product['hwid_limit'] ?? 0),
                    ':symbolic_limit_enabled' => (string)($product['symbolic_limit_enabled'] ?? '0'),
                    ':symbolic_limit_users'   => (string)(int)($product['symbolic_limit_users'] ?? 0),
                    ':discount_code' => $discountCode !== '' ? $discountCode : null,
                    ':discount_amount' => $discPriceBefore !== null ? (string)$discAmount : '0',
                    ':price_before_discount' => $discPriceBefore !== null ? (string)$discPriceBefore : null,
                ]
            );
            panel_limit_lock_release($rxPanelLimitLockKey);
        } catch (Throwable $e) {
            panel_limit_lock_release($rxPanelLimitLockKey);
            if ($e instanceof PDOException && (string)$e->getCode() === '23000') {
                FaoximaResponse::fail(409, 'این نام کاربری قبلاً ثبت شده است، لطفاً دوباره تلاش کنید.');
            }
            FaoximaLogger::exception($e, 'Invoice insert failed', ['user_id' => $this->user['id']]);
            FaoximaResponse::serverError('خطا در ذخیره فاکتور');
        }
        fx_store_invoice_snapshot($orderId, $fxSnapshot);


        $expireTs = $serviceTime > 0 ? strtotime('+' . $serviceTime . ' days') : 0;


        $priceToCharge = (float) $product['price_product'];
        $balanceBeforePurchase = (float) $this->user['Balance'];
        $balanceChargedAtomically = false;
        if ($priceToCharge > 0.0 || $discountCode !== '') {
            $agent = $this->user['agent'] ?? 'f';
            $allowNeg = ($agent === 'n2') ? (int)($this->user['maxbuyagent'] ?? 0) : 0;
            $charge = MiniDiscount::chargeWallet($discountCode, $this->user, $priceToCharge, $allowNeg, (string)$orderId);
            if (empty($charge['ok'])) {
                FaoximaLogger::warn('Atomic balance charge failed at purchase', [
                    'user_id'  => $this->user['id'],
                    'order_id' => $orderId,
                    'price'    => $priceToCharge,
                    'reason'   => $charge['reason'] ?? 'unknown',
                ]);
                if (($charge['reason'] ?? '') === 'outcome_unknown') {
                    FaoximaResponse::fail(503, 'وضعیت برداشت مشخص نیست؛ با شناسه سفارش با پشتیبانی در ارتباط باشید.', ['operation_id' => (string)$orderId]);
                }
                try { FaoximaDb::execute('DELETE FROM invoice WHERE id_invoice = :o AND id_user = :u', [':o' => $orderId, ':u' => $this->user['id']]); } catch (Throwable $_) {}
                if (str_starts_with((string)($charge['reason'] ?? ''), 'discount_')) {
                    FaoximaResponse::fail(409, 'کد تخفیف دیگر قابل استفاده نیست. لطفاً خرید را دوباره انجام دهید.');
                }
                if (($charge['reason'] ?? '') !== 'insufficient_balance') {
                    FaoximaResponse::fail(500, 'ثبت خرید انجام نشد. لطفاً دوباره تلاش کنید.');
                }
                FaoximaResponse::fail(402, faoxima_textbot_get('dyn_purchase_concurrent_balance_conflict', 'موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.'));
            }
            $balanceChargedAtomically = $priceToCharge > 0;

            $this->user['Balance'] = $charge['new_balance'];
            if ($balanceChargedAtomically && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($this->user['id'], 'debit', $priceToCharge, 'purchase', 'خرید سرویس', $orderId, 'invoice', $orderId);
            }
        }


        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel)) {
            $stock = function_exists('nmStockReserveForProduct')
                ? nmStockReserveForProduct($panel, (array)$product, $this->user['id'], $orderId, 'miniapp_national_buy')
                : false;
            if (!is_array($stock) || (string)($stock['content'] ?? '') === '') {
                MiniDiscount::releaseWalletDiscount($discountCode, $this->user, (string)$orderId);
                if (is_array($stock) && function_exists('nmStockReleaseReservation')) nmStockReleaseReservation($stock);
                if ($balanceChargedAtomically) {
                    if (!balance_atomic_credit($this->user['id'], $priceToCharge)) {
                        FaoximaLogger::critical('Refund after stock reservation failure did not apply; user balance may be short', [
                            'user_id'  => $this->user['id'],
                            'order_id' => $orderId,
                            'price'    => $priceToCharge,
                        ]);
                    } elseif (function_exists('wallet_ledger_record')) {
                        wallet_ledger_record($this->user['id'], 'credit', $priceToCharge, 'refund', 'بازگشت وجه به دلیل خطای انبار', $orderId, 'invoice', $orderId);
                    }
                }
                try { FaoximaDb::execute('DELETE FROM invoice WHERE id_invoice = :o AND id_user = :u', [':o' => $orderId, ':u' => $this->user['id']]); } catch (Throwable $_) {}
                FaoximaResponse::fail(409, faoxima_textbot_get('dyn_purchase_stock_depleted', '❌ موجودی انبار برای این محصول تمام شده است؛ مبلغی کسر نشد.'));
            }

            $stockContent = (string)($stock['content'] ?? '');
            $stockContentTrimmed = trim($stockContent);
            $stockSubLink = trim((string)($stock['sub_link'] ?? ''));
            if ($stockSubLink === '' && preg_match('/^https?:\/\//i', $stockContentTrimmed)) $stockSubLink = $stockContentTrimmed;

            update('invoice', 'user_info', $stockContent, 'id_invoice', $orderId);
            try { update('invoice', 'source_panel_code', $panel['code_panel'] ?? '', 'id_invoice', $orderId); } catch (Throwable $e) {}

            if (!empty($stock['id']) && function_exists('nmStockMarkDelivered')) {
                nmStockMarkDelivered($stock['id'], $this->user['id'], $orderId, ['mode' => 'miniapp_national_buy']);
            }
            try {
                update('invoice', 'Status', 'active', 'id_invoice', $orderId);
            } catch (Throwable $e) {
                FaoximaLogger::warn('invoice activation failed after stock delivery', ['order_id' => $orderId, 'err' => $e->getMessage()]);
            }

            $invoiceCount = (int) FaoximaDb::fetchScalar(
                "SELECT COUNT(*) FROM invoice WHERE name_product != 'سرویس تست' AND id_user = :id",
                [':id' => $this->user['id']]
            );
            $this->payAffiliate($product, $invoiceCount, $porsantReport);
            if ((int)($this->setting['scorestatus'] ?? 0) === 1) {
                sendmessage($this->user['id'], faoxima_textbot_get('dyn_purchase_score_earned_1', '📌شما 1 امتیاز جدید کسب کردید.'), null, 'html');
                update('user', 'score', (int)$this->user['score'] + 1, 'id', $this->user['id']);
            }
            $this->reportPurchase($buyReport, $product, $panel, $usernameAc, $orderId, $invoiceCount, $balanceBeforePurchase);

            $stockConfigs = ($stockContentTrimmed !== '' && $stockContentTrimmed !== $stockSubLink) ? [$stockContent] : [];
            $stockOutput = [];
            if ($stockSubLink !== '') {
                $stockOutput[] = ['type' => 'link', 'value' => $stockSubLink];
            }
            if ($stockContentTrimmed !== '' && $stockContentTrimmed !== $stockSubLink) {
                $stockFormat = strtolower((string)($stock['format'] ?? ''));
                if ($stockFormat === '' && function_exists('nmStockDetectFormat')) {
                    $stockFormat = strtolower((string)nmStockDetectFormat($stockContent));
                }
                if ($stockFormat === 'wireguard') {
                    $stockOutput[] = ['type' => 'file', 'value' => $stockContent, 'filename' => 'wg-' . $orderId . '.conf'];
                } else {
                    $stockOutput[] = ['type' => 'config', 'value' => $stockContent];
                }
            }
            $payload = [
                'success'  => true,
                'status'   => true,
                'message'  => 'ok',
                'order_id' => $orderId,
                'service'  => [
                    'id'                => $orderId,
                    'username'          => $usernameAc,
                    'username_requested' => $requestedUsernameAc,
                    'username_was_changed' => $usernameWasRenamed,
                    'status'            => 'active',
                    'active'            => true,
                    'expire'            => $expireTs,
                    'product_name'      => (string)($product['name_product'] ?? ''),
                    'panel_name'        => (string)($panel['name_panel'] ?? ''),
                    'panel_type'        => 'stock',
                    'service_time_days' => (int)$serviceTime,
                    'days_left'         => (int)$serviceTime,
                    'unlimited_time'    => (int)$serviceTime === 0,
                    'expiration_time'   => $expireTs > 0 ? jdate('Y/m/d', $expireTs) : 'نامحدود',
                    'subscription_url'  => $stockSubLink,
                    'configs'           => $stockConfigs,
                    'service_output'    => $stockOutput,
                    'is_stock'          => true,
                ],
            ];
            if (function_exists('__miniapp_emit')) {
                __miniapp_emit(200, $payload);
            } else {
                while (ob_get_level() > 0) { @ob_end_clean(); }
                if (!headers_sent()) {
                    http_response_code(200);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode($payload, JSON_UNESCAPED_UNICODE);
                $GLOBALS['__miniapp_response_sent'] = true;
            }
            exit;
        }


        $createPayload = [
            'expire'     => $expireTs,
            'data_limit' => (int)$product['Volume_constraint'] * pow(1024, 3),
            'from_id'    => $this->user['id'],
            'username'   => $this->user['username'] ?? '',
            'type'       => 'buy',
        ];

        $remote = $managePanel->createUser(
            $panel['name_panel'],
            $product['code_product'],
            $usernameAc,
            $createPayload,
            true
        );

        if (empty($remote['username']) && ($panel['type'] ?? '') !== 'Manualsale'
            && (!function_exists('rx_pf_panel_reconcilable') || rx_pf_panel_reconcilable($panel))) {
            $snapshot = null;
            if (function_exists('rx_pf_panel_snapshot')) {
                $snapshot = rx_pf_panel_snapshot($managePanel, (string)$panel['name_panel'], (string)$usernameAc, (string)($panel['type'] ?? ''));
            }
            if (is_array($snapshot) && !empty($snapshot['ok']) && !empty($snapshot['exists']) && is_array($snapshot['raw'] ?? null)) {
                $remote = $snapshot['raw'];
                $remote['status'] = 'successful';
                if (empty($remote['configs']) && !empty($remote['links'])) {
                    $remote['configs'] = is_array($remote['links']) ? $remote['links'] : explode("\n", (string)$remote['links']);
                }
            } elseif (!is_array($snapshot) || empty($snapshot['ok'])) {
                $this->reportToChannel(faoxima_render_text(faoxima_textbot_get('dyn_purchase_create_user_uncertain_report_tpl', "⚠️ وضعیت ساخت سرویس در پنل نامشخص است\n<blockquote>آیدی کاربر : {user_id}</blockquote>\n<blockquote>نام کاربری : {username}</blockquote>\n<blockquote>پنل : {panel_name}</blockquote>"), [
                    'user_id' => $this->user['id'],
                    'username' => $usernameAc,
                    'panel_name' => $panel['name_panel'],
                ]), $errorReport);
                FaoximaLogger::critical('createUser outcome uncertain after wallet charge; no refund issued', [
                    'user_id' => $this->user['id'],
                    'order_id' => $orderId,
                    'panel' => $panel['name_panel'],
                    'username' => $usernameAc,
                ]);
                FaoximaResponse::fail(503, 'وضعیت ساخت سرویس مشخص نیست. مبلغ از کیف پول کسر شده و مورد در حال بررسی است؛ لطفاً با پشتیبانی در ارتباط باشید.');
            }
        }

        if (empty($remote['username'])) {
            MiniDiscount::releaseWalletDiscount($discountCode, $this->user, (string)$orderId);
            $reason = is_array($remote) ? rx_panel_error_text($remote['msg'] ?? null, $remote['detail'] ?? null) : htmlspecialchars((string)$remote, ENT_QUOTES, 'UTF-8');
            FaoximaLogger::error('createUser failed', [
                'user_id' => $this->user['id'],
                'panel' => $panel['name_panel'],
                'product' => $product['code_product'] ?? null,
                'reason' => $reason,
            ]);


            if ($balanceChargedAtomically) {
                if (!balance_atomic_credit($this->user['id'], $priceToCharge)) {
                    FaoximaLogger::critical('Refund after createUser failure did not apply; user balance may be short', [
                        'user_id'  => $this->user['id'],
                        'order_id' => $orderId,
                        'price'    => $priceToCharge,
                    ]);
                } elseif (function_exists('wallet_ledger_record')) {
                    wallet_ledger_record($this->user['id'], 'credit', $priceToCharge, 'refund', 'بازگشت وجه به دلیل خطای ساخت سرویس', $orderId, 'invoice', $orderId);
                }
            }
            try { FaoximaDb::execute('DELETE FROM invoice WHERE id_invoice = :o AND id_user = :u', [':o' => $orderId, ':u' => $this->user['id']]); } catch (Throwable $_) {}

            $errorText = faoxima_render_text(faoxima_textbot_get('dyn_purchase_create_user_failed_report_tpl', "⭕️ خطای ساخت اشتراک \n<blockquote>✍️ دلیل خطا : </blockquote>\n<blockquote>{reason}</blockquote>\n<blockquote>آیدی کابر : {user_id}</blockquote>\n<blockquote>نام کاربری کاربر : @{username}</blockquote>\n<blockquote>نام پنل : {panel_name}</blockquote>"), [
                'reason' => $reason,
                'user_id' => $this->user['id'],
                'username' => $this->user['username'],
                'panel_name' => $panel['name_panel'],
            ]);
            $this->reportToChannel($errorText, $errorReport);

            FaoximaResponse::serverError(faoxima_textbot_get('dyn_purchase_create_user_failed_customer', 'خطایی در ساخت اشتراک رخ داده است با پشتیبانی در ارتباط باشید'));
        }

        if (!empty($remote['renamed_from'])) {
            $usernameAc = rx_adopt_created_username($remote, $usernameAc, $orderId);
            $usernameWasRenamed = true;
            rx_notify_username_renamed($this->user['id'], $remote, $panel);
        }
        try {
            update('invoice', 'Status', 'active', 'id_invoice', $orderId);
        } catch (Throwable $e) {
            FaoximaLogger::warn('invoice activation failed after service creation', ['order_id' => $orderId, 'err' => $e->getMessage()]);
        }

        $configList = is_array($remote['configs'] ?? null) ? $remote['configs'] : [];
        $configsText = '';
        if (($panel['config'] ?? '') === 'onconfig') {
            foreach ($configList as $link) {
                $configsText .= "\n" . $link;
            }
        }
        $showLink = false;
        $subLink = '';
        if (($panel['type'] ?? '') === 'Manualsale') {
            $mExt = strtolower(ltrim(trim((string)($remote['file_ext'] ?? '')), '.'));
            $isManualFile = ($mExt !== '' && $mExt !== 'sub' && $mExt !== 'text');
            $manualSubLink = trim((string)($remote['sub_link'] ?? ''));
            $manualContent = (string)($remote['subscription_url'] ?? '');
            if ($isManualFile) {
                $subLink = $manualSubLink;
            } elseif ($mExt === 'text') {
                $subLink = $manualSubLink;
                if (trim($manualContent) !== '') {
                    $configList[] = $manualContent;
                }
            } else {
                $subLink = $manualContent;
            }
        } else {
            $showLink = ($panel['sublink'] ?? '') === 'onsublink';
            $subLink = $showLink ? (string)($remote['subscription_url'] ?? '') : '';
        }

        $template = $this->resolveTemplate($panel['type'] ?? '');
        $template = str_replace('{username}', "<code>" . guardDisplayUsername($remote['username'], $panel) . "</code>", $template);
        $template = str_replace('{name_service}', $product['name_product'] ?? '', $template);
        $template = str_replace('{location}', $panel['name_panel'] ?? '', $template);

        $displayDays   = $serviceTime === 0 ? ($textbotlang['users']['stateus']['Unlimited'] ?? '∞') : $serviceTime;
        $displayVolume = (int)$product['Volume_constraint'] === 0 ? ($textbotlang['users']['stateus']['Unlimited'] ?? '∞') : $product['Volume_constraint'];
        $template = str_replace('{day}', (string)$displayDays, $template);
        $template = str_replace('{volume}', (string)$displayVolume, $template);

        if (!empty($customService)) {
            $template .= fx_custom_price_text($panel, $this->user['agent'] ?? 'f', $product['Volume_constraint'], $product['Service_time'], $product['price_product']);
        }

        $usesButtonDelivery = ($panel['type'] ?? '') !== 'Manualsale'
            && !(function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel));
        $template = $usesButtonDelivery
            ? applyConnectionPlaceholders($template, '', '')
            : applyConnectionPlaceholders($template, $subLink, $configsText);


        $rootDir = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 2);
        $backgroundCandidates = [
            $rootDir . DIRECTORY_SEPARATOR . 'images.jpeg',
            $rootDir . DIRECTORY_SEPARATOR . 'images.jpg',
        ];
        $backgroundImage = 'images.jpg';
        foreach ($backgroundCandidates as $candidate) {
            if (is_file($candidate)) { $backgroundImage = $candidate; break; }
        }


        $cardSent = $this->sendInfoCardNotification(
            $panel,
            $remote,
            $usernameAc,
            $orderId,
            $product,
            $serviceTime,
            $template,
            $configList,
            $subLink,
            $cardDelivery
        );


        if (!$cardSent && ($cardDelivery === null || (
            $cardDelivery['media_state'] === 'failed'
            && in_array($cardDelivery['text_state'], ['failed', 'skipped', 'not_attempted'], true)
            && $cardDelivery['text_chunks_confirmed'] === 0
        ))) {
            $serviceKeyboard = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => faoxima_textbot_get('dyn_purchase_view_tutorial_btn', '📚 مشاهده آموزش استفاده '), 'callback_data' => 'helpbtn'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

            global $setting;
            if (empty($setting) && !empty($this->setting)) {
                $setting = $this->setting;
            }

            sendMessageService(
                $panel,
                $configList,
                $subLink,
                trim((string) ($remote['username'] ?? '')) !== '' ? $remote['username'] : $usernameAc,
                $serviceKeyboard,
                $template,
                $orderId,
                $this->user['id'],
                $backgroundImage,
                true
            );
        } else {
            rxAutoDeliverPurchaseFilesSafe(
                $panel,
                $configList,
                $subLink,
                trim((string) ($remote['username'] ?? '')) !== '' ? $remote['username'] : $usernameAc,
                $this->user['id'],
                $orderId
            );
        }




        $methodUsername = $panel['MethodUsername'] ?? '';
        $sequentialMethods = [
            'متن دلخواه + عدد ترتیبی',
            'نام کاربری + عدد به ترتیب',
            'آیدی عددی+عدد ترتیبی',
            'متن دلخواه نماینده + عدد ترتیبی',
        ];
        if (in_array($methodUsername, $sequentialMethods, true)) {
            update('user', 'number_username', (int)$this->user['number_username'] + 1, 'id', $this->user['id']);
            if ($methodUsername === 'متن دلخواه + عدد ترتیبی' || $methodUsername === 'متن دلخواه نماینده + عدد ترتیبی') {
                update('setting', 'numbercount', (int)$this->setting['numbercount'] + 1);
            }
        }


        $invoiceCount = (int) FaoximaDb::fetchScalar(
            "SELECT COUNT(*) FROM invoice
              WHERE name_product != 'سرویس تست'
                AND id_user = :id",
            [':id' => $this->user['id']]
        );
        $this->payAffiliate($product, $invoiceCount, $porsantReport);


        if ((int)($this->setting['scorestatus'] ?? 0) === 1) {
            sendmessage($this->user['id'], faoxima_textbot_get('dyn_purchase_score_earned_1', '📌شما 1 امتیاز جدید کسب کردید.'), null, 'html');
            update('user', 'score', (int)$this->user['score'] + 1, 'id', $this->user['id']);
        }


        $this->reportPurchase($buyReport, $product, $panel, $usernameAc, $orderId, $invoiceCount, $balanceBeforePurchase);

        FaoximaLogger::debug('Purchase completed', [
            'user_id'  => $this->user['id'],
            'order_id' => $orderId,
            'panel'    => $panel['name_panel'],
            'product'  => $product['code_product'] ?? null,
            'amount'   => $product['price_product'],
        ]);

        $totalBytes = (int)$product['Volume_constraint'] * (int) pow(1024, 3);
        $serviceTimeInt = (int)$serviceTime;
        $configsArr = array_values(array_filter(array_map(static function ($c) {
            return is_string($c) ? $c : '';
        }, $configList), static function ($c) { return trim($c) !== ''; }));

        if (($panel['type'] ?? '') === 'Manualsale') {
            $manualItemsOut = is_array($remote['manual_items'] ?? null) ? $remote['manual_items'] : [];
            $serviceOutput = faoxima_build_service_output([
                'panel_type' => 'Manualsale',
                'content'    => (string)($remote['subscription_url'] ?? ''),
                'sub_link'   => (string)($remote['manual_sub_link'] ?? $remote['sub_link'] ?? ''),
                'file_ext'   => (string)($remote['file_ext'] ?? ''),
                'username'   => (string)($remote['username'] ?? $usernameAc),
                'items'      => $manualItemsOut,
            ]);
        } else {
            $serviceOutput = faoxima_build_service_output([
                'panel_type' => (string)($panel['type'] ?? ''),
                'sub_link'   => (string)$subLink,
                'configs'    => $configsArr,
            ]);
        }

        $serviceOutput = faoxima_apply_file_names($serviceOutput, $panel, $configsArr);

        $payload = [
            'success'  => true,
            'status'   => true,
            'message'  => 'ok',
            'order_id' => $orderId,
            'service'  => [
                'id'                => $orderId,
                'username'          => (string)($remote['username'] ?? $usernameAc),
                'display_username'  => guardDisplayUsername((string)($remote['username'] ?? $usernameAc), $panel),
                'username_requested' => $requestedUsernameAc,
                'username_was_changed' => $usernameWasRenamed,
                'status'            => 'active',
                'active'            => true,
                'expire'            => $expireTs,
                'product_name'      => (string)($product['name_product'] ?? ''),
                'panel_name'        => (string)($panel['name_panel'] ?? ''),
                'panel_type'        => (string)($panel['type'] ?? ''),
                'service_time_days' => $serviceTimeInt,
                'days_left'         => $serviceTimeInt,
                'volume_gb'         => (int)$product['Volume_constraint'],
                'used_bytes'        => 0,
                'total_bytes'       => $totalBytes,
                'unlimited_volume'  => $totalBytes === 0,
                'unlimited_time'    => $serviceTimeInt === 0,
                'total_traffic_gb'  => $totalBytes > 0 ? round($totalBytes / (1024 ** 3), 2) : 0,
                'used_traffic_gb'   => 0,
                'expiration_time'   => $expireTs > 0 ? jdate('Y/m/d', $expireTs) : 'نامحدود',
                'subscription_url'  => (string)$subLink,
                'configs'           => $configsArr,
                'service_output'    => $serviceOutput,
            ],
        ];
        if (function_exists('__miniapp_emit')) {
            __miniapp_emit(200, $payload);
        } else {
            while (ob_get_level() > 0) { @ob_end_clean(); }
            if (!headers_sent()) {
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            $GLOBALS['__miniapp_response_sent'] = true;
        }
        exit;
    }

    private function sendInfoCardNotification(
        array $panel,
        $remote,
        string $usernameAc,
        string $orderId,
        array $product,
        int $serviceTime,
        string $caption,
        array $configList = [],
        string $subLink = '',
        ?array &$delivery = null
    ): bool {
        $delivery = null;

        if (($panel['type'] ?? '') === 'Manualsale') {
            return false;
        }
        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel)) {
            return false;
        }

        $rootDir = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 2);
        $infocardPath = $rootDir . DIRECTORY_SEPARATOR . 'infocard.php';
        if (!is_file($infocardPath)) {
            return false;
        }
        require_once $infocardPath;
        if (!function_exists('createServiceInfoCard') || !function_exists('telegram')) {
            return false;
        }
        if (!function_exists('getInfoCardStatus') || !getInfoCardStatus()) {
            return false;
        }


        global $usernamebot;
        $botUsername = '';
        if (isset($usernamebot) && is_string($usernamebot)) {
            $botUsername = ltrim(trim($usernamebot), '@');
        }
        if ($botUsername === '' && function_exists('telegram')) {
            try {
                $me = @telegram('getMe', []);
                if (is_array($me) && isset($me['result']['username'])) {
                    $botUsername = (string)$me['result']['username'];
                }
            } catch (Throwable $_) {  }
        }


        $totalBytes = (int)$product['Volume_constraint'] * (int) pow(1024, 3);
        $params = [
            'config_name'    => $usernameAc,
            'bot_username'   => $botUsername,
            'user_id'        => (string)$this->user['id'],
            'active'         => true,
            'used_bytes'     => 0,
            'total_bytes'    => $totalBytes,
            'days_left'      => $serviceTime,
            'unlimited_time' => $serviceTime === 0,
        ];

        $color = function_exists('getInfoCardColor') ? getInfoCardColor() : 'yellow';
        $outPath = $rootDir . DIRECTORY_SEPARATOR . 'infocard_' . $this->user['id'] . '_' . bin2hex(random_bytes(3)) . '.png';

        try {
            $written = createServiceInfoCard($params, $color, $outPath);
        } catch (Throwable $e) {
            FaoximaLogger::warn('createServiceInfoCard threw', ['err' => $e->getMessage()]);
            return false;
        }
        if ($written === false || !is_file($outPath)) {
            return false;
        }


        $__kbPurchaseRows = [];
        if (!function_exists('isQrDisabled') || !isQrDisabled()) {
            $__kbPurchaseRows[] = [['text' => faoxima_textbot_get('dyn_purchase_qr_code_btn', '📷 دریافت QR Code'), 'callback_data' => 'infocard_qr_' . $orderId]];
        }
        if (function_exists('rxDeliveryAvailability')) {
            $availability = rxDeliveryAvailability($panel, $configList, $subLink);
            if (!empty($availability['config'])) {
                $__kbPurchaseRows[] = [['text' => faoxima_textbot_get('dyn_purchase_get_config_btn', '🔐 دریافت کانفیگ'), 'callback_data' => 'config_' . $orderId]];
            }
            if (!empty($availability['sub']) && (!function_exists('rxSubscriptionLinkButtonEnabled') || rxSubscriptionLinkButtonEnabled($panel))) {
                $__kbPurchaseRows[] = [['text' => faoxima_textbot_get('dyn_purchase_get_sublink_btn', '🔗 دریافت لینک اشتراک'), 'callback_data' => 'subscriptionurl_' . $orderId]];
            }
            if (!empty($availability['sub']) && function_exists('rxAppendSubscriptionLinkLine')) {
                $caption = rxAppendSubscriptionLinkLine($caption, $subLink, $panel);
            }
        }
        $__kbPurchaseRows[] = [['text' => '📚 مشاهده آموزش استفاده ', 'callback_data' => 'helpbtn']];

        $keyboard = json_encode(['inline_keyboard' => $__kbPurchaseRows], JSON_UNESCAPED_UNICODE);

        try {
            rxSendMediaWithCaption('sendphoto', [
                'chat_id'      => $this->user['id'],
                'photo'        => new CURLFile($outPath),
                'caption'      => $caption,
                'parse_mode'   => 'HTML',
                'reply_markup' => $keyboard,
            ], null, $delivery);
            return $delivery['delivery_status'] === 'completed';
        } catch (Throwable $e) {
            $delivery = ['media_state' => 'unknown', 'text_state' => 'unknown'];
            FaoximaLogger::warn('sendphoto (info card) failed', ['exception' => get_class($e)]);
            return false;
        } finally {
            @unlink($outPath);
        }
    }

    private function buildCustomProduct(array $panel, array $customService): array
    {
        global $textbotlang;
        $agent = $this->user['agent'] ?? 'f';

        $main  = $this->decodeJsonField($panel['mainvolume'] ?? null);
        $max   = $this->decodeJsonField($panel['maxvolume'] ?? null);
        $minT  = $this->decodeJsonField($panel['maintime'] ?? null);
        $maxT  = $this->decodeJsonField($panel['maxtime'] ?? null);

        $minVolume = (int)($main[$agent] ?? 0);
        $maxVolume = (int)($max[$agent] ?? 0);
        $minTime   = (int)($minT[$agent] ?? 0);
        $maxTime   = (int)($maxT[$agent] ?? 0);

        $volume = (int)($customService['traffic_gb'] ?? 0);
        $time   = (int)($customService['time_days'] ?? 0);

        if ($volume > $maxVolume || $volume < $minVolume) {
            FaoximaResponse::badRequest(faoxima_textbot_get('dyn_purchase_invalid_volume_restart', 'حجم نامعتبر است خرید را از اول انجام دهید'));
        }
        if ($time > $maxTime || $time < $minTime) {
            FaoximaResponse::badRequest(faoxima_textbot_get('dyn_purchase_invalid_time_restart', 'زمان نامعتبر است خرید را از اول انجام دهید'));
        }

        if ((int)($this->decodeJsonField($panel['customvolume'] ?? null)[$agent] ?? 0) !== 1 || ($panel['type'] ?? '') === 'Manualsale') {
            FaoximaResponse::badRequest('سرویس دلخواه فعال نیست.');
        }
        try {
            $durationPricing = fx_duration_config($panel, $agent)['enabled'];
            $price = fx_custom_price($panel, $agent, $durationPricing ? ($customService['traffic_gb'] ?? 0) : $volume, $durationPricing ? ($customService['time_days'] ?? 0) : $time);
        } catch (InvalidArgumentException $e) {
            FaoximaResponse::badRequest($e->getMessage());
        }

        return [
            'code_product'      => 'customvolume',
            'name_product'      => $textbotlang['users']['customsellvolume']['title'] ?? 'سرویس سفارشی',
            'Volume_constraint' => $volume,
            'Service_time'      => $time,
            'Location'          => $panel['name_panel'],
            'agent'             => $agent,
            'price_product'     => $price,
        ];
    }

    private function resolveTemplate(string $panelType): string
    {
        $rows = select('textbot', '*', null, null, 'fetchAll') ?: [];
        $bag = [
            'textafterpay' => '',
            'textmanual' => '',
            'text_wgdashboard' => '',
        ];
        foreach ($rows as $row) {
            if (isset($bag[$row['id_text']])) {
                $bag[$row['id_text']] = $row['text'];
            }
        }

        if ($panelType === 'Manualsale')           return $bag['textmanual'] ?: $bag['textafterpay'];
        if ($panelType === 'WGDashboard')          return $bag['text_wgdashboard'] ?: $bag['textafterpay'];
        return $bag['textafterpay'];
    }

    private function payAffiliate(array $product, int $invoiceCount, string $topicId): void
    {
        if (!function_exists('payAffiliateCommissionForPurchase')) return;

        $commission = payAffiliateCommissionForPurchase(
            $this->user['id'],
            $this->user['affiliates'] ?? null,
            (float) $product['price_product'],
            $invoiceCount
        );
        if ($commission === null) return;

        $formatted = number_format($commission);
        $when = date('Y/m/d H:i:s');

        $textUser = faoxima_render_text(faoxima_textbot_get('dyn_purchase_affiliate_commission_user_tpl', "🎁  پرداخت پورسانت\n\nمبلغ {amount} تومان به حساب شما از طرف زیر مجموعه تان به کیف پول شما واریز گردید"), ['amount' => $formatted]);
        $textReport = faoxima_render_text(faoxima_textbot_get('dyn_purchase_affiliate_commission_report_tpl', "\n<blockquote>مبلغ {amount} به کاربر {affiliate_id} برای پورسانت از کاربر {user_id} واریز گردید</blockquote>\n<blockquote>تایم : {when}</blockquote>"), [
            'amount' => $formatted,
            'affiliate_id' => $this->user['affiliates'],
            'user_id' => $this->user['id'],
            'when' => $when,
        ]);

        $this->reportToChannel($textReport, $topicId);
        sendmessage($this->user['affiliates'], $textUser, null, 'HTML');
    }

    private function reportPurchase(string $topicId, array $product, array $panel, string $usernameAc, string $orderId, int $invoiceCount, float $balanceBeforePurchase): void
    {
        $balanceAfter = (float) FaoximaDb::fetchScalar(
            'SELECT Balance FROM user WHERE id = :id',
            [':id' => $this->user['id']]
        );
        $balanceBefore = number_format($balanceBeforePurchase);
        $balanceAfter  = number_format($balanceAfter);

        $firstBuy = $invoiceCount === 1 ? faoxima_textbot_get('dyn_purchase_first_buy_flag', '📌 خرید اول کاربر') : '';
        $when = jdate('Y/m/d H:i:s');

        $text = faoxima_render_text(faoxima_textbot_get('dyn_purchase_report_channel_tpl', "📣 جزئیات ساخت اکانت در مینی اپ ثبت شد .\n\n{first_buy}\n<blockquote>▫️آیدی عددی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>▫️نام کاربری کاربر :@{username}</blockquote>\n<blockquote>▫️نام کاربری کانفیگ :{config_username}</blockquote>\n<blockquote>▫️موقعیت سرویس : {panel_name}</blockquote>\n<blockquote>▫️نام محصول :{product_name}</blockquote>\n<blockquote>▫️زمان خریداری شده :{service_time} روز</blockquote>\n<blockquote>▫️حجم خریداری شده : {volume} GB</blockquote>\n<blockquote>▫️موجودی قبل خرید : {balance_before} تومان</blockquote>\n<blockquote>▫️موجودی بعد خرید : {balance_after} تومان</blockquote>\n<blockquote>▫️کد پیگیری: {order_id}</blockquote>\n<blockquote>▫️نوع کاربر : {agent}</blockquote>\n<blockquote>▫️شماره تلفن کاربر : {phone}</blockquote>\n<blockquote>▫️قیمت محصول : {price} تومان</blockquote>\n<blockquote>▫️زمان خرید : {when}</blockquote>"), [
            'first_buy' => $firstBuy,
            'user_id' => $this->user['id'],
            'username' => $this->user['username'],
            'config_username' => guardDisplayUsername($usernameAc, $panel),
            'panel_name' => $panel['name_panel'],
            'product_name' => $product['name_product'],
            'service_time' => $product['Service_time'],
            'volume' => $product['Volume_constraint'],
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'order_id' => $orderId,
            'agent' => $this->user['agent'],
            'phone' => $this->user['number'],
            'price' => rxFormatToman($product['price_product']),
            'when' => $when,
        ]);

        global $textbotlang;
        $manageBtn = $textbotlang['Admin']['ManageUser']['mangebtnuser'] ?? '👤 مدیریت کاربر';

        $reply = json_encode([
            'inline_keyboard' => [[
                ['text' => $manageBtn, 'callback_data' => 'manageuser_' . $this->user['id']],
            ]],
        ]);

        if (function_exists('faoxima_public_purchase_log_event')) {
            faoxima_public_purchase_log_event('new_sub', [
                'user_id'    => $this->user['id'],
                'amount'     => $product['name_product'],
                'price'      => number_format((float)$product['price_product']),
                'panel_name' => $panel['name_panel'] ?? '',
                'category'   => $product['category'] ?? '',
            ], $this->setting);
        }

        $channel = $this->setting['Channel_Report'] ?? '';
        if ((string)$channel === '') return;

        rx_sendTopicReport([
            'chat_id'           => $channel,
            'message_thread_id' => $topicId,
            'text'              => $text,
            'parse_mode'        => 'HTML',
            'reply_markup'      => $reply,
        ], ['flow' => 'miniapp_buy', 'order_id' => $orderId, 'user_id' => (string)$this->user['id']]);
    }

    private function reportToChannel(string $text, string $topicId): void
    {
        $channel = $this->setting['Channel_Report'] ?? '';
        if ((string)$channel === '') return;

        telegram('sendmessage', [
            'chat_id'           => $channel,
            'message_thread_id' => $topicId,
            'text'              => $text,
            'parse_mode'        => 'HTML',
        ]);
    }
}

