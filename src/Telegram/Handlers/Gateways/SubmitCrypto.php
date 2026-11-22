<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $connect, $datatextbot, $dsm, $from_id, $keyboard.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class SubmitCrypto
{
    public static function handle(
        $connect,
        $datatextbot,
        $dsm,
        $from_id,
        $keyboard
    ) {
        $orderId = $dsm[1];
                $reportStmt = $connect->prepare("SELECT id_order, payment_Status FROM Payment_report WHERE id_order = ? AND id_user = ? LIMIT 1");
                $userIdStr = (string) $from_id;
                $reportStmt->bind_param('ss', $orderId, $userIdStr);
                $reportStmt->execute();
                $rowChk = $reportStmt->get_result()->fetch_assoc();
                $reportStmt->close();
                if (!is_array($rowChk)) {
                    sendmessage($from_id, $datatextbot['dyn_wallet_invoice_not_found'] ?? "❌ فاکتور یافت نشد.", $keyboard, 'HTML');
                    return;
                }
                if (!in_array($rowChk['payment_Status'], ['Unpaid', 'AwaitingHash'], true)) {
                    sendmessage($from_id, $datatextbot['dyn_wallet_invoice_not_pending'] ?? "❌ این فاکتور دیگر در حالت انتظار پرداخت نیست.", $keyboard, 'HTML');
                    return;
                }
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $orderId, "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('digitaltron_hash_input', $from_id);
                $cancelKb = json_encode([
                    'inline_keyboard' => [
                        [['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('common_btn_cancel'), 'callback_data' => 'cancel_hash_input']],
                    ],
                ], JSON_UNESCAPED_UNICODE);
                sendmessage(
                    $from_id,
                    "📨 لطفاً <b>هش (TxID) تراکنش</b> را ارسال کنید.\n\n"
                    . "هر دو فرمت قابل قبول است:\n"
                    . "• هش خام (مثلاً <code>09d6aaee138447689f92a0ee7a4382dd01fcc88413f8644f2dd8fb772b0c9402</code>)\n"
                    . "• لینک کامل از Tonviewer / Tonscan / Tronscan",
                    $cancelKb,
                    'HTML'
                );

        return true;
    }
}
