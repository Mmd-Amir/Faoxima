<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/DiscountSupport.php';

final class ServiceExtraHandler extends BaseHandler
{


    public ?string $mode = null;

    public function handle(): void
    {
        $mode = $this->mode ?? FaoximaInput::string($this->data, '_mode');
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
        FaoximaResponse::badRequest('Unknown mode for service_extra');
    }

    private function handleQuote(): void
    {
        [$invoice, $panel, $kind, $pricePerUnit, $bounds, $fxKind] = $this->resolveContext('GET');

        $amount = FaoximaInput::int($this->data, 'amount', 0);
        $total = null;
        if ($amount > 0) {
            $total = $amount * $pricePerUnit;
            $total = fx_finalize_amount($total, $panel, $fxKind);
        }

        FaoximaResponse::ok([
            'username'        => (string)$invoice['username'],
            'kind'            => $kind,
            'price_per_unit'  => (int)$pricePerUnit,
            'unit_label'      => $kind === 'time' ? faoxima_textbot_get('dyn_serviceextra_unit_day', 'روز') : faoxima_textbot_get('dyn_serviceextra_unit_gigabyte', 'گیگابایت'),
            'min'             => $bounds[0],
            'max'             => $bounds[1],
            'amount'          => $amount,
            'total_price'     => $total === null ? null : (int)$total,
            'balance'         => (float)($this->user['Balance'] ?? 0),
            'fx_enabled'      => fx_context($panel, $fxKind) !== null,
            'fx_quote'        => fx_quote_token($panel, $fxKind),
            'fx_round_step'   => fx_context($panel, $fxKind)['round_step'] ?? null,
        ]);
    }

