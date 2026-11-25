<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;
use RuntimeException;

final class Purchase
{
    public static function fundWalletInvoice(PDO $pdo, array $user, string $invoice, string $panel, string $username, float $price, string $discount, string $note, ?string $confirmation = null, array $effects = []): array
    {
        $existingInvoice = null;
        try {
            return \Faoxima\Payments\Fulfillment\Store::tx($pdo, static function (PDO $pdo) use ($user, $invoice, $panel, $username, $price, $discount, $note, $confirmation, $effects, &$existingInvoice) {
                $find = $pdo->prepare('SELECT username, Service_location, Status, name_product, price_product FROM invoice WHERE id_invoice = ? AND id_user = ? FOR UPDATE');
                $find->execute([$invoice, $user['id']]);
                $row = $find->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['username'] !== $username || $row['Service_location'] !== $panel) {
                    return ['ok' => false, 'reason' => 'invoice_mismatch'];
                }
                $expected = str_starts_with($confirmation ?? '', 'telegram:')
                    ? (float) (int) $row['price_product']
                    : (float) $row['price_product'];
                if ($price < 0 || !is_finite($price) || $price !== $expected) {
                    return ['ok' => false, 'reason' => 'invoice_price_mismatch'];
                }
                if (!in_array($row['Status'], ['unpaid', 'wallet_pending'], true)) {
                    return ['ok' => false, 'reason' => 'invoice_already_claimed', 'existing_invoice' => $invoice];
                }
                $funding = \Faoxima\Orders\Discounts\SellDiscounts::chargeServiceWallet(
                    $pdo, $discount, $user, $price, $user['agent'] ?? 'f', $invoice,
                    ['category' => 'purchase', 'note' => $note, 'invoice' => $invoice]
                );
                if (!empty($funding['ok'])) {
                    $reference = $confirmation ?? 'invoice:' . $invoice;
                    $lock = $pdo->prepare('SELECT id FROM user WHERE id = ? FOR UPDATE');
                    $lock->execute([$user['id']]);
                    if ($lock->fetchColumn() === false) {
                        throw new RuntimeException('wallet_purchase_owner_missing');
                    }
                    $seen = $pdo->prepare("SELECT value FROM service_other WHERE id_user = ? AND username = ? AND type = 'wallet_purchase' LIMIT 1 FOR UPDATE");
                    $seen->execute([$user['id'], $reference]);
                    $recorded = $seen->fetchColumn();
                    if ($recorded !== false) {
                        $existingInvoice = (string) $recorded;
                        throw new RuntimeException('invoice_already_claimed');
                    }
                    $pending = $pdo->prepare("SELECT prior.id_invoice FROM service_other operation
                        JOIN invoice prior ON prior.id_invoice = operation.value AND prior.id_user = operation.id_user
                        WHERE operation.id_user = ? AND operation.type = 'wallet_purchase'
                        AND prior.Service_location = ? AND prior.name_product = ?
                        AND prior.Status IN ('processing', 'manual_review') LIMIT 1 FOR UPDATE");
                    $pending->execute([$user['id'], $panel, $row['name_product']]);
                    $recorded = $pending->fetchColumn();
                    if ($recorded !== false) {
                        $existingInvoice = (string) $recorded;
                        throw new RuntimeException('invoice_already_claimed');
                    }
                    $operation = $pdo->prepare("INSERT INTO service_other (id_user, username, value, type, time, price, status, output)
                        VALUES (?, ?, ?, 'wallet_purchase', ?, ?, 'pending', ?)");
                    if (!$operation->execute([$user['id'], $reference, $invoice, date('Y/m/d H:i:s'), $price,
                        json_encode(['discount' => $discount], JSON_THROW_ON_ERROR)])) {
                        throw new RuntimeException('wallet_purchase_reference_failed');
                    }
                    WalletPurchaseEffects::seed($pdo, (string) $user['id'], $invoice, $effects);

                    $claim = $pdo->prepare("UPDATE invoice SET Status = 'processing' WHERE id_invoice = ? AND id_user = ?");
                    if (!$claim->execute([$invoice, $user['id']]) || $claim->rowCount() !== 1) {
                        throw new RuntimeException('wallet_invoice_claim_failed');
                    }
                }
                return $funding;
            });
        } catch (Throwable $error) {
            if ($error instanceof RuntimeException && $error->getMessage() === 'invoice_already_claimed') {
                return ['ok' => false, 'reason' => 'invoice_already_claimed', 'existing_invoice' => $existingInvoice];
            }
            error_log('Wallet purchase claim ' . $invoice . ': ' . $error->getMessage());
            return ['ok' => false, 'reason' => 'outcome_unknown'];
        }
    }

    public static function walletInvoiceForConfirmation(PDO $pdo, string $userId, string $confirmation): ?string
    {
        $find = $pdo->prepare("SELECT funded.id_invoice FROM service_other operation
            JOIN invoice funded ON funded.id_user = operation.id_user AND funded.id_invoice = operation.value
            WHERE operation.id_user = ? AND operation.username = ? AND operation.type = 'wallet_purchase' LIMIT 1");
        $find->execute([$userId, $confirmation]);
        $invoice = $find->fetchColumn();
        return $invoice === false ? null : (string) $invoice;
    }

    public static function outstandingWalletInvoice(PDO $pdo, string $user, string $panel, array $product, string $discount): ?string
    {
        $find = $pdo->prepare("SELECT i.id_invoice FROM invoice i JOIN service_other o ON o.value = i.id_invoice AND o.id_user = i.id_user
            WHERE i.id_user = ? AND i.Service_location = ? AND i.name_product = ? AND i.price_product = ?
              AND i.Volume = ? AND i.Service_time = ? AND COALESCE(i.discount_code, '') = ?
              AND i.Status IN ('processing', 'manual_review') AND o.type = 'wallet_purchase' ORDER BY o.id LIMIT 1");
        $find->execute([$user, $panel, $product['name_product'], $product['price_product'], $product['Volume_constraint'],
            $product['Service_time'], $discount]);
        $invoice = $find->fetchColumn();
        return $invoice === false ? null : (string) $invoice;
    }

    public static function finishWalletInvoice(PDO $pdo, string $userId, string $invoice, string $status): bool
    {
        $statement = $pdo->prepare("UPDATE invoice SET Status = ? WHERE id_invoice = ? AND id_user = ? AND Status = 'processing'");
        return $statement->execute([$status, $invoice, $userId]) && $statement->rowCount() === 1;
    }

    public static function persistProvisionedUserInfo(PDO $pdo, string $invoice, array $remote, bool $replace = false): bool
    {
        if ($invoice === '' || empty($remote['username'])) {
            return false;
        }
        $encoded = json_encode($remote, JSON_UNESCAPED_UNICODE);
        if ($encoded === false || strlen($encoded) > 65535) {
            $encoded = json_encode(array_intersect_key($remote, array_flip(['username', 'subscription_url'])), JSON_INVALID_UTF8_SUBSTITUTE);
            if ($encoded === false || strlen($encoded) > 65535) {
                return false;
            }
        }
        try {
            $find = $pdo->prepare('SELECT user_info FROM invoice WHERE id_invoice = ? LIMIT 1');
            $find->execute([$invoice]);
            $current = $find->fetchColumn();
            if (!$replace && is_string($current) && trim($current) !== '') {
                $decoded = json_decode($current, true);
                if (is_array($decoded) && ($decoded['username'] ?? null) === $remote['username']
                    && trim((string) ($decoded['subscription_url'] ?? '')) !== '') {
                    return true;
                }
            }
            $update = $pdo->prepare('UPDATE invoice SET user_info = ? WHERE id_invoice = ?');
            return $update->execute([$encoded, $invoice]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function settleWalletStock(PDO $pdo, string $userId, string $invoice, array $panel, array $product, string $mode): ?array
    {
        if ($pdo->inTransaction() || !Inventory::nmStockEnsureSchema($pdo)) {
            throw new RuntimeException('wallet_stock_unavailable');
        }
        return \Faoxima\Payments\Fulfillment\Store::tx($pdo, static function (PDO $pdo) use ($userId, $invoice, $panel, $product, $mode) {
            $find = $pdo->prepare('SELECT Status, Service_location FROM invoice WHERE id_invoice = ? AND id_user = ? FOR UPDATE');
            $find->execute([$invoice, $userId]);
            $row = $find->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['Status'] !== 'processing' || $row['Service_location'] !== (string) $panel['name_panel']) {
                throw new RuntimeException('wallet_stock_invoice_unavailable');
            }
            $marker = $pdo->prepare("SELECT status FROM service_other WHERE id_user = ? AND value = ? AND type = 'wallet_purchase' FOR UPDATE");
            $marker->execute([$userId, $invoice]);
            if ($marker->fetchColumn() !== 'pending') {
                throw new RuntimeException('wallet_stock_claim_unavailable');
            }
            $stock = Inventory::nmStockReserveForProduct($pdo, $panel, $product, $userId, $invoice, $mode);
            if (!is_array($stock)) {
                self::permitWalletRefund($pdo, $userId, $invoice, 'pending');
                return null;
            }
            if (trim((string) ($stock['content'] ?? '')) === '') {
                if (!Inventory::nmStockReleaseReservation($pdo, $stock)) {
                    throw new RuntimeException('wallet_stock_release_failed');
                }
                self::permitWalletRefund($pdo, $userId, $invoice, 'pending');
                return null;
            }
            $update = $pdo->prepare("UPDATE invoice SET Status = 'active', user_info = ?, source_panel_code = ?
                WHERE id_invoice = ? AND id_user = ? AND Status = 'processing'");
            if (!$update->execute([$stock['content'], $panel['code_panel'] ?? '', $invoice, $userId]) || $update->rowCount() !== 1) {
                throw new RuntimeException('wallet_stock_finalize_failed');
            }
            return $stock;
        });
    }

    private static function permitWalletRefund(PDO $pdo, string $userId, string $invoice, string $previous): void
    {
        $stmt = $pdo->prepare("UPDATE service_other SET status = 'refundable' WHERE id_user = ? AND value = ?
            AND type = 'wallet_purchase' AND status = ?");
        if (!$stmt->execute([$userId, $invoice, $previous]) || $stmt->rowCount() !== 1) {
            throw new RuntimeException('wallet_purchase_refund_approval_failed');
        }
    }

    public static function refundWalletInvoice(PDO $pdo, string $userId, string $invoice, float $amount, string $discount, string $note): string
    {
        try {
            return \Faoxima\Payments\Fulfillment\Store::tx($pdo, static function (PDO $pdo) use ($userId, $invoice, $amount, $discount, $note) {
                $find = $pdo->prepare("SELECT Status FROM invoice WHERE id_invoice = ? AND id_user = ? FOR UPDATE");
                $find->execute([$invoice, $userId]);
                $status = $find->fetchColumn();
                $allowed = $pdo->prepare("SELECT status, price FROM service_other WHERE id_user = ? AND value = ?
                    AND type = 'wallet_purchase' LIMIT 1 FOR UPDATE");
                $allowed->execute([$userId, $invoice]);
                $operation = $allowed->fetch(PDO::FETCH_ASSOC);
                if (!$operation || (float) $operation['price'] !== $amount) {
                    throw new RuntimeException('wallet_purchase_refund_mismatch');
                }
                if ($status === 'Unsuccessful' && $operation['status'] === 'refunded') {
                    return 'already_refunded';
                }
                if (!in_array($status, ['processing', 'manual_review'], true) || $operation['status'] !== 'refundable') {
                    throw new RuntimeException('wallet_purchase_refund_not_approved');
                }
                $refund = \Faoxima\Payments\Settlement\Refunds::walletChargeOnce(
                    $pdo, ['id' => $userId], $invoice, $invoice, $amount, $discount, $note
                );
                if ($refund === 'failed') {
                    throw new RuntimeException('wallet_purchase_refund_failed');
                }
                $finish = $pdo->prepare("UPDATE invoice SET Status = 'Unsuccessful' WHERE id_invoice = ? AND id_user = ?");
                $record = $pdo->prepare("UPDATE service_other SET status = 'refunded' WHERE value = ? AND id_user = ?
                    AND type = 'wallet_purchase' AND status = 'refundable'");
                if (!$finish->execute([$invoice, $userId]) || $finish->rowCount() !== 1
                    || !$record->execute([$invoice, $userId]) || $record->rowCount() !== 1) {
                    throw new RuntimeException('wallet_purchase_refund_not_saved');
                }
                return $refund;
            });
        } catch (Throwable $error) {
            error_log('Wallet purchase refund ' . $invoice . ': ' . $error->getMessage());
            return 'failed';
        }
    }

    public static function refundInterruptedWalletInvoice(PDO $pdo, string $user, string $invoice, array $row, array $output): bool
    {
        if (!array_key_exists('discount', $output)) {
            return false;
        }
        if ($row['status'] === 'pending') {
            $guard = $pdo->prepare("UPDATE service_other SET status = 'refundable'
                WHERE id = ? AND id_user = ? AND value = ? AND type = 'wallet_purchase' AND status = 'pending'");
            $guard->execute([$row['id'], $user, $invoice]);
            if ($guard->rowCount() !== 1) {
                return false;
            }
        }
        return self::refundWalletInvoice($pdo, $user, $invoice, (float) $row['price'], (string) $output['discount'],
            'بازگشت وجه خرید ناتمام') !== 'failed';
    }

    public static function provisionWalletPurchase($provider, array $panel, array $product, string $username, array $payload, bool $adoptVerifiedResult = true): array
    {
        try {
            $remote = $provider->createUser($panel['name_panel'], $product['code_product'], $username, $payload, true);
        } catch (Throwable $error) {
            return ['remote' => ['msg' => $error->getMessage()], 'uncertain' => true];
        }
        if (is_array($remote) && ($remote['code'] ?? '') === 'uncertain') {
            return ['remote' => $remote, 'uncertain' => true];
        }
        if (!$adoptVerifiedResult) {
            return ['remote' => $remote, 'uncertain' => false];
        }
        if (!empty($remote['username']) || ($panel['type'] ?? '') === 'Manualsale' || !Delivery::panelReconcilable($panel)) {
            return ['remote' => $remote, 'uncertain' => false];
        }
        $snapshot = ProviderState::panelSnapshot($provider, (string) $panel['name_panel'], $username, (string) ($panel['type'] ?? ''));
        if (!empty($snapshot['ok']) && !empty($snapshot['exists']) && is_array($snapshot['raw'] ?? null)) {
            return ['remote' => Adjustments::adoptCreated($snapshot['raw']), 'uncertain' => false, 'read_back' => $snapshot];
        }
        return ['remote' => $remote, 'uncertain' => empty($snapshot['ok'])];
    }

    public static function provisionFunded(PDO $pdo, string $userId, string $invoice, $provider, array $panel,
        array $product, string $username, array $payload): array
    {
        if ($pdo->inTransaction()) {
            throw new RuntimeException('wallet_purchase_not_committed');
        }
        $claim = $pdo->prepare("UPDATE service_other operation JOIN invoice funded
            ON funded.id_invoice = operation.value AND funded.id_user = operation.id_user
            SET operation.status = 'applying'
            WHERE operation.id_user = ? AND operation.type = 'wallet_purchase' AND operation.status = 'pending'
            AND funded.id_invoice = ? AND funded.Status = 'processing' AND funded.Service_location = ? AND funded.username = ?");
        if (!$claim->execute([$userId, $invoice, $panel['name_panel'], $username]) || $claim->rowCount() !== 1) {
            return ['remote' => [], 'uncertain' => true, 'claim_unavailable' => true];
        }
        $canRead = Delivery::panelReconcilable($panel);
        $alreadyExists = false;
        if ($canRead) {
            $before = ProviderState::panelSnapshot($provider, (string) $panel['name_panel'], $username, (string) $panel['type']);
            if (empty($before['ok'])) {
                return ['remote' => ['msg' => 'wallet_purchase_provider_identity_unavailable'], 'uncertain' => true];
            }
            $alreadyExists = !empty($before['exists']);
            if (!$alreadyExists && isset($payload['data_limit'], $payload['expire'])) {
                $plan = json_encode([
                    'username' => $username, 'data_limit' => (int) $payload['data_limit'], 'expire' => (int) $payload['expire'],
                    'dispatched_at' => time(),
                ], JSON_THROW_ON_ERROR);
                $save = $pdo->prepare("UPDATE service_other SET output = JSON_MERGE_PATCH(
                    CASE WHEN JSON_VALID(output) THEN output ELSE '{}' END, ?) WHERE id_user = ? AND type = 'wallet_purchase' AND value = ? AND status = 'applying'");
                if (!$save->execute([$plan, $userId, $invoice]) || $save->rowCount() !== 1) {
                    return ['remote' => [], 'uncertain' => true];
                }
            }
        }
        $execution = self::provisionWalletPurchase($provider, $panel, $product, $username, $payload, $canRead && !$alreadyExists);
        $remote = $execution['remote'];
        if (!empty($remote['username'])) {
            if ($alreadyExists && $remote['username'] === $username) {
                return ['remote' => [], 'uncertain' => true];
            }
            if (isset($execution['read_back'])
                && (($execution['read_back']['username'] ?? null) !== $username || !ProviderState::stateMatches($execution['read_back'], $payload))) {
                return ['remote' => [], 'uncertain' => true];
            }
            $proof = $pdo->prepare("UPDATE service_other SET output = ? WHERE id_user = ? AND value = ?
                AND type = 'wallet_purchase' AND status = 'applying'");
            if (!$proof->execute([json_encode(['confirmed_username' => (string) $remote['username'], 'confirmed_at' => time()], JSON_THROW_ON_ERROR),
                $userId, $invoice]) || $proof->rowCount() !== 1) {
                return ['remote' => [], 'uncertain' => true];
            }
            return $execution;
        }
        $known = is_array($remote) && (($remote['no_mutation'] ?? false) === true
            || (($panel['type'] ?? '') === 'Manualsale' && ($remote['msg'] ?? '') === 'Manualsale stock not found'));
        if ($known) {
            self::permitWalletRefund($pdo, $userId, $invoice, 'applying');
        }
        return ['remote' => $remote, 'uncertain' => !$known];
    }

    public static function recoverWalletInvoice(PDO $pdo, string $userId, string $invoice, $provider, array $panel): bool
    {
        if ($pdo->inTransaction()) {
            return false;
        }
        $find = $pdo->prepare("SELECT funded.username, funded.Status, operation.output FROM invoice funded
            JOIN service_other operation ON operation.value = funded.id_invoice AND operation.id_user = funded.id_user
            WHERE funded.id_user = ? AND funded.id_invoice = ? AND funded.Service_location = ?
            AND funded.Status IN ('processing', 'manual_review') AND operation.type = 'wallet_purchase' AND operation.status = 'applying' LIMIT 1");
        $find->execute([$userId, $invoice, $panel['name_panel']]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        $plan = $row ? json_decode((string) $row['output'], true) : null;
        if (is_array($plan) && !empty($plan['confirmed_username'])) {
            if ((int) ($plan['confirmed_at'] ?? 0) >= time() - 300) {
                return false;
            }
            if (($panel['type'] ?? '') === 'Manualsale') {
                return false;
            }
            return self::activateRecoveredWalletInvoice($pdo, $userId, $invoice, $panel['name_panel'], $row['username'], $plan['confirmed_username']);
        }
        if (!Delivery::panelReconcilable($panel)) {
            return false;
        }
        if (!is_array($plan) || ($plan['username'] ?? '') !== $row['username'] || !isset($plan['data_limit'], $plan['expire'])) {
            return false;
        }
        if ($row['Status'] === 'processing' && (int) ($plan['dispatched_at'] ?? 0) >= time() - 300) {
            return false;
        }
        $snapshot = ProviderState::panelSnapshot($provider, (string) $panel['name_panel'], $row['username'], (string) $panel['type']);
        if (empty($snapshot['ok']) || empty($snapshot['exists']) || ($snapshot['username'] ?? null) !== $row['username']
            || !ProviderState::stateMatches($snapshot, $plan)) {
            return false;
        }
        return self::activateRecoveredWalletInvoice($pdo, $userId, $invoice, $panel['name_panel'], $row['username'], $row['username']);
    }

    private static function activateRecoveredWalletInvoice(PDO $pdo, string $user, string $invoice, string $panel, string $oldName, string $name): bool
    {
        return \Faoxima\Payments\Fulfillment\Store::tx($pdo, static function (PDO $pdo) use ($user, $invoice, $panel, $oldName, $name) {
            $complete = $pdo->prepare("UPDATE invoice SET Status = 'active', username = ?
                WHERE id_invoice = ? AND id_user = ? AND username = ? AND Service_location = ?
                  AND Status IN ('processing', 'manual_review')");
            if (!$complete->execute([$name, $invoice, $user, $oldName, $panel]) || $complete->rowCount() !== 1) {
                return false;
            }
            $exists = $pdo->prepare("SELECT 1 FROM service_other WHERE id_user = ? AND value = ? AND type = 'wallet_purchase_effect' LIMIT 1");
            $exists->execute([$user, $invoice]);
            if ($exists->fetchColumn() === false) {
                WalletPurchaseEffects::seed($pdo, $user, $invoice, ['delivery' => []]);
            }
            return true;
        });
    }

    public static function purchase(array $context)
    {
        $order_id = $context['payment']['order_id'];
        $Payment_report = $context['payment']['report'];
        $steppay = $context['payment']['invoice_tag'];
        $Balance_id = $context['payment']['customer'];
        $rxCk = $context['payment']['checkout'];
        $rxToken = $context['fulfillment']['token'];
        $rxPhase = $context['fulfillment']['phase'];
        $rxBeforeState = $context['fulfillment']['before'];
        $rxTargetState = $context['fulfillment']['target'];
        $pdo =& $context['provider']['database'];
        $ManagePanel =& $context['provider']['panel'];
        $setting = $context['presentation']['settings'];
        $textbotlang =& $context['presentation']['language'];
        $datatextbot =& $context['presentation']['templates'];
        $keyboard =& $context['presentation']['keyboard'];
        $image = $context['presentation']['image'];
        $format_price_cart = $context['presentation']['price_display'];
        $paymentNote = $context['presentation']['payment_note'];
        $receipt = $context['presentation']['receipt'];
        $admin_ids = $context['reporting']['administrators'];
        $buyreport = $context['reporting']['purchase'];
        $otherreport = $context['reporting']['general'];
        $errorreport = $context['reporting']['error'];
        $porsantreport = $context['reporting']['affiliate'];

        $__invUsername = isset($steppay[1]) ? trim((string)$steppay[1]) : '';
        $__invOwner = trim((string)($Payment_report['id_user'] ?? ''));
        $get_invoice = false;
        $__invLookupError = false;
        if ($__invUsername !== '' && $__invOwner !== '') {
            try {
                $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :u AND id_user = :uid AND Status IN ('unpaid', 'Unpaid') ORDER BY id_invoice DESC LIMIT 1");
                $stmt->execute([':u' => $__invUsername, ':uid' => $__invOwner]);
                $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $__e) { $get_invoice = false; $__invLookupError = true; }
            if (!$get_invoice) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE LOWER(username) = LOWER(:u) AND id_user = :uid AND Status IN ('unpaid', 'Unpaid') ORDER BY id_invoice DESC LIMIT 1");
                    $stmt->execute([':u' => $__invUsername, ':uid' => $__invOwner]);
                    $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $__e) { $get_invoice = false; $__invLookupError = true; }
            }
        }
        // اگه با هیچ روشی پیدا نشد، قبل از اینکه به refund برسیم به ادمین گزارش بدیم و مستقیم برگردیم
        if (!$get_invoice && $__invLookupError) {
            if (function_exists('error_log')) {
                @error_log("[DirectPayment] invoice lookup error for order={$order_id} user={$Balance_id['id']} steppay[1]={$__invUsername} — retryable");
            }
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'invoice_lookup_error', true);
        }
        if (!$get_invoice) {
            if (function_exists('error_log')) {
                @error_log("[DirectPayment] invoice NOT FOUND for order={$order_id} user={$Balance_id['id']} steppay[1]={$__invUsername} — aborting WITHOUT refund (so cryptocheck stuck-refund can handle it cleanly)");
            }
            $__setting = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('setting', '*', null, null, 'select') : [];
            $__errReport = \Faoxima\Core\Database\Db::runtimeReady() ? (\Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'errorreport', 'select')['idreport'] ?? null) : null;
            $__txt = "⚠️ <b>فاکتور پیدا نشد برای ساخت سرویس</b>\n"
                   . "<blockquote>🛒 کد سفارش: <code>{$order_id}</code></blockquote>\n"
                   . "<blockquote>👤 کاربر: <code>{$Balance_id['id']}</code></blockquote>\n"
                   . "<blockquote>🔎 username موردنظر: <code>" . htmlspecialchars($__invUsername) . "</code></blockquote>\n"
                   . "ℹ️ DirectPayment بدون refund برگشت — لطفاً دستی بررسی کنید.";
            if (!empty($__setting['Channel_Report']) && function_exists('telegram')) {
                @telegram('sendmessage', [
                    'chat_id' => $__setting['Channel_Report'],
                    'message_thread_id' => $__errReport,
                    'text' => $__txt,
                    'parse_mode' => 'HTML',
                ]);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'invoice_not_found', false);
        }
        $userAgent = $Balance_id['agent'] ?? 'f';
        $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name AND (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([':name' => $get_invoice['name_product'], ':loc' => $get_invoice['Service_location'], ':agent' => $userAgent]);
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($get_invoice['name_product'] == "🛍 حجم دلخواه" || $get_invoice['name_product'] == "⚙️ سرویس دلخواه") {
            $info_product['data_limit_reset'] = "no_reset";
            $info_product['Volume_constraint'] = $get_invoice['Volume'];
            $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
            $info_product['code_product'] = "customvolume";
            $info_product['Service_time'] = $get_invoice['Service_time'];
            $info_product['price_product'] = $get_invoice['price_product'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name AND (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $stmt->execute([':name' => $get_invoice['name_product'], ':loc' => $get_invoice['Service_location'], ':agent' => $userAgent]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $username_ac = $get_invoice['username'];
        $randomString = bin2hex(random_bytes(2));
        $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $get_invoice['Service_location'], "select");

        // [panel-missing guard] اگه پنل پیدا نشد، اصلاً نباید بریم سراغ createUser چون قطعاً "Panel Not Found" برمی‌گردونه.
        // قبل از refund، به ادمین گزارش بدیم تا دلیل واقعی (مثلاً پنل حذف شده، نام عوض شده، Service_location خراب) مشخص بشه.
        if (!is_array($marzban_list_get) || empty($marzban_list_get['name_panel'])) {
            if (function_exists('error_log')) {
                @error_log("[DirectPayment] panel missing for order={$order_id} user={$Balance_id['id']} location='" . (string)($get_invoice['Service_location'] ?? '') . "' invoice={$get_invoice['id_invoice']} — aborting WITHOUT refund");
            }
            $__setting2 = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('setting', '*', null, null, 'select') : [];
            $__errReport2 = \Faoxima\Core\Database\Db::runtimeReady() ? (\Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'errorreport', 'select')['idreport'] ?? null) : null;
            $__txt2 = "⚠️ <b>پنل برای ساخت سرویس پیدا نشد</b>\n"
                    . "<blockquote>🛒 کد سفارش: <code>{$order_id}</code></blockquote>\n"
                    . "<blockquote>👤 کاربر: <code>{$Balance_id['id']}</code></blockquote>\n"
                    . "<blockquote>📍 لوکیشن ذخیره‌شده در فاکتور: <code>" . htmlspecialchars((string)($get_invoice['Service_location'] ?? '')) . "</code></blockquote>\n"
                    . "<blockquote>🧾 شناسه فاکتور: <code>{$get_invoice['id_invoice']}</code></blockquote>\n"
                    . "ℹ️ DirectPayment بدون refund برگشت — لطفاً پنل را در دیتابیس بررسی کنید.";
            if (!empty($__setting2['Channel_Report']) && function_exists('telegram')) {
                @telegram('sendmessage', [
                    'chat_id' => $__setting2['Channel_Report'],
                    'message_thread_id' => $__errReport2,
                    'text' => $__txt2,
                    'parse_mode' => 'HTML',
                ]);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'panel_not_found', true);
        }

        // [idempotent-refund guard] اگر این فاکتور قبلاً refund خورده (نشانه auto-refund تو dec_not_confirmed)،
        // دیگه نباید دوباره کیف پول رو شارژ کنیم — همینجا برمی‌گردیم و فقط لاگ می‌زنیم.
        // این جلوی double/triple-credit موقع retry گیر کردن سرویس رو می‌گیره.
        $__alreadyRefunded = false;
        try {
            $__pr = \Faoxima\Core\Database\Db::select("Payment_report", "dec_not_confirmed", "id_order", $order_id, "select");
            $__decNote = is_array($__pr) ? (string)($__pr['dec_not_confirmed'] ?? '') : '';
            if ($__decNote !== '' && stripos($__decNote, 'auto-refund') !== false) {
                $__alreadyRefunded = true;
            }
        } catch (Throwable $__e) { $__alreadyRefunded = false; }
        if ($__alreadyRefunded) {
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'already_refunded', false);
        }

        // [username normalize] اگر یوزرنیم فاکتور خالی/کوتاه‌تر از 3 کاراکتر بود، یکی معتبر بساز.
        // این از خطای پنل "Username must be at least 3 characters long" جلوگیری می‌کنه.
        if (!is_string($username_ac) || trim($username_ac) === '' || strlen(trim($username_ac)) < 3) {
            if ($rxPhase === 'reconcile') {
                return \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, 'buy_username_changed_during_reconciliation', ['username' => (string) $username_ac]);
            }
            $username_ac = preg_replace('/[^A-Za-z0-9-]/', '', str_replace('_', '-', (string)$Balance_id['id'])) . '-' . bin2hex(random_bytes(4));
            if (strlen($username_ac) < 3) $username_ac = 'u' . bin2hex(random_bytes(4));
            // فقط وقتی id_invoice معتبره update کن (جلوی خطای "Column id_invoice cannot be null" گرفته میشه)
            if (!empty($get_invoice['id_invoice'])) {
                try { \Faoxima\Core\Database\Db::update("invoice", "username", $username_ac, "id_invoice", $get_invoice['id_invoice']); } catch (Throwable $__e) { /* fail-open */ }
                try { \Faoxima\Core\Database\Db::update("Payment_report", "id_invoice", "getconfigafterpay|" . $username_ac, "id_order", $order_id); } catch (Throwable $__e) {}
            }
        }

        // [duplicate-username guard - REMOVED]
        // پچ قبلی یک حلقه pre-check با DataUser داشت که باعث می‌شد:
        //   - چندین API call به panel قبل از createUser → کند و گاهی timeout
        //   - اگر createUser قبلی نیمه‌کاره موفق بوده (yوزر ساخته شده ولی bot جواب نگرفته)، DataUser می‌گفت "exists" و یوزرنیم عوض می‌شد
        //   - هر retry یوزرنیم جدید → چندین یوزر زامبی توی پنل + سرویس نهایی هیچ‌وقت تحویل نشد
        // الان فقط روی duplicate-error واقعی از createUser (در پایین) retry می‌کنیم، که هم سریع‌تر و هم دقیق‌تره.
        // این رفتار همون چیزیه که در wallet-payment موفق عمل می‌کنه.
        $date = strtotime("+" . $get_invoice['Service_time'] . "days");
        if (intval($get_invoice['Service_time']) == 0) {
            $timestamp = 0;
        } else {
            $timestamp = strtotime(date("Y-m-d H:i:s", $date));
        }
        $datac = array(
            'expire' => $timestamp,
            'data_limit' => $get_invoice['Volume'] * pow(1024, 3),
            'id_invoice' => (string) $get_invoice['id_invoice'],
            'from_id' => $Balance_id['id'],
            'username' => $Balance_id['username'],
            'type' => 'buy'
        );
        if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($marzban_list_get)) {
            if ($rxPhase === 'reconcile') {
                return \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, 'national_stock_buy_unverifiable', ['username' => (string) $username_ac]);
            }
            if (!\Faoxima\Payments\Fulfillment\Settlement::prepare($pdo, $order_id, $rxToken, null, ['national_stock' => true, 'username' => (string) $username_ac])) {
                return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'fulfillment_prepare_failed', true);
            }
            if (is_array($info_product) && \Faoxima\Services\Fulfillment\InventoryProvisioning::nmStockCompleteBuyFromInventory($pdo, $Balance_id['id'], $Balance_id, $marzban_list_get, $info_product, $get_invoice['id_invoice'], $username_ac, false, 'paid_national_buy')) {
                $rxCompletion = \Faoxima\Payments\Fulfillment\Settlement::complete($pdo, $order_id, $rxToken, ['national_stock' => true, 'username' => (string) $username_ac]);
                if ($rxCompletion !== 'completed') {
                    return \Faoxima\Payments\Fulfillment\Settlement::completionOutcome($pdo, $order_id, $rxToken, $rxCompletion);
                }
                sendmessage($Balance_id['id'], $textbotlang['users']['selectoption'], $keyboard, 'HTML');
                return \Faoxima\Payments\Fulfillment\Claims::result('completed', 'national_stock');
            }
            $__refundNs = \Faoxima\Payments\Settlement\Refunds::paymentOnce($pdo, $order_id, $Balance_id['id'], $Payment_report['price'], 'بازگشت وجه - موجودی انبار ملی تمام شده', (string)$get_invoice['id_invoice']);
            if ($__refundNs === 'refunded') {
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_purchase_stock_empty_refunded'), $keyboard, 'HTML');
            } elseif ($__refundNs === 'failed') {
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_purchase_stock_empty_refund_failed'), $keyboard, 'HTML');
            }
            return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'national_stock_empty', $__refundNs);
        }
        // [zombie-rescue] قبل از تلاش جدید، اگه قبلاً تو panel یوزری برای این کاربر ساخته شده (zombie)،
        // اول بررسی کن: شاید createUser تو call قبلی موفق بوده فقط response نرسیده. اگه پیدا کردیم،
        // از همون استفاده کن (بدون ساختن یوزر جدید) — این جلوی تولید بیشتر zombie رو می‌گیره.
        $ManagePanel = \Faoxima\Services\Panels\PanelSetup::rxEnsureManagePanel();
        if (!is_object($ManagePanel)) {
            if (function_exists('rx_log_event')) {
                rx_log_event('DIRECT_PAYMENT_NO_MANAGE_PANEL', 'ManagePanel unavailable; buy aborted without side effects', ['id_order' => $order_id]);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'manage_panel_unavailable', true);
        }
        $rxReconcilable = \Faoxima\Services\Fulfillment\Delivery::panelReconcilable($marzban_list_get);
        $rxAllowRename = true;
        $__isRetryCall = false;
        $dataoutput = null;
        if ($rxPhase === 'reconcile') {
            $rxExpectedUser = is_array($rxTargetState) ? (string) ($rxTargetState['username'] ?? '') : '';
            if (!$rxReconcilable || !is_array($rxBeforeState) || ($rxBeforeState['exists'] ?? null) !== false || $rxExpectedUser === '' || $rxExpectedUser !== (string) $username_ac) {
                return \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, 'buy_result_unverifiable', ['username' => (string) $username_ac]);
            }
            $rxSnap = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($ManagePanel, (string) $marzban_list_get['name_panel'], (string) $username_ac);
            if (empty($rxSnap['ok'])) {
                return \Faoxima\Payments\Fulfillment\Settlement::defer($pdo, $order_id, $rxToken, 'buy_panel_read_failed: ' . (string) ($rxSnap['error'] ?? ''));
            }
            if (!empty($rxSnap['exists'])) {
                $dataoutput = \Faoxima\Services\Fulfillment\Adjustments::adoptCreated($rxSnap['raw']);
            } elseif (!\Faoxima\Payments\Fulfillment\Settlement::markApplying($pdo, $order_id, $rxToken)) {
                return \Faoxima\Payments\Fulfillment\Claims::result('already_processing', 'ownership_lost');
            } else {
                $rxAllowRename = false;
            }
        } else {
            $rxBeforeState = ['username' => (string) $username_ac, 'exists' => null];
            if ($rxReconcilable) {
                $rxSnap = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($ManagePanel, (string) $marzban_list_get['name_panel'], (string) $username_ac);
                if (empty($rxSnap['ok'])) {
                    return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'buy_panel_read_failed', true);
                }
                $rxBeforeState['exists'] = !empty($rxSnap['exists']);
            }
            $rxTargetState = ['username' => (string) $username_ac, 'panel' => (string) $marzban_list_get['name_panel']];
            if (!\Faoxima\Payments\Fulfillment\Settlement::prepare($pdo, $order_id, $rxToken, $rxBeforeState, $rxTargetState)) {
                return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'fulfillment_prepare_failed', true);
            }
            try {
                $__pr2 = \Faoxima\Core\Database\Db::select("Payment_report", "crypto_check_count", "id_order", $order_id, "select");
                $__cnt = is_array($__pr2) ? (int)($__pr2['crypto_check_count'] ?? 0) : 0;
                if ($__cnt >= 1) $__isRetryCall = true;
            } catch (Throwable $__e) { /* ignore */ }
        }

        if ($__isRetryCall) {
            // در حالت retry، اول چک کن یوزر در پنل وجود داره یا نه (با همون username فاکتور)
            try {
                $__zombieCheck = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
                if (is_array($__zombieCheck) && !empty($__zombieCheck['username']) && (string)$__zombieCheck['username'] === (string)$username_ac) {
                    // یوزر تو پنل هست! یعنی createUser قبلی واقعاً موفق بوده، فقط bot جواب نگرفته.
                    // از همون استفاده کن.
                    $dataoutput = $__zombieCheck;
                    $dataoutput['status'] = 'successful';
                    if (empty($dataoutput['configs']) && !empty($dataoutput['links'])) {
                        $dataoutput['configs'] = is_array($dataoutput['links']) ? $dataoutput['links'] : explode("\n", (string)$dataoutput['links']);
                    }
                }
            } catch (Throwable $__e) { /* fail-open */ }
        }

        if (empty($dataoutput) || empty($dataoutput['username'])) {
            try {
                $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac, $rxAllowRename);
            } catch (Throwable $rxPanelError) {
                return \Faoxima\Payments\Fulfillment\Settlement::reconcileLater($pdo, $order_id, $rxToken, 'buy_panel_exception: ' . $rxPanelError->getMessage());
            }
            if (!empty($dataoutput['renamed_from'])) {
                $username_ac = \Faoxima\Services\Panels\PanelMessages::rx_adopt_created_username($dataoutput, $username_ac, $get_invoice['id_invoice'] ?? null);
                try { \Faoxima\Core\Database\Db::update("Payment_report", "id_invoice", "getconfigafterpay|" . $username_ac, "id_order", $order_id); } catch (Throwable $__e2) {}
                \Faoxima\Services\Panels\PanelMessages::rx_notify_username_renamed($Balance_id['id'], $dataoutput, $marzban_list_get);
            }
        }
        if (($marzban_list_get['type'] ?? '') === 'jovadpanel' && ($dataoutput['code'] ?? '') === 'uncertain') {
            return \Faoxima\Payments\Fulfillment\Settlement::reconcileLater($pdo, $order_id, $rxToken, 'jovad_creation_result_unknown');
        }
        if (empty($dataoutput['username']) && $rxReconcilable && is_array($rxBeforeState) && ($rxBeforeState['exists'] ?? null) === false) {
            $rxSnap = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($ManagePanel, (string) $marzban_list_get['name_panel'], (string) $username_ac);
            if (empty($rxSnap['ok'])) {
                return \Faoxima\Payments\Fulfillment\Settlement::reconcileLater($pdo, $order_id, $rxToken, 'buy_result_unknown: ' . (string) ($rxSnap['error'] ?? ''));
            }
            if (!empty($rxSnap['exists'])) {
                $dataoutput = \Faoxima\Services\Fulfillment\Adjustments::adoptCreated($rxSnap['raw']);
            }
        }
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
            $__refundCu = \Faoxima\Payments\Settlement\Refunds::paymentOnce($pdo, $order_id, $Balance_id['id'], $Payment_report['price'], 'بازگشت وجه - خطا در ساخت سرویس', (string)$get_invoice['id_invoice']);
            if ($__refundCu === 'duplicate') {
                return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'create_failed', $__refundCu);
            }
            // پیام UI fallback اگه textbotlang در دسترس نباشه (مثلاً وقتی از cron صدا زده میشه)
            $__uiErr = isset($textbotlang['users']['sell']['ErrorConfig']) && is_string($textbotlang['users']['sell']['ErrorConfig']) && trim($textbotlang['users']['sell']['ErrorConfig']) !== ''
                ? $textbotlang['users']['sell']['ErrorConfig']
                : "❌ متاسفانه ساخت سرویس با خطا مواجه شد. مبلغ پرداختی به کیف پول شما برگشت داده شد.";
            if ($__refundCu === 'refunded') {
                sendmessage($Balance_id['id'], $__uiErr, $keyboard, 'HTML');
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_purchase_failed_refunded', ['amount' => \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price'])]), $keyboard, 'HTML');
            } else {
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_purchase_failed_refund_failed'), $keyboard, 'HTML');
            }
            $texterros = "
