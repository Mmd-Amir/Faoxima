<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use Throwable;

final class ReceiptAudit
{
    public static function cashback($runtimePdo, array $report): int
    {
        if (!method_exists(\Faoxima\Payments\Settlement\SettlementRuntime::class, 'rx_cashback_credit_once') || !\Faoxima\Core\Database\Db::runtimeReady()) {
            return 0;
        }
        try {
            $user = \Faoxima\Core\Database\Db::select('user', '*', 'id', $report['id_user'], 'select', ['cache' => false]);
            if (!is_array($user)) {
                return 0;
            }
            $row = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'chashbackcart', 'select');
            $pct = is_array($row) ? (string) ($row['ValuePay'] ?? '0') : '0';
            if ($pct === '0' || $pct === '' || !is_numeric($pct)) {
                return 0;
            }
            $eligible = !method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_cashbackEligibleForKey')
                || \Faoxima\Cashback\Eligibility::cashbackEligibleForKey($runtimePdo, 'chashbackcart', $user['register'] ?? null, $report['id_invoice'] ?? null, $user['id'] ?? null, $report['id_order'] ?? null);
            if (!$eligible) {
                return 0;
            }
            $amount = (int) floor(((int) $report['price'] * (int) $pct) / 100);
            if ($amount <= 0) {
                return 0;
            }
            if (\Faoxima\Cashback\Credits::creditOnce($runtimePdo, $report['id_order'], $user['id'], $amount, 'chashbackcart', 'هدیه بازگشت وجه کارت به کارت (تأیید با بررسی)') !== 'credited') {
                return 0;
            }
            if (function_exists('sendmessage')) {
                sendmessage($user['id'], \Faoxima\Telegram\Support\MessageTemplates::get('notify_cashback_gift', ['amount' => (method_exists(\Faoxima\Core\Support\Formatting::class, 'rxFormatToman') ? \Faoxima\Core\Support\Formatting::rxFormatToman($amount) : number_format($amount))]), null, 'HTML');
            }
            return $amount;
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_CASHBACK_FAILED', $e->getMessage(), ['id_order' => $report['id_order'] ?? null]);
            return 0;
        }
    }

    public static function reportMarkerFetch(PDO $pdo, string $action, ?int $depositId, ?string $orderId): ?array
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_audit WHERE action = ? AND deposit_id <=> ? AND id_order <=> ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$action, $depositId, $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_REPORT_MARKER_READ_FAILED', $e->getMessage(), ['action' => $action, 'deposit_id' => $depositId]);
            throw $e;
        }
    }

    public static function reportMarkerSave(PDO $pdo, ?int $markerId, string $action, ?int $depositId, ?string $orderId, ?string $actor, array $data): ?int
    {
        $state = substr((string) ($data['state'] ?? 'failed'), 0, 40);
        $reason = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($reason === false) {
            $reason = json_encode(['state' => $state], JSON_UNESCAPED_SLASHES);
        }
        $receivedRial = isset($data['received_rial']) && $data['received_rial'] !== null ? (int) $data['received_rial'] : null;
        try {
            if ($markerId !== null) {
                $stmt = $pdo->prepare("UPDATE card_sms_audit SET outcome = ?, reason = ?, received_rial = COALESCE(?, received_rial) WHERE id = ?");
                $stmt->execute([$state, $reason, $receivedRial, $markerId]);
                return $markerId;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $orderId,
                $depositId,
                $actor,
                $action,
                isset($data['principal_toman']) ? (int) $data['principal_toman'] : null,
                isset($data['fee_toman']) ? (int) $data['fee_toman'] : null,
                isset($data['expected_rial']) ? (int) $data['expected_rial'] : null,
                $receivedRial,
                isset($data['credit_toman']) ? (int) $data['credit_toman'] : null,
                $state,
                $reason,
                time(),
            ]);
            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_REPORT_MARKER_WRITE_FAILED', $e->getMessage(), ['action' => $action, 'deposit_id' => $depositId]);
            return null;
        }
    }
}
