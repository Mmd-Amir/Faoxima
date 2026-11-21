<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $callback_query_id, $from_id, $message_id, $rxPgwMatch.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CancelGateway
{
    public static function handle(
        $callback_query_id,
        $from_id,
        $message_id,
        $rxPgwMatch
    ) {
        $rxPgwGatewayKey = $rxPgwMatch[1];
                $rxPgwOrderId = $rxPgwMatch[2];
                $rxPgwMethodMap = \Faoxima\Payments\Settlement\PendingInvoices::rx_gateway_payment_method_map();
                $rxPgwMethod = $rxPgwMethodMap[$rxPgwGatewayKey] ?? $rxPgwGatewayKey;

                $rxPgwCancelled = \Faoxima\Payments\Settlement\PendingInvoices::cancelPendingGatewayInvoice($from_id, $rxPgwMethod, $rxPgwOrderId);
                if (!$rxPgwCancelled) {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => '❌ این فاکتور دیگر قابل لغو نیست.',
                        'show_alert' => true,
                        'cache_time' => 0,
                    ]);
                    return;
                }

                $rxPgwLabel = \Faoxima\Payments\Settlement\PendingInvoices::rx_gateway_display_name($rxPgwGatewayKey);
                $rxPgwText = "✅ فاکتور قبلی لغو شد.\n\nبرای دریافت فاکتور جدید، دکمه زیر را بزنید.";
                $rxPgwKb = json_encode([
                    'inline_keyboard' => [
                        [rx_payment_button($rxPgwGatewayKey, 'retry', ['text' => "🔄 دریافت فاکتور جدید {$rxPgwLabel}", 'callback_data' => $rxPgwGatewayKey])],
                        [rx_payment_button($rxPgwGatewayKey, 'back_main', ['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'])],
                    ],
                ], JSON_UNESCAPED_UNICODE);
                Editmessagetext($from_id, $message_id, $rxPgwText, $rxPgwKb, 'HTML');

        return true;
    }
}
