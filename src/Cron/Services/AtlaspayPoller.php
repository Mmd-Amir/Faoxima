<?php

namespace Faoxima\Cron\Services;

use Faoxima\Cron\Runtime\PollRun;
use Faoxima\Payments\Fulfillment\PaymentPolicy;
use Faoxima\Payments\Gateways\Atlaspay\BotRuntime;
use Faoxima\Payments\Gateways\Atlaspay\Client;
use Faoxima\Payments\Settlement\ConfirmationFunctions;
use PDO;

final class AtlaspayPoller
{
    private const SUCCESS = ['confirmed', 'settled'];
    private const DEAD = ['rejected', 'expired', 'cancelled'];

    private PDO $pdo;
    private $setting;
    private $reportThread;

    public function __construct(PDO $pdo, $setting, $reportThread)
    {
        $this->pdo = $pdo;
        $this->setting = $setting;
        $this->reportThread = $reportThread;
    }

    public function run(): void
    {
        $window = PaymentPolicy::cronWindowSql();
        $stmt = null;
        PollRun::cursorBatch(
            $this->pdo,
            'atlaspaycheck',
            'atlaspay_poll',
            function (string $after, $ceiling) use ($window, &$stmt): array {
                if ($stmt === null) {
                    $stmt = $this->pdo->prepare(
                        "SELECT id, id_order, atlaspay_order_id, price, payment_Status, payment_processing_version, atlaspay_total_amount, atlaspay_local_state, id_user
                           FROM Payment_report
                          WHERE $window AND payment_Status = 'Unpaid'
                            AND Payment_Method = 'atlaspay'
                            AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom')
                            AND atlaspay_order_id IS NOT NULL
                            AND atlaspay_order_id <> ''
                            AND id > ? AND id <= ?
                          ORDER BY id ASC
                          LIMIT 30"
                    );
                }
                $stmt->execute([$after, $ceiling]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            },
            function (array $row): void {
                $this->check($row);
            },
            'atlaspay_poll_finished',
            function () use ($window): array {
                $late = $this->pdo->prepare(
                    "SELECT id_order, atlaspay_order_id, price, payment_Status, payment_processing_version, atlaspay_total_amount, atlaspay_local_state, id_user
                       FROM Payment_report
                      WHERE $window AND payment_Status = 'expire'
                        AND Payment_Method = 'atlaspay'
                        AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom')
                        AND payment_processing_version >= 2
                        AND atlaspay_order_id IS NOT NULL
                        AND atlaspay_order_id <> ''
                        AND atlaspay_created_at >= ?
                        AND (atlaspay_last_checked_at IS NULL OR atlaspay_last_checked_at <= ?)
                      ORDER BY atlaspay_last_checked_at ASC, id ASC
                      LIMIT 20"
                );
                $late->execute([time() - 172800, time() - 900]);
                return $late->fetchAll(PDO::FETCH_ASSOC);
            }
        );
    }

    private function check(array $row): void
    {
        $orderId = (string) $row['id_order'];
        $atlaspayOrderId = trim((string) $row['atlaspay_order_id']);
        if ($atlaspayOrderId === '') {
            return;
        }

        $check = Client::checkOrder($atlaspayOrderId);
        if ((int) ($row['payment_processing_version'] ?? 0) >= 2 && method_exists(BotRuntime::class, 'atlaspayOfficialV2Process')) {
            if (method_exists(BotRuntime::class, 'atlaspayCustomUpdateOfficialChecked')) {
                BotRuntime::atlaspayCustomUpdateOfficialChecked($orderId);
            }
            BotRuntime::atlaspayOfficialV2Process($row, $check);
            return;
        }
        if (!is_array($check) || (int) ($check['id'] ?? 0) !== (int) $atlaspayOrderId) {
            return;
        }

        $status = (string) ($check['status'] ?? '');
        if (in_array($status, self::DEAD, true)) {
            if ($status === 'expired' || $status === 'cancelled') {
                ConfirmationFunctions::payment_mark_expired($orderId);
            } else {
                ConfirmationFunctions::payment_notify_user_failed($orderId, 'پرداخت توسط اطلس‌پی رد شد');
            }
            return;
        }
        if (!in_array($status, self::SUCCESS, true)) {
            return;
        }

        $actualReceived = isset($check['actualReceivedAmountToman']) ? (int) $check['actualReceivedAmountToman'] : null;
        if (!empty($check['requiresManualDelivery'])) {
            $this->reportShortfall($orderId, $row, $actualReceived);
            return;
        }

        ConfirmationFunctions::payment_confirm_paid($orderId, 'chashbackatlaspay', [
            'method'      => 'atlaspay',
            'thread_id'   => $this->reportThread,
            'extra_lines' => array_filter([
                $actualReceived !== null ? ('💸 مبلغ نهایی تأییدشده توسط اطلس‌پی : ' . number_format($actualReceived) . ' تومان') : '',
                '🔁 تایید از طریق پولر',
            ]),
        ]);
    }

    private function reportShortfall(string $orderId, array $row, ?int $actualReceived): void
    {
        $channelReport = is_array($this->setting) ? (string) ($this->setting['Channel_Report'] ?? '') : '';
        if ($channelReport === '' || !function_exists('telegram')) {
            return;
        }
        $text = "⚠️ سفارش اطلس‌پی با کسری واریز تایید شد — نیاز به بررسی دستی.\n"
            . "🛒 کد فاکتور: <code>" . htmlspecialchars($orderId) . "</code>\n"
            . "💰 مبلغ سفارش: " . number_format((int) $row['price']) . " تومان\n"
            . ($actualReceived !== null ? ('💸 مبلغ واریزی واقعی: ' . number_format($actualReceived) . ' تومان') : '');
        telegram('sendmessage', [
            'chat_id'    => $channelReport,
            'message_thread_id' => $this->reportThread,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }
}
