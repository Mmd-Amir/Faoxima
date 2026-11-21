<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $_rx_acc_styles, $callback_query_id, $datatextbot, $from_id, $message_id, $pdo, $telegramRoute.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class MiniAppPending
{
    public static function handle(
        $_rx_acc_styles,
        $callback_query_id,
        $datatextbot,
        $from_id,
        $message_id,
        $pdo,
        $telegramRoute
    ) {
        switch ($telegramRoute) {
        case 'miniapp_pending':
            $stmtMp = $pdo->prepare(
                "SELECT id_order, price FROM Payment_report
          WHERE id_user = :uid
            AND payment_Status = 'waiting'
            AND Payment_Method = 'cart to cart'
            AND source = 'miniapp'
            AND dec_not_confirmed = 'receipt-submitted'
          ORDER BY id DESC LIMIT 5"
            );
            $stmtMp->execute([':uid' => $from_id]);
            $mpRows = $stmtMp->fetchAll();
            if (empty($mpRows)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => '✅ هیچ رسید تایید نشده‌ای از مینی‌اپ ندارید.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }
            $mpText = "⏳ <b>رسیدهای در انتظار تأیید از مینی‌اپ</b>\n\n";
            foreach ($mpRows as $mp) {
                $mpText .= "🛒 کد پیگیری: <code>{$mp['id_order']}</code>\n";
                $mpText .= "💰 مبلغ: " . number_format((int)$mp['price']) . " تومان\n";
                $mpText .= "📌 وضعیت: در انتظار بررسی ادمین\n\n";
            }
            $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
            $mpKb = json_encode(['inline_keyboard' => [[\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => '🔙 بازگشت', 'callback_data' => 'account', '_rxcolor' => 'navigation:account_back', '_rxsk' => 'backinfo', '_rxkeep' => true], 'backinfo', $_rx_acc_s)]]], JSON_UNESCAPED_UNICODE);
            Editmessagetext($from_id, $message_id, $mpText, $mpKb);
            break;
        case 'faq':
            sendmessage($from_id, $datatextbot['text_dec_fq'], null, 'HTML');
            break;
        }

        return true;
    }
}
