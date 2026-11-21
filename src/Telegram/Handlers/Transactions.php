<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $from_id, $message_id, $pdo, $telegramRoute, $textbotlang, $txRangeMatch, $txText.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Transactions
{
    public static function handle(
        $from_id,
        $message_id,
        $pdo,
        $telegramRoute,
        $textbotlang,
        $txRangeMatch,
        $txText
    ) {
        switch ($telegramRoute) {
        case 'transactions':
            $_rx_tx_s = (isset($GLOBALS['_rx_tx_styles']) && is_array($GLOBALS['_rx_tx_styles'])) ? $GLOBALS['_rx_tx_styles'] : [];
            $txRows = [
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => '🕐 ۲۴ ساعت گذشته', 'callback_data' => 'tx_24h'], 'tx_24h', $_rx_tx_s),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => '📅 ۳ روز گذشته', 'callback_data' => 'tx_3d'], 'tx_3d', $_rx_tx_s),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => '🗓 ۷ روز گذشته', 'callback_data' => 'tx_7d'], 'tx_7d', $_rx_tx_s),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => '📆 ۳۰ روز گذشته', 'callback_data' => 'tx_30d'], 'tx_30d', $_rx_tx_s),
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'account', '_rxcolor' => 'navigation:account_back', '_rxsk' => 'backinfo', '_rxkeep' => true], 'tx_back', $_rx_tx_s),
                ],
            ];
            $txKeyboard = json_encode(['inline_keyboard' => $txRows], JSON_UNESCAPED_UNICODE);
            $txPromptText = "📑 تراکنش‌های من\n\nبازه‌ی زمانی مورد نظر را برای مشاهده‌ی تراکنش‌های کیف پول خود انتخاب کنید:";
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $txPromptText, $txKeyboard, 'HTML');
            } else {
                sendmessage($from_id, $txPromptText, $txKeyboard, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            return;
        case 'transaction_range':
            $txRangeSeconds = [
                '24h' => 24 * 60 * 60,
                '3d'  => 3 * 24 * 60 * 60,
                '7d'  => 7 * 24 * 60 * 60,
                '30d' => 30 * 24 * 60 * 60,
            ][$txRangeMatch[1]];
            $txRangeLabel = [
                '24h' => '۲۴ ساعت گذشته',
                '3d'  => '۳ روز گذشته',
                '7d'  => '۷ روز گذشته',
                '30d' => '۳۰ روز گذشته',
            ][$txRangeMatch[1]];
            $txCategoryLabels = [
                'topup_card'           => 'شارژ کارت به کارت',
                'topup_crypto'         => 'شارژ ارز دیجیتال',
                'topup_gateway'        => 'شارژ درگاه پرداخت',
                'admin_credit'         => 'افزایش موجودی توسط ادمین',
                'admin_debit'          => 'کاهش موجودی توسط ادمین',
                'purchase'             => 'خرید سرویس',
                'refund'               => 'بازگشت وجه',
                'affiliate_commission' => 'پورسانت زیرمجموعه',
                'gift_code'            => 'کد هدیه',
                'lottery'              => 'جایزه قرعه‌کشی',
                'cashback'             => 'هدیه بازگشت وجه',
                'service_action'       => 'عملیات سرویس',
                'transfer_out'         => 'انتقال موجودی به کاربر دیگر',
                'transfer_in'          => 'انتقال موجودی از کاربر دیگر',
                'referral_gift'        => 'هدیه عضویت زیرمجموعه',
            ];
            $txRangeKey = $txRangeMatch[1];
            $txLimit = 5;
            $txPage = isset($txRangeMatch[2]) && $txRangeMatch[2] !== '' ? max(1, (int)$txRangeMatch[2]) : 1;
            $txOffset = ($txPage - 1) * $txLimit;
            $txCutoff = time() - $txRangeSeconds;

            $txCountStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM wallet_ledger WHERE id_user = :u AND created_at >= FROM_UNIXTIME(:cutoff)"
            );
            $txCountStmt->execute([':u' => (string)$from_id, ':cutoff' => $txCutoff]);
            $txTotal = (int)$txCountStmt->fetchColumn();
            $txTotalPages = $txTotal > 0 ? (int)ceil($txTotal / $txLimit) : 1;
            if ($txPage > $txTotalPages) {
                $txPage = $txTotalPages;
                $txOffset = ($txPage - 1) * $txLimit;
            }

            $txStmt = $pdo->prepare(
                "SELECT direction, amount, category, description, created_at
           FROM wallet_ledger
          WHERE id_user = :u AND created_at >= FROM_UNIXTIME(:cutoff)
          ORDER BY id DESC
          LIMIT :limit OFFSET :offset"
            );
            $txStmt->bindValue(':u', (string)$from_id);
            $txStmt->bindValue(':cutoff', $txCutoff);
            $txStmt->bindValue(':limit', $txLimit, PDO::PARAM_INT);
            $txStmt->bindValue(':offset', $txOffset, PDO::PARAM_INT);
            $txStmt->execute();
            $txRowsData = $txStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($txRowsData)) {
                $txText = "📑 تراکنش‌های من ({$txRangeLabel})\n\nدر این بازه‌ی زمانی، تراکنشی یافت نشد.";
            } else {
                $txLines = [];
                foreach ($txRowsData as $txRow) {
                    $txSign = $txRow['direction'] === 'credit' ? '➕' : '➖';
                    $txLabel = $txCategoryLabels[$txRow['category']] ?? $txRow['category'];
                    $txAmountFmt = number_format((int)$txRow['amount']);
                    $txDateFmt = jdate('Y/m/d H:i', strtotime($txRow['created_at']));
                    $txLines[] = "{$txSign} {$txAmountFmt} تومان — {$txLabel}\n🕐 {$txDateFmt}";
                }
                $txPageInfo = $txTotalPages > 1 ? "\n\n📄 صفحه {$txPage} از {$txTotalPages}" : '';
                $txText = "📑 تراکنش‌های من ({$txRangeLabel})\n\n" . implode("\n\n", $txLines) . $txPageInfo;
            }

            $_rx_tx_s = (isset($GLOBALS['_rx_tx_styles']) && is_array($GLOBALS['_rx_tx_styles'])) ? $GLOBALS['_rx_tx_styles'] : [];
            $txKbRows = [];
            if ($txTotalPages > 1) {
                $txNavRow = [];
                if ($txPage > 1) {
                    $txNavRow[] = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => '◀️ قبلی', 'callback_data' => "tx_{$txRangeKey}_" . ($txPage - 1)], 'tx_prev', $_rx_tx_s);
                }
                if ($txPage < $txTotalPages) {
                    $txNavRow[] = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => 'بعدی ▶️', 'callback_data' => "tx_{$txRangeKey}_" . ($txPage + 1)], 'tx_next', $_rx_tx_s);
                }
                if (!empty($txNavRow)) {
                    $txKbRows[] = $txNavRow;
                }
            }
            $txKbRows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'MyTransactions'], 'tx_back', $_rx_tx_s)];
            $txBackKeyboard = json_encode(['inline_keyboard' => $txKbRows], JSON_UNESCAPED_UNICODE);
            Editmessagetext($from_id, $message_id, $txText, $txBackKeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            return;
        }

        return true;
    }
}