    private function handleConfirm(): void
    {
        [$invoice, $panel, $kind, $pricePerUnit, $bounds, $fxKind] = $this->resolveContext('POST');

        $amount = FaoximaInput::int($this->data, 'amount', 0);
        if ($amount <= 0) {
            FaoximaResponse::badRequest('amount must be > 0');
        }
        if (!ctype_digit((string)$amount)) {
            FaoximaResponse::badRequest('amount must be a positive integer');
        }
        if (count($bounds) === 2 && $bounds[0] > 0 && $amount < $bounds[0]) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_serviceextra_amount_min_tpl', '❌ مقدار باید حداقل {min} باشد'), ['min' => $bounds[0]]));
        }
        if (count($bounds) === 2 && $bounds[1] > 0 && $amount > $bounds[1]) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_serviceextra_amount_max_tpl', '❌ مقدار باید حداکثر {max} باشد'), ['max' => $bounds[1]]));
        }

        $price = (float) ($amount * $pricePerUnit);
        $price = fx_finalize_amount($price, $panel, $fxKind);
        if (fx_context($panel, $fxKind) !== null && !fx_quote_token_valid(FaoximaInput::string($this->data, 'fx_quote'), $panel, $fxKind)) {
            FaoximaResponse::fail(409, fx_price_changed_api_message(), fx_price_changed_api_obj((int) $price, fx_quote_token($panel, $fxKind)));
        }
        $fxListPrice = (float) $price;


        $discountCode = FaoximaInput::string($this->data, 'discount_code');
        $discPriceBefore = null;
        if ($discountCode !== '') {
            $sourceProduct = FaoximaDb::fetchOne(
                "SELECT category FROM product WHERE name_product = :n AND (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1",
                [':n' => (string)($invoice['name_product'] ?? ''), ':loc' => (string)($invoice['Service_location'] ?? ''), ':agent' => (string)($this->user['agent'] ?? 'f')]
            );
            $categoryList = is_array($sourceProduct) ? nmProductCategoryList($sourceProduct) : [];

            $dv = MiniDiscount::validateSellForCategories(
                $discountCode,
                $kind === 'time' ? 'time' : 'volume',
                '',
                (string)($panel['code_panel'] ?? ''),
                $categoryList,
                $this->user
            );
            if (empty($dv['ok'])) {
                FaoximaResponse::fail(422, (string)($dv['reason'] ?? faoxima_textbot_get('dyn_purchase_invalid_discount_code', '❌ کد تخفیف نامعتبر است.')));
            }
            $discPriceBefore = $price;
            $price = fx_finalize_amount(MiniDiscount::applyToPrice($dv['row'], $price), $panel, $fxKind);
            MiniDiscount::logOrderDiscount([
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
        $finalPrice = (int) fx_finalize_amount($finalPrice, $panel, $fxKind);
        $fxBaseUnit = fx_panel_raw_unit_price($panel, $this->fxPriceField($invoice, $kind), (string)($this->user['agent'] ?? 'f'));
        $fxSnapshot = fx_pricing_snapshot($panel, $fxKind, $amount * ($fxBaseUnit ?? 0), $finalPrice, $fxListPrice - $finalPrice, ['quantity' => $amount, 'unit_price' => (int) $pricePerUnit, 'source_channel' => 'miniapp']);

        $balance = (float)($this->user['Balance'] ?? 0);
        $agent = (string)($this->user['agent'] ?? 'f');


        $maxBuyAgent = (int)($this->user['maxbuyagent'] ?? 0);
        if ($maxBuyAgent !== 0 && $agent === 'n2') {
            if (($balance - $finalPrice) < (-1 * $maxBuyAgent)) {
                FaoximaResponse::fail(403, faoxima_textbot_get('dyn_serviceaction_purchase_limit_exhausted', '❌ مبلغ مجاز خرید شما به اتمام رسیده است.'));
            }
        }


        $walletState = function_exists('rx_wallet_available') ? rx_wallet_available($this->user['id']) : ['balance' => (int) $balance, 'available' => (int) $balance];
        $shortfall = $finalPrice - (float) $walletState['available'];
        if ($shortfall > 0 && $agent !== 'n2') {
            $directBuy = panel_feature_enabled($invoice['Service_location'], 'directbuy') ? 'ondirectbuy' : 'offdirectbuy';
            if ($directBuy !== 'ondirectbuy') {
                FaoximaResponse::fail(402, faoxima_textbot_get('dyn_renewconfirm_insufficient_balance', '❌ موجودی کیف پول شما کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.'));
            }

            $orderId = bin2hex(random_bytes(4));

            $extendStateTow = $kind === 'time' ? 'getextratimeuser' : 'getextravolumeuser';
            $opened = $this->openCheckout($extendStateTow, $extendStateTow . '|' . $invoice['username'] . '%' . $amount, (string) $invoice['username'], (int) $finalPrice, ['extra_kind' => $kind, 'extra_amount' => $amount]);
            $amountDue = (int) $opened['checkout']['external_amount'];
            update('user', 'Processing_value',     $amountDue,                              'id', $this->user['id']);
            update('user', 'Processing_value_one', $invoice['username'] . '%' . $amount, 'id', $this->user['id']);
            update('user', 'Processing_value_tow', $extendStateTow,                       'id', $this->user['id']);

            FaoximaResponse::ok(array_merge($this->checkoutResponseFields($opened), [
                'kind'        => 'requires_payment',
                'amount_due'  => $amountDue,
                'balance'     => $balance,
                'price'       => $finalPrice,
                'username'    => (string)$invoice['username'],
                'order_id'    => $orderId,
                'extra_kind'  => $kind,
                'extra_amount'=> $amount,
                'message'     => faoxima_textbot_get('dyn_renewconfirm_insufficient_balance_pay_shortfall', 'موجودی کیف پول کافی نیست. لطفاً مبلغ کسری را پرداخت کنید.'),
            ]));
            return;
        }


        $operationId = 'extra_' . bin2hex(random_bytes(16));
        $operationType = $kind === 'time' ? 'extra_time_user' : 'extra_user';
        $pendingRowId = 0;
        $managePanel = new ManagePanel();
        $beforeSnapshot = function_exists('rx_pf_panel_snapshot')
            ? rx_pf_panel_snapshot($managePanel, (string)$panel['name_panel'], (string)$invoice['username'], (string)($panel['type'] ?? ''))
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
            'before' => $beforeSnapshot,
            'target' => $targetState,
            'wallet_state' => 'charged',
        ];
        $pdo = FaoximaDb::pdo();
        $ownOperation = !$pdo->inTransaction();
        $operationSavepoint = 'rx_extra_' . bin2hex(random_bytes(4));
        $commitAttempted = false;
        try {
            if ($ownOperation) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $operationSavepoint);
            }
            $pendingStmt = $pdo->prepare(
                'INSERT INTO service_other (id_user, username, value, type, time, price, output, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $pendingStmt->execute([
                (string)$this->user['id'],
                (string)$invoice['username'],
                json_encode($operationValue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $operationType,
                date('Y/m/d H:i:s'),
                (string)$finalPrice,
                '',
                'pending',
            ]);
            $pendingRowId = (int)$pdo->lastInsertId();
            if ($pendingRowId <= 0) {
                throw new RuntimeException('operation_not_saved');
            }
            $charge = MiniDiscount::chargeServiceWallet($discountCode, $this->user, $finalPrice, $agent, $operationId);
            if (empty($charge['ok'])) {
                throw new RuntimeException((string)($charge['reason'] ?? 'charge_failed'));
            }
            $newBalance = $charge['new_balance'];
            $balanceCharged = $finalPrice > 0;
            if ($balanceCharged && (!function_exists('wallet_ledger_record') || !wallet_ledger_record($this->user['id'], 'debit', $finalPrice, 'service_action', $kind === 'time' ? faoxima_textbot_get('dyn_serviceextra_ledger_debit_time', 'خرید زمان اضافه') : faoxima_textbot_get('dyn_serviceextra_ledger_debit_volume', 'خرید حجم اضافه'), $operationId, 'service_other', (string)$pendingRowId))) {
                throw new RuntimeException('ledger_not_saved');
            }
            if ($ownOperation) {
                $commitAttempted = true;
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $operationSavepoint);
            }
        } catch (Throwable $e) {
            $outcomeUnknown = $commitAttempted && !$pdo->inTransaction();
            try {
                if ($ownOperation) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                } else {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $operationSavepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $operationSavepoint);
                }
            } catch (Throwable $rollbackError) {
                $outcomeUnknown = true;
            }
            FaoximaLogger::error('Service extra operation could not be recorded', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
            if ($outcomeUnknown) {
                FaoximaResponse::fail(503, 'وضعیت ثبت خرید مشخص نیست؛ با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $operationId]);
            }
            if (str_starts_with($e->getMessage(), 'discount_')) {
                FaoximaResponse::fail(409, 'کد تخفیف دیگر قابل استفاده نیست. لطفاً خرید را دوباره انجام دهید.');
            }
            if ($e->getMessage() === 'insufficient_balance') {
                FaoximaResponse::fail(402, 'موجودی کیف پول شما کافی نیست.');
            }
            FaoximaResponse::fail(500, 'ثبت درخواست خرید انجام نشد؛ مبلغی کسر نشد. لطفاً دوباره تلاش کنید.');
        }
        try {
            if ($kind === 'time') {
                $result = $managePanel->extra_time(
                    (string)$invoice['username'],
                    (string)$panel['code_panel'],
                    (int)$amount
                );
            } else {
                $result = $managePanel->extra_volume(
                    (string)$invoice['username'],
                    (string)$panel['code_panel'],
                    (int)$amount
                );
            }
        } catch (Throwable $e) {
            $result = ['status' => false, 'msg' => 'panel_request_interrupted'];
            FaoximaLogger::error('Service extra panel request interrupted', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
        }

        if (!is_array($result) || ($result['status'] ?? null) === false) {
            $reason = is_array($result) ? rx_panel_error_text($result['msg'] ?? $result, $result['detail'] ?? null) : htmlspecialchars((string)$result, ENT_QUOTES, 'UTF-8');
            FaoximaLogger::error('ManagePanel extra_* failed', [
                'kind'     => $kind,
                'user_id'  => $this->user['id'],
                'panel'    => $panel['name_panel'],
                'username' => $invoice['username'],
                'reason'   => $reason,
            ]);

            $reconciled = false;
            $uncertain = true;
            $reconcilable = function_exists('rx_pf_panel_reconcilable') ? rx_pf_panel_reconcilable($panel) : false;
            if ($reconcilable && is_array($beforeSnapshot) && !empty($beforeSnapshot['ok']) && !empty($beforeSnapshot['exists'])
                && function_exists('rx_pf_classify') && function_exists('rx_pf_panel_snapshot')) {
                if ($kind === 'time') {
                    $beforeState = ['expire' => (int)($beforeSnapshot['expire'] ?? 0)];
                } else {
                    $beforeState = ['data_limit' => (int)($beforeSnapshot['data_limit'] ?? 0)];
                }
                $afterSnapshot = rx_pf_panel_snapshot($managePanel, (string)$panel['name_panel'], (string)$invoice['username'], (string)($panel['type'] ?? ''));
                if (is_array($afterSnapshot) && !empty($afterSnapshot['ok'])) {
                    $classification = rx_pf_classify($afterSnapshot, $beforeState, $targetState);
                    $reconciled = $classification === 'target';
                    $uncertain = $classification !== 'target' && $classification !== 'before';
                } else {
                    $uncertain = true;
                }
            } elseif ($reconcilable && is_array($beforeSnapshot) && empty($beforeSnapshot['ok'])) {
                $uncertain = true;
            }

            if ($reconciled) {
                $result = ['status' => true, 'reconciled' => true];
            } else {
                if ($pendingRowId > 0) {
                    try {
                        if ($uncertain) {
                            FaoximaDb::execute(
                                "UPDATE service_other SET status = 'pending', output = :o WHERE id = :id",
                                [':o' => json_encode($result, JSON_UNESCAPED_UNICODE), ':id' => $pendingRowId]
                            );
                        }
                    } catch (Throwable $e) {
                    }
                }
                if ($uncertain) {
                    $errorTitle = $kind === 'time'
                        ? faoxima_textbot_get('dyn_serviceextra_error_title_time', 'خطای خرید زمان اضافه')
                        : faoxima_textbot_get('dyn_serviceextra_error_title_volume', 'خطای خرید حجم اضافه');
                    $this->reportError(
                        faoxima_render_text(faoxima_textbot_get('dyn_serviceextra_uncertain_report_tpl', "{error_title}\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>نتیجه عملیات در پنل قابل تشخیص نیست : {reason}</blockquote>"), [
                            'error_title' => $errorTitle,
                            'panel_name' => $panel['name_panel'],
                            'username' => guardDisplayUsername($invoice['username'], $panel),
                            'reason' => $reason,
                        ]) . "\n<blockquote>شناسه کاربر: <code>" . htmlspecialchars((string)$this->user['id'], ENT_QUOTES, 'UTF-8') . "</code></blockquote>"
                        . "\n<blockquote>شناسه عملیات: <code>" . $operationId . "</code></blockquote>"
                        . "\n<blockquote>مبلغ: " . number_format($finalPrice) . " تومان</blockquote>"
                        . "\n<blockquote>مقدار درخواست: " . (int)$amount . ($kind === 'time' ? ' روز' : ' گیگابایت') . "</blockquote>"
                    );
                    FaoximaLogger::critical('extra outcome uncertain; no refund issued', [
                        'user_id' => $this->user['id'],
                        'invoice' => $operationId,
                        'kind' => $kind,
                    ]);
                    FaoximaResponse::fail(503, 'نتیجه عملیات در پنل مشخص نیست؛ لطفاً با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $operationId]);
                }

                $refundOwn = !$pdo->inTransaction();
                $refundSavepoint = 'rx_extra_refund_' . bin2hex(random_bytes(4));
                try {
                    if ($refundOwn) {
                        $pdo->beginTransaction();
                    } else {
                        $pdo->exec('SAVEPOINT ' . $refundSavepoint);
                    }
                    if (!MiniDiscount::releaseWalletDiscount($discountCode, $this->user, $operationId)) {
                        throw new RuntimeException('discount_release_failed');
                    }
                    if ($balanceCharged) {
                        if (!balance_atomic_credit($this->user['id'], $finalPrice)) {
                            throw new RuntimeException('refund_credit_failed');
                        }
                        if (!wallet_ledger_record($this->user['id'], 'credit', $finalPrice, 'refund', faoxima_textbot_get('dyn_serviceextra_refund_note', 'بازگشت وجه به دلیل خطا در سرویس اضافه'), $operationId, 'service_other', (string)$pendingRowId)) {
                            throw new RuntimeException('refund_ledger_failed');
                        }
                    }
                    $operationValue['wallet_state'] = 'refunded';
                    FaoximaDb::execute('UPDATE service_other SET value = :v, status = :s, output = :o WHERE id = :id', [
                        ':v' => json_encode($operationValue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ':s' => 'refunded',
                        ':o' => json_encode($result, JSON_UNESCAPED_UNICODE),
                        ':id' => $pendingRowId,
                    ]);
                    if ($refundOwn) {
                        $pdo->commit();
                    } else {
                        $pdo->exec('RELEASE SAVEPOINT ' . $refundSavepoint);
                    }
                } catch (Throwable $e) {
                    if ($refundOwn) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                    } else {
                        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $refundSavepoint);
                        $pdo->exec('RELEASE SAVEPOINT ' . $refundSavepoint);
                    }
                    FaoximaLogger::critical('Service extra refund requires review', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
                    FaoximaResponse::fail(503, 'بازگشت وجه تکمیل نشد؛ لطفاً با شناسه پیگیری با پشتیبانی در ارتباط باشید.', ['operation_id' => $operationId]);
                }
                $errorTitle = $kind === 'time'
                    ? faoxima_textbot_get('dyn_serviceextra_error_title_time', 'خطای خرید زمان اضافه')
                    : faoxima_textbot_get('dyn_serviceextra_error_title_volume', 'خطای خرید حجم اضافه');
                $this->reportError(
                    faoxima_render_text(faoxima_textbot_get('dyn_serviceextra_error_report_tpl', "{error_title}\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>دلیل خطا : {reason}</blockquote>"), [
                        'error_title' => $errorTitle,
                        'panel_name' => $panel['name_panel'],
                        'username' => guardDisplayUsername($invoice['username'], $panel),
                        'reason' => $reason,
                    ])
                );
                $human = $kind === 'time'
                    ? faoxima_textbot_get('dyn_serviceextra_generic_error_time', '❌ خطایی در خرید زمان اضافه رخ داده با پشتیبانی در ارتباط باشید')
                    : faoxima_textbot_get('dyn_serviceextra_generic_error_volume', '❌ خطایی در خرید حجم اضافه رخ داده با پشتیبانی در ارتباط باشید');
                FaoximaResponse::fail(502, $human);
            }
        }

        MiniDiscount::logSale([
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
                $value = fx_value_with_snapshot($value, $fxSnapshot);
                $type = 'extra_time_user';
            } else {
                $value = json_encode([
                    'volume_value'    => $amount,
                    'priceـper_gig'   => (int)$pricePerUnit,
                    'old_volume'      => $oldDataLimit,
                    'expire_old'      => $oldExpire,
                ], JSON_UNESCAPED_UNICODE);
                $value = fx_value_with_snapshot($value, $fxSnapshot);
                $type = 'extra_user';
            }

            $completedValue = json_decode($value, true);
            $value = json_encode(array_merge(is_array($completedValue) ? $completedValue : [], $operationValue), JSON_UNESCAPED_UNICODE);
            $pdo = FaoximaDb::pdo();
            if ($pendingRowId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE service_other
                        SET value = :v, type = :ty, output = :o, status = :s
                      WHERE id = :id'
                );
                $stmt->execute([
                    ':v'  => $value,
                    ':ty' => $type,
                    ':o'  => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ':s'  => 'done',
                    ':id' => $pendingRowId,
                ]);
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT IGNORE INTO service_other
                            (id_user, username, value, type, time, price, output, status)
                         VALUES (:u, :n, :v, :ty, :t, :p, :o, :s)'
                    );
                    $stmt->execute([
                        ':u'  => $this->user['id'],
                        ':n'  => $invoice['username'],
                        ':v'  => $value,
                        ':ty' => $type,
                        ':t'  => date('Y/m/d H:i:s'),
                        ':p'  => $finalPrice,
                        ':o'  => json_encode($result, JSON_UNESCAPED_UNICODE),
                        ':s'  => 'done',
                    ]);
                } catch (Throwable $e) {
                    $stmt = $pdo->prepare(
                        'INSERT IGNORE INTO service_other
                            (id_user, username, value, type, time, price, output)
                         VALUES (:u, :n, :v, :ty, :t, :p, :o)'
                    );
                    $stmt->execute([
                        ':u'  => $this->user['id'],
                        ':n'  => $invoice['username'],
                        ':v'  => $value,
                        ':ty' => $type,
                        ':t'  => date('Y/m/d H:i:s'),
                        ':p'  => $finalPrice,
                        ':o'  => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
        } catch (Throwable $e) {
            FaoximaLogger::warn('service_other insert failed (extra)', ['err' => $e->getMessage()]);
        }

        if ($kind === 'time') {
            update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
        }

        if (function_exists('faoxima_public_purchase_log_event')) {
            faoxima_public_purchase_log_event($kind === 'time' ? 'time_extra' : 'volume_topup', [
                'user_id' => $this->user['id'],
                'amount'  => $amount,
                'price'   => number_format((float)$finalPrice),
            ], $this->setting);
        }

        FaoximaLogger::debug('Inline extra purchase completed', [
            'kind'     => $kind,
            'user_id'  => $this->user['id'],
            'username' => $invoice['username'],
            'amount'   => $amount,
            'price'    => $finalPrice,
        ]);

        $msg = $kind === 'time'
            ? faoxima_render_text(faoxima_textbot_get('dyn_serviceextra_success_time_tpl', '✅ {amount} روز به سرویس شما اضافه شد.'), ['amount' => $amount])
            : faoxima_render_text(faoxima_textbot_get('dyn_serviceextra_success_volume_tpl', '✅ {amount} گیگابایت به سرویس شما اضافه شد.'), ['amount' => $amount]);

        FaoximaResponse::ok([
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
        $username = FaoximaInput::string($this->data, 'username');
        if ($username === '') {
            FaoximaResponse::badRequest('username is required');
        }
        $kind = strtolower(FaoximaInput::string($this->data, 'kind'));
        if (!in_array($kind, ['time', 'volume'], true)) {
            FaoximaResponse::badRequest("kind must be 'time' or 'volume'");
        }

        $invoice = FaoximaDb::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            FaoximaResponse::notFound('Service not found');
        }

        if ($method === 'POST'
            && !in_array((string)$invoice['Status'], ['active','end_of_time','end_of_volume','sendedwarn','send_on_hold'], true)) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_serviceextra_status_invalid_restart', '❌ خرید با خطا مواجه گردید مراحل را مجدد انجام دهید.'));
        }

        $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (empty($panel)) {
            FaoximaResponse::notFound('Panel not found');
        }
        if (($panel['status_extend'] ?? '') === 'off_extend') {
            FaoximaResponse::fail(409, $kind === 'time'
                ? faoxima_textbot_get('dyn_serviceextra_time_unavailable_panel', '❌ امکان خرید زمان اضافه در این پنل وجود ندارد')
                : faoxima_textbot_get('dyn_serviceextra_volume_unavailable_panel', '❌ امکان خرید حجم اضافه در این پنل وجود ندارد'));
        }


        $featKey = $kind === 'time' ? 'timeextra' : 'extravolume';
        if (!panel_feature_enabled($panel, $featKey)) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_serviceextra_feature_unavailable', '❌ این قابلیت درحال حاضر در دسترس نیست'));
        }


        if ($kind === 'time' && ($invoice['name_product'] ?? '') === 'سرویس تست') {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_serviceextra_test_service_unavailable', '❌ این قابلیت برای سرویس تست در دسترس نیست'));
        }

        $agent = (string)($this->user['agent'] ?? 'f');
        $isCustomService = in_array($invoice['name_product'] ?? '', ['🛍 حجم دلخواه', '⚙️ سرویس دلخواه'], true);
        $priceField = $this->fxPriceField($invoice, $kind);
        if ($isCustomService) {
            $fxKind = $kind === 'time' ? 'custom_time' : 'custom_volume';
        } else {
            $fxKind = $kind === 'time' ? 'extra_time' : 'extra_volume';
        }
        $pricePerUnit = (int) fx_adjust_base_toman($this->jsonAgentValue($panel[$priceField] ?? '', $agent, 0), $panel, $fxKind);
        if ($pricePerUnit <= 0) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_serviceextra_tariff_not_configured', '❌ تعرفه خرید اضافه روی این پنل تنظیم نشده است.'));
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
}