⭕️ خطا در ساخت کانفیگ
<blockquote>✍️ دلیل خطا : {$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : {$Balance_id['id']}</blockquote>
<blockquote>نام کاربری کاربر : @{$Balance_id['username']}</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'create_failed', $__refundCu);
        }
        $__walletPortion = (float)$get_invoice['price_product'] - (float)($Payment_report['price'] ?? 0);
        if ($__walletPortion < 0) {
            $__walletPortion = 0;
        }
        $Balance_prims = (float)$Balance_id['Balance'] - $__walletPortion;
        if ($Balance_prims <= 0) {
            $Balance_prims = 0;
        }
        // [CRITICAL: mark invoice active EARLY]
        // اگه createUser موفق بوده، همین الان قبل از هر sendmessage/QR code generation/الخ که ممکنه hang کنه،
        // invoice رو active علامت بزن. این جلوی retry بعدی توسط cron رو می‌گیره حتی اگه پیام تلگرام hang کنه.
        $__doneNote = '[service-created at ' . date('Y-m-d H:i:s') . ' username=' . $dataoutput['username'] . ']';
        $rxStatements = array_merge(
            \Faoxima\Orders\Checkout\Settlement::settlementStatements($rxCk, $order_id, $Balance_id['id'], (int) round($__walletPortion), 'خرید سرویس'),
            [["UPDATE Payment_report SET dec_not_confirmed = CASE WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN ? ELSE CONCAT(dec_not_confirmed, ' | ', ?) END WHERE id_order = ?", [$__doneNote, $__doneNote, $order_id]]]
        );
        if (!empty($get_invoice['id_invoice'])) {
            $rxStatements[] = ["UPDATE invoice SET Status = 'active' WHERE id_invoice = ?", [$get_invoice['id_invoice']]];
        }
        $rxCompletion = \Faoxima\Payments\Fulfillment\Settlement::complete($pdo, $order_id, $rxToken, ['username' => (string) $dataoutput['username'], 'renamed_from' => $dataoutput['renamed_from'] ?? null], $rxStatements);
        if ($rxCompletion !== 'completed') {
            return \Faoxima\Payments\Fulfillment\Settlement::completionOutcome($pdo, $order_id, $rxToken, $rxCompletion);
        }
        if (!empty($get_invoice['id_invoice']) && ($marzban_list_get['type'] ?? '') !== 'Manualsale') {
            self::persistProvisionedUserInfo($pdo, (string) $get_invoice['id_invoice'], $dataoutput);
            \Faoxima\Services\Delivery\AutoFileQueue::enqueue($pdo, $marzban_list_get,
                (string) $get_invoice['id_invoice'], (string) $get_invoice['id_user']);
        }
        $Shoppinginfo = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "📚 مشاهده آموزش استفاده ", 'callback_data' => "helpbtn"],
                ]
            ]
        ]);
        $output_config_link = "";
        $config = "";
        if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            $rxPaidTextConfigs = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'rxDeliveryTextConfigs') ? \Faoxima\Services\Panels\Drivers\JovadPanel::rxDeliveryTextConfigs($dataoutput['configs']) : $dataoutput['configs'];
            foreach ($rxPaidTextConfigs as $link) {
                $config .= "\n" . $link;
            }
        }
        $deliveryPanel = \Faoxima\Services\Delivery\ServiceDelivery::rxPurchaseDeliveryPanel($marzban_list_get, $dataoutput['subscription_url'] ?? '');
        $output_config_link = $deliveryPanel['sublink'] == "onsublink" ? \Faoxima\Services\Delivery\ServiceDelivery::rxResolveConnectionLink($deliveryPanel, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
        $rxAfterPayTpl = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $rxAfterPayTpl = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $rxAfterPayTpl;
        if (intval($get_invoice['Service_time']) == 0)
            $get_invoice['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
        if (intval($get_invoice['Volume']) == 0)
            $get_invoice['Volume'] = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', "<code>" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($dataoutput['username'], $marzban_list_get) . "</code>", $rxAfterPayTpl);
        $textcreatuser = str_replace('{name_service}', $get_invoice['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $get_invoice['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $get_invoice['Volume'], $textcreatuser);
        $textcreatuser = \Faoxima\Core\Database\Db::applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        if (intval($get_invoice['Volume']) == 0) {
            $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
        }
        if ($marzban_list_get['type'] == "Manualsale") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            \Faoxima\Core\Database\Db::update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $get_invoice['id_invoice']);
        }
        \Faoxima\Services\Delivery\ServiceDelivery::sendMessageService($deliveryPanel, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $get_invoice['id_invoice'], $get_invoice['id_user'], $image);
        $partsdic = explode("_", $Balance_id['Processing_value_four']);
        if ($partsdic[0] == "dis") {
            $__discountUseLegacy = true;
            if (is_callable([\Faoxima\Orders\Discounts\SellDiscounts::class, 'discountMarkUsedAtomic'])) {
                $__discountMark = \Faoxima\Orders\Discounts\SellDiscounts::discountMarkUsedAtomic($pdo, $partsdic[1], $Balance_id['id'], 'sell');
                $__discountUseLegacy = empty($__discountMark['ok']) && (string)($__discountMark['reason'] ?? '') === 'db_unavailable';
            }
            if ($__discountUseLegacy) {
                $SellDiscountlimit = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
                $value = intval($SellDiscountlimit['usedDiscount']) + 1;
                \Faoxima\Core\Database\Db::update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
                $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
                $stmt->bindParam(':id_user', $Balance_id['id']);
                $stmt->bindParam(':code', $partsdic[1]);
                $stmt->execute();
            }
            $text_report = "⭕️ یک کاربر با نام کاربری @{$Balance_id['username']}  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'parse_mode' => 'HTML',
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $affiliateCommissionPaid = \Faoxima\Cashback\AffiliateCommissions::payAffiliateCommissionForPurchase($pdo, $context['provider']['commission_settings'], $Balance_id['id'], $Balance_id['affiliates'] ?? null, (float) $Payment_report['price']);
        if ($affiliateCommissionPaid !== null) {
            $result = number_format($affiliateCommissionPaid);
            $dateacc = date('Y/m/d H:i:s');
            $textadd = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_affiliate_commission_user_tpl', "🎁  پرداخت پورسانت

        مبلغ {amount} تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید"), ['amount' => $result]);
            $textreportport = "
مبلغ $result به کاربر {$Balance_id['affiliates']} برای پورسانت از کاربر {$Balance_id['id']} واریز گردید
<blockquote>تایم : $dateacc</blockquote>";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $porsantreport,
                    'text' => $textreportport,
                    'parse_mode' => "HTML"
                ]);
            }
            sendmessage($Balance_id['affiliates'], $textadd, null, 'HTML');
        }
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($Balance_id['number_username']) + 1;
            \Faoxima\Core\Database\Db::update("user", "number_username", $value, "id", $Balance_id['id']);
            if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                $value = intval($setting['numbercount']) + 1;
                \Faoxima\Core\Database\Db::update("setting", "numbercount", $value);
            }
        }
        $balanceformatsell = \Faoxima\Core\Database\Db::select("user", "Balance", "id", $get_invoice['id_user'], "select")['Balance'];
        $balanceformatsell = number_format($balanceformatsell, 0);
        $balancebefore = number_format($Balance_id['Balance'], 0);
        $rxFmtInvoicePriceProduct = \Faoxima\Core\Support\Formatting::rxFormatToman($get_invoice['price_product']);
        $rxFmtPaymentReportPrice = \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price']);
        $timejalali = jdate('Y/m/d H:i:s');
        $textonebuy = "";
        if ($countinvoice == 1) {
            $textonebuy = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_first_buy_flag', "📌 خرید اول کاربر");
        }
        // [fallback] اگه textbotlang در cron context کامل لود نشده، text رو با مقدار default پر کن تا تلگرام reject نکنه
        $__mngBtnText = '👤 مدیریت کاربر';
        if (isset($textbotlang['Admin']['ManageUser']['mangebtnuser']) && is_string($textbotlang['Admin']['ManageUser']['mangebtnuser']) && trim($textbotlang['Admin']['ManageUser']['mangebtnuser']) !== '') {
            $__mngBtnText = $textbotlang['Admin']['ManageUser']['mangebtnuser'];
        }
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $__mngBtnText, 'callback_data' => 'manageuser_' . $Balance_id['id']],
                ],
            ]
        ]);
        $text_report = "📣 جزئیات ساخت اکانت در ربات بعد پرداخت ثبت شد .

