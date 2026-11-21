<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminChannelMenu, $adminExecution, $adminPanelsMenu, $adminUsersMenu, $backadmin, $callback_query_id, $dataget, $datagetc, $datagetr, $from_id, $message_id, $pdo, $rxConfirmAlert, $setting_panel, $shopkeyboard, $supportcenter, $text, $textbotlang, $update.
 * Inherited or exported by reference: $keyboardadmin, $randomString, $stmt, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class UserNotesAndReceipts
{
    public static function handle(
        $adminAction,
        $adminChannelMenu,
        $adminExecution,
        $adminPanelsMenu,
        $adminUsersMenu,
        $backadmin,
        $callback_query_id,
        $dataget,
        $datagetc,
        $datagetr,
        $from_id,
        &$keyboardadmin,
        $message_id,
        $pdo,
        &$randomString,
        $rxConfirmAlert,
        $setting_panel,
        $shopkeyboard,
        &$stmt,
        $supportcenter,
        $text,
        $textbotlang,
        $update,
        &$user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "data_limit_reset", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, " 🗒 یادداشت را برای محصول ارسال کنید.این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('endstep', $from_id);
            break;
        case 2:
            $userdata = json_decode($user['Processing_value'], true);
            $randomString = bin2hex(random_bytes(2));
            $varhide_panel = "{}";
            if (!isset($userdata['category']))
                $userdata['category'] = null;
            $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status) VALUES (:name_product,:code_product,:price_product,:Volume_constraint,:Service_time,:Location,:agent,:data_limit_reset,:note,:category,:hide_panel,'0')");
            $stmt->bindParam(':name_product', $userdata['name_product']);
            $stmt->bindParam(':code_product', $randomString);
            $stmt->bindParam(':price_product', $userdata['price_product']);
            $stmt->bindParam(':Volume_constraint', $userdata['Volume_constraint']);
            $stmt->bindParam(':Service_time', $userdata['Service_time']);
            $stmt->bindParam(':Location', $userdata['Location']);
            $stmt->bindParam(':agent', $userdata['agent']);
            $stmt->bindParam(':data_limit_reset', $userdata['data_limit_reset']);
            $stmt->bindParam(':category', $userdata['category'], PDO::PARAM_STR);
            $stmt->bindParam(':note', $text, PDO::PARAM_STR);
            $stmt->bindParam(':hide_panel', $varhide_panel, PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['SaveProduct'], $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 3:
            $list_admin = \Faoxima\Core\Database\Db::select("admin", "*", null, null, "fetchAll");
            $keyboardadmin = ['inline_keyboard' => []];
            foreach ($list_admin as $admin) {
                $adminId = isset($admin['id_admin']) ? trim($admin['id_admin']) : '';
                if ($adminId === '') {
                    continue;
                }
                $keyboardadmin['inline_keyboard'][] = [
                    ['text' => "❌", 'callback_data' => "removeadmin_" . $adminId],
                    ['text' => $adminId, 'callback_data' => "adminlist"],
                ];
            }
            $keyboardadmin['inline_keyboard'][] = [
                ['text' => "👨‍💻 اضافه کردن ادمین", 'callback_data' => "addnewadmin"],
            ];
            $keyboardadmin['inline_keyboard'][] = [
                ['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => "admin_usershub"],
                ['text' => "🏠 منوی مدیریت", 'callback_data' => "adm_hub_main"],
            ];
            $keyboardadmin = json_encode($keyboardadmin);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در بخش زیر می توانید لیست ادمین ها را مشاهده کنید همچنین با زدن دکمه ضربدر می توانید یک ادمین را حذف کنید", $keyboardadmin, 'HTML');
            break;
        case 4:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
            break;
        case 5:
            \Faoxima\Telegram\Navigation\Steps::step('channelhub', $from_id);
            if (isset($user) && is_array($user)) {
                $user['step'] = 'channelhub';
            }
            if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channelhub');
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminChannelMenu, 'HTML');
            break;
        case 6:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminUsersMenu, 'HTML');
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $supportcenter, 'HTML');
            break;
        case 9:
            $order_id = $dataget[1];
            $Payment_report = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $order_id, "select");
            if ($Payment_report == false) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "تراکنش حذف شده است",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            $_receipt_chat_id = !empty($Payment_report['report_chat_id']) ? $Payment_report['report_chat_id'] : ($update['callback_query']['message']['chat']['id'] ?? $from_id);
            $_receipt_msg_id = !empty($Payment_report['report_message_id']) ? (int) $Payment_report['report_message_id'] : (int) $message_id;
            $_receipt_thread_id = !empty($Payment_report['report_thread_id']) ? (int) $Payment_report['report_thread_id'] : (int) ($update['callback_query']['message']['message_thread_id'] ?? 0);
            $Confirm_pay = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "✅ تایید شده", 'callback_data' => "confirmpaid", 'style' => 'success', '_rxkeep' => 1],
                    ],
                    [
                        ['text' => "⚙️ مدیریت کاربر", 'callback_data' => "manageuser_" . $Payment_report['id_user'], 'style' => 'primary', '_rxkeep' => 1],
                    ]
                ]
            ]);
            $sql = "SELECT * FROM Payment_report WHERE id_user = '{$Payment_report['id_user']}' AND payment_Status != 'paid' AND payment_Status != 'Unpaid' AND payment_Status != 'expire' AND payment_Status != 'reject' AND payment_Status != 'cancelled' AND  (id_invoice  LIKE CONCAT('%','getconfigafterpay', '%') OR id_invoice  LIKE CONCAT('%','getextenduser', '%') OR id_invoice  LIKE CONCAT('%','getextravolumeuser', '%') OR id_invoice  LIKE CONCAT('%','getextratimeuser', '%'))";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $countpay = $stmt->rowCount();
            $typepay = explode('|', $Payment_report['id_invoice']);
            if ($countpay > 0 and !in_array($typepay[0], ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ برای تأیید درخواست‌های کاربر، ابتدا رسیدهای خرید یا تمدید اشتراک را بررسی و تأیید کنید. سپس رسید شارژ کیف پول را تأیید کنید. ", null, 'HTML');
                return;
            }
            $format_price_cart = number_format($Payment_report['price']);
            $Balance_id = \Faoxima\Core\Database\Db::select("user", "*", "id", $Payment_report['id_user'], "select");
            if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                $textconfrom = "✅. پرداخت توسط ادمین دیگری تایید شده
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی بعد از تایید : " . \Faoxima\Core\Support\Formatting::rxFormatToman($Balance_id['Balance']) . "
💸 مبلغ پرداختی: $format_price_cart تومان
";
                Editmessagetext($_receipt_chat_id, $_receipt_msg_id, $textconfrom, $Confirm_pay, 'HTML', $_receipt_thread_id > 0 ? $_receipt_thread_id : null);
                return;
            }

            $rxConfirmResult = \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptConfirm($order_id, ['actor_id' => $from_id, 'actor_label' => (string) $from_id]);

            if (!$rxConfirmResult['ok']) {
                $rxConfirmReason = (string) ($rxConfirmResult['reason'] ?? '');
                if (function_exists('rx_log_event')) {
                    rx_log_event('RECEIPT_CONFIRM_CALLBACK_FAILED', 'Admin receipt confirmation did not complete', [
                        'id_order' => $order_id,
                        'actor_id' => $from_id,
                        'reason' => $rxConfirmReason,
                    ]);
                }
                if (in_array($rxConfirmReason, ['already_paid', 'already_final'], true)) {
                    $rxConfirmAlert = (string) ($textbotlang['Admin']['Payment']['reviewedpayment'] ?? 'این رسید قبلاً بررسی شده است.');
                } elseif (in_array($rxConfirmReason, ['processing', 'fulfillment_reconciling', 'race_lost'], true)) {
                    $rxConfirmAlert = '⏳ تأیید این رسید در حال پردازش است؛ لطفاً چند لحظه بعد وضعیت را دوباره بررسی کنید.';
                } elseif ($rxConfirmReason === 'not_waiting') {
                    $rxConfirmAlert = '⚠️ این پرداخت در وضعیت انتظار تأیید رسید نیست؛ وضعیت آن را در گزارش پرداخت بررسی کنید.';
                } elseif ($rxConfirmReason === 'manual_review') {
                    $rxConfirmAlert = '⚠️ این پرداخت نیازمند بررسی دستی است و به‌صورت خودکار تأیید نشد.';
                } elseif ($rxConfirmReason === 'verify_reason_required') {
                    $rxConfirmAlert = '🔐 این رسید نیازمند تأیید با بررسی (پیامک بانکی/ForApp) یا ثبت دلیل توسط ادمین است.';
                } elseif ($rxConfirmReason === 'purchase_receipts_pending') {
                    $rxConfirmAlert = '⚠️ ابتدا رسیدهای خرید یا تمدید اشتراک این کاربر را تأیید کنید و سپس رسید شارژ کیف پول را تأیید کنید.';
                } elseif (in_array($rxConfirmReason, ['not_found', 'pdo_unavailable'], true)) {
                    $rxConfirmAlert = '❌ تراکنش یا اتصال پایگاه داده در دسترس نیست؛ صفحه را به‌روزرسانی کنید و دوباره تلاش کنید.';
                } elseif ($rxConfirmReason === 'fulfillment_failed') {
                    $rxConfirmAlert = '❌ تکمیل پرداخت با خطا مواجه شد؛ وضعیت پرداخت را بررسی کنید یا با پشتیبانی تماس بگیرید.';
                } elseif ($rxConfirmReason === 'db_error') {
                    $rxConfirmAlert = '❌ ذخیره‌سازی تأیید پرداخت با خطا مواجه شد؛ پیش از تلاش دوباره، وضعیت پرداخت را بررسی کنید.';
                } else {
                    $rxConfirmAlert = '❌ تکمیل عملیات با خطا مواجه شد؛ وضعیت پرداخت را بررسی کنید یا با پشتیبانی تماس بگیرید.';
                }
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $rxConfirmAlert,
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                return;
            }
            break;
        case 10:
            $id_order = $datagetr[1];
            $Payment_report = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $id_order, "select");
            if ($Payment_report == false) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "تراکنش حذف شده است",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            $_receipt_chat_id = !empty($Payment_report['report_chat_id']) ? $Payment_report['report_chat_id'] : ($update['callback_query']['message']['chat']['id'] ?? $from_id);
            $_receipt_msg_id = !empty($Payment_report['report_message_id']) ? (int) $Payment_report['report_message_id'] : (int) $message_id;
            $_receipt_thread_id = !empty($Payment_report['report_thread_id']) ? (int) $Payment_report['report_thread_id'] : (int) ($update['callback_query']['message']['message_thread_id'] ?? 0);
            if ($Payment_report['payment_Status'] == "reject" || $Payment_report['payment_Status'] == "paid") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                return;
            }
            $rejectStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject' WHERE id_order = :id_order AND payment_Status = 'waiting'");
            $rejectStmt->execute([':id_order' => $id_order]);
            if ($rejectStmt->rowCount() !== 1) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $Payment_report['id_user'], "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $id_order, "id", $from_id);

            $_reject_reason_kb = json_encode([
                'inline_keyboard' => [
                    [['text' => "❌ لغو", 'callback_data' => "reject_pay_cancel_" . $id_order]],
                ],
            ], JSON_UNESCAPED_UNICODE);
            $_reject_prompt_result = Editmessagetext($_receipt_chat_id, $_receipt_msg_id, $textbotlang['Admin']['Payment']['Reasonrejecting'], $_reject_reason_kb, 'HTML', $_receipt_thread_id > 0 ? $_receipt_thread_id : null);
            $_reject_prompt_msg_id = is_array($_reject_prompt_result) ? (int) ($_reject_prompt_result['result']['message_id'] ?? 0) : 0;
            if ($_reject_prompt_msg_id > 0) {
                $_receipt_msg_id = $_reject_prompt_msg_id;
            }
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $_receipt_chat_id . ':' . $_receipt_msg_id . ':' . $_receipt_thread_id, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('reject-dec', $from_id);
            break;
        case 11:
            $id_order = $datagetc[1];
            $_receipt_chat_id = $update['callback_query']['message']['chat']['id'] ?? $from_id;
            $Payment_report = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $id_order, "select");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "لغو شد",
                'show_alert' => false,
                'cache_time' => 0,
            ));
            if ($Payment_report === false) {
                Editmessagetext($_receipt_chat_id, $message_id, "❌ تراکنش حذف شده است", null);
                return;
            }
            \Faoxima\Core\Database\Db::update("Payment_report", "payment_Status", "waiting", "id_order", $id_order);
            $_reject_cancel_kb = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$id_order}"],
                        ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$id_order}"],
                    ],
                    [
                        ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$id_order}"],
                        ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$Payment_report['id_user']}"],
                    ],
                ],
            ]);
            $_reject_cancel_text = "🔁 رد رسید لغو شد. رسید دوباره در انتظار بررسی است.

🛒 کد پیگیری پرداخت: {$id_order}
💰 مبلغ پرداخت : " . \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price']) . "
👤 ایدی عددی کاربر: {$Payment_report['id_user']}";
            Editmessagetext($_receipt_chat_id, $message_id, $_reject_cancel_text, $_reject_cancel_kb);
            break;
        }

        return true;
    }
}
