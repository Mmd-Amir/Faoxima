<?php

namespace Faoxima\Telegram\Support;

use Faoxima\Core\Database\Db;
use Faoxima\Orders\Pricing\ExchangePricing;
use Faoxima\Services\Renewal\Plans;
use PDO;

final class ServiceProductIdentity
{
    private array $products = [];
    private array $productIdsByCode = [];
    private array $renewals = [];

    public function __construct(PDO $pdo, string $userId)
    {
        $catalog = $pdo->query("SELECT id, code_product, name_product FROM product");
        while ($product = $catalog->fetch(PDO::FETCH_ASSOC)) {
            $id = (string) $product['id'];
            $this->products[$id] = $product['name_product'];
            $code = trim((string) $product['code_product']);
            if ($code !== '') {
                $this->productIdsByCode[$code][] = $id;
            }
        }
        $this->loadRenewals($pdo, $userId);
    }

    public static function recordPurchase(string $invoiceId, array $product, ?array $snapshot = null): void
    {
        $id = self::catalogId($product['id'] ?? null);
        if ($id !== null) {
            $snapshot = ($snapshot ?? []) + ['catalog_product_id' => $id];
        }
        ExchangePricing::fxStoreInvoiceSnapshot($invoiceId, $snapshot);
    }

    public static function linkRenewal(string|false $value, string $invoiceId, array $product): string|false
    {
        $id = self::catalogId($product['id'] ?? null);
        if ($id === null || $value === false) {
            return $value;
        }
        $target = json_decode($value, true);
        if (!is_array($target)) {
            return $value;
        }
        $linked = json_encode($target + ['invoice_id' => $invoiceId, 'catalog_product_id' => $id], JSON_UNESCAPED_UNICODE);
        return $linked !== false && mb_strlen($linked, 'UTF-8') <= 1000 ? $linked : $value;
    }

    public function productForInvoice(array $invoice): ?array
    {
        $snapshot = json_decode((string) ($invoice['pricing_snapshot'] ?? ''), true);
        $event = $this->renewals[$invoice['id_invoice']] ?? $snapshot;
        $code = trim((string) ($event['code_product'] ?? $event['product'] ?? ''));
        $id = self::catalogId($event['catalog_product_id'] ?? null);
        if ($id === null && $code !== '' && !Plans::renewalIsCustomCode($code)) {
            $matches = $this->productIdsByCode[$code] ?? [];
            $id = count($matches) === 1 ? $matches[0] : null;
        }
        if ($id === null) {
            return null;
        }
        return ['id' => $id, 'name' => trim((string) ($this->products[$id] ?? ''))];
    }

    private function loadRenewals(PDO $pdo, string $userId): void
    {
        $verified = [];
        $verifiedPayments = [];
        if (Db::rxTableColumnPresent($pdo, 'renewal_operation', 'id_invoice')) {
            $operations = $pdo->prepare("SELECT r.op_id, r.id_invoice, r.username, r.updated_at, p.id_invoice AS payment_target
                FROM renewal_operation r JOIN invoice i ON i.id_invoice = r.id_invoice
                LEFT JOIN Payment_report p ON r.source = 'payment' AND p.id_order = r.ref AND p.id_user = i.id_user
                WHERE i.id_user = ? AND r.state = 'verified' AND r.source IN ('wallet', 'payment', 'miniapp')");
            $operations->execute([$userId]);
            while ($operation = $operations->fetch(PDO::FETCH_ASSOC)) {
                $verified[$operation['op_id']] = $operation;
                if (!empty($operation['payment_target'])) {
                    $verifiedPayments[$operation['payment_target']][$operation['op_id']] = $operation;
                }
            }
        }

        $history = $pdo->prepare("SELECT id, username, value, output, time FROM service_other
            WHERE id_user = ? AND type = 'extend_user' AND status = 'paid'");
        $history->execute([$userId]);
        while ($row = $history->fetch(PDO::FETCH_ASSOC)) {
            $target = json_decode($row['value'], true);
            $result = json_decode($row['output'], true);
            if (!is_array($target) || !is_array($result) || !empty($result['queued'])) {
                continue;
            }
            $operation = $verified[$result['operation_id'] ?? ''] ?? null;
            if (empty($result['operation_id']) && !empty($target['id_order'])) {
                $paymentTarget = 'getextenduser|' . $row['username'] . '%' . $target['id_order'];
                $matches = $verifiedPayments[$paymentTarget] ?? [];
                $operation = count($matches) === 1 ? reset($matches) : null;
            }
            $invoiceId = (string) ($target['invoice_id'] ?? $operation['id_invoice'] ?? '');
            if ($operation !== null && ($operation['id_invoice'] !== $invoiceId || $operation['username'] !== $row['username'])) {
                continue;
            }
            if ($operation === null && (empty($result['status']) || !empty($result['pending_verification']))) {
                continue;
            }
            $appliedAt = (int) ($operation['updated_at'] ?? strtotime($row['time']));
            $this->rememberRenewal($invoiceId, $target, $appliedAt, (int) $row['id']);
        }

        if (Db::rxTableColumnPresent($pdo, 'queued_renewal', 'id_invoice')) {
            $reservations = $pdo->prepare("SELECT id, id_invoice, code_product, applied_at FROM queued_renewal
                WHERE id_user = ? AND status = 'applied'");
            $reservations->execute([$userId]);
            while ($reservation = $reservations->fetch(PDO::FETCH_ASSOC)) {
                $this->rememberRenewal((string) $reservation['id_invoice'], $reservation, (int) strtotime((string) $reservation['applied_at']), (int) $reservation['id']);
            }
        }
    }

    private function rememberRenewal(string $invoiceId, array $target, int $appliedAt, int $sequence): void
    {
        $code = trim((string) ($target['code_product'] ?? ''));
        if ($invoiceId === '' || $appliedAt <= 0 || $code === '' || Plans::renewalIsCustomCode($code)) {
            return;
        }
        $order = [$appliedAt, $sequence];
        if (!isset($this->renewals[$invoiceId]) || $order > $this->renewals[$invoiceId]['order']) {
            $this->renewals[$invoiceId] = $target + ['order' => $order];
        }
    }

    private static function catalogId($id): ?string
    {
        return (is_string($id) || is_int($id)) && preg_match('/^[1-9][0-9]*$/D', (string) $id) ? (string) $id : null;
    }
}