$textonebuy
<blockquote>▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@{$Balance_id['username']}</blockquote>
<blockquote>▫️نام کاربری کانفیگ :" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($username_ac, $marzban_list_get) . "</blockquote>
<blockquote>▫️لوکیشن سرویس : {$get_invoice['Service_location']}</blockquote>
<blockquote>▫️زمان خریداری شده :{$get_invoice['Service_time']} روز</blockquote>
<blockquote>▫️نام محصول خریداری شده :{$get_invoice['name_product']}</blockquote>
<blockquote>▫️حجم خریداری شده : {$get_invoice['Volume']} GB</blockquote>
<blockquote>▫️موجودی قبل خرید : $balancebefore تومان</blockquote>
<blockquote>▫️موجودی بعد خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️کد پیگیری: {$get_invoice['id_invoice']}</blockquote>
<blockquote>▫️نوع کاربر : {$Balance_id['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$Balance_id['number']}</blockquote>
<blockquote>▫️قیمت محصول : {$rxFmtInvoicePriceProduct} تومان</blockquote>
<blockquote>▫️قیمت نهایی : {$rxFmtPaymentReportPrice} تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            \Faoxima\Telegram\Support\Transport::rx_sendTopicReport([
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $buyreport,
                'text' => $text_report,
                'parse_mode' => "HTML",
                'reply_markup' => $Response
            ], ['flow' => 'direct_payment', 'order_id' => (string) ($get_invoice['id_invoice'] ?? ''), 'user_id' => (string) ($Balance_id['id'] ?? '')]);
        }
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
            \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('new_sub', [
                'user_id'    => $Balance_id['id'],
                'amount'     => $get_invoice['name_product'],
                'price'      => number_format((float)$Payment_report['price']),
                'panel_name' => $marzban_list_get['name_panel'] ?? '',
                'category'   => $info_product['category'] ?? '',
            ], $setting);
        }
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_score_earned_1', "📌شما 1 امتیاز جدید کسب کردید."), null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            \Faoxima\Core\Database\Db::update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "username", $get_invoice['username']);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $get_invoice['id_invoice']);
            $rxFmtBalanceBeforeBuy = \Faoxima\Core\Support\Formatting::rxFormatToman($Balance_id['Balance']);
            $textconfrom = "✅ پرداخت تایید شده
🛍خرید سرویس
▫️نام کاربری کانفیگ :" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($username_ac, $marzban_list_get) . "
▫️لوکیشن سرویس : {$get_invoice['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل خرید  : {$rxFmtBalanceBeforeBuy}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}

";
            ReceiptMessage::updateReceipt($receipt, $textconfrom);
        }
        return \Faoxima\Payments\Fulfillment\Claims::result('completed');
    }
}
