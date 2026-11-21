<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminAction, $adminExecution, $backadmin, $callback_query_id, $dataget, $from_id, $keyboardadmin, $otherreport, $pdo, $setting, $text, $textbotlang, $user.
 * Inherited or exported by reference: $id_invoice, $invoice, $marzban_list_get, $nameloc, $pricelast, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AgentRequestsAndServiceAdministration
{
    public static function handle(
        $ManagePanel,
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $dataget,
        $from_id,
        &$id_invoice,
        &$invoice,
        $keyboardadmin,
        &$marzban_list_get,
        &$nameloc,
        $otherreport,
        $pdo,
        &$pricelast,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['accecptreqests'], $keyboardadmin, 'HTML');
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $user['Processing_value'], "select");
            \Faoxima\Core\Database\Db::update("cancel_service", "status", "reject", "username", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("cancel_service", "description", $text, "username", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("cancel_service", "resolved_at", time(), "username", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            sendmessage($nameloc['id_user'], "❌ کاربری گرامی درخواست حذف شما با نام کاربری  {$user['Processing_value']} موافقت نگردید.

        دلیل عدم تایید : $text", null, 'HTML');
            break;
        case 2:
            $id_invoice = $dataget[1];
            $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            $requestcheck = \Faoxima\Core\Database\Db::select("cancel_service", "*", "username", $invoice['username'], "select");
            if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $requestcheck['username'], "select");
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $requestcheck['username']);
            $stmt = $pdo->prepare("SELECT  SUM(price) FROM service_other WHERE username = :username AND type != 'change_location' AND type != 'extend_user' LIMIT 1");
            $stmt->bindParam(':username', $nameloc['username']);
            $stmt->execute();
            $sumproduct = $stmt->fetch(PDO::FETCH_ASSOC);
            if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
                if (empty($nameloc['invalidated_at'])) {
                    \Faoxima\Core\Database\Db::update("invoice", "invalidated_at", time(), "id_invoice", $nameloc['id_invoice']);
                }
                \Faoxima\Core\Database\Db::update("invoice", "Status", "disabledn", "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($DataUserOut['status'] == "Unsuccessful") {
                if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "Panel Not Found") {
                    if (empty($nameloc['invalidated_at'])) {
                        \Faoxima\Core\Database\Db::update("invoice", "invalidated_at", time(), "id_invoice", $nameloc['id_invoice']);
                    }
                    \Faoxima\Core\Database\Db::update("invoice", "Status", "Unsuccessful", "id_invoice", $nameloc['id_invoice']);
                }
            }
            if ($DataUserOut['data_limit'] == null && $DataUserOut['expire'] == null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ به دلیل نامحدود بودن حجم و زمان امکان حذف سرویس وجود ندارد. ", null, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($DataUserOut['status'] == "on_hold") {
                $pricelast = $invoice['price_product'];
            } elseif ($DataUserOut['data_limit'] == null) {
                $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
                if ($serviceTime > 0) {
                    $pricetime = \Faoxima\Core\Support\Formatting::safe_divide($nameloc['price_product'], $serviceTime, 0) + intval($sumproduct['SUM(price)']);
                    $pricelast = (($DataUserOut['expire'] - time()) / 86400) * $pricetime;
                } else {
                    $pricelast = 0;
                }
            } elseif ($DataUserOut['expire'] == null) {
                $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
                if ($dataLimit > 0) {
                    $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
                    $volumeDivisor = $dataLimit / pow(1024, 3);
                    $volumeleft = $volumeDivisor > 0 ? \Faoxima\Core\Support\Formatting::safe_divide($volumelefts, $volumeDivisor, 0) : 0;
                    $pricelast = round($volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
                } else {
                    $pricelast = 0;
                }
            } else {
                $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
                $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
                $volumeDivisor = $dataLimit / pow(1024, 3);
                if ($serviceTime > 0 && $volumeDivisor > 0) {
                    $timeleft = \Faoxima\Core\Support\Formatting::safe_divide(round(($DataUserOut['expire'] - time()) / 86400, 0), $serviceTime, 0);
                    $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
                    $volumeleft = \Faoxima\Core\Support\Formatting::safe_divide($volumelefts, $volumeDivisor, 0);
                    $pricelast = round($timeleft * $volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
                } else {
                    $pricelast = 0;
                }
            }
            $pricelast = max(0, intval($pricelast));
            $rxRefundCap = intval($nameloc['price_product'] ?? 0) + intval($sumproduct['SUM(price)'] ?? 0);
            if ($rxRefundCap > 0 && $pricelast > $rxRefundCap) {
                $pricelast = $rxRefundCap;
            }
            $rxRefundOk = false;
            try {
                $pdo->beginTransaction();
                $rxRefundClaim = $pdo->prepare("UPDATE cancel_service SET status = 'accept' WHERE username = :u AND (status IS NULL OR status NOT IN ('accept', 'reject'))");
                $rxRefundClaim->execute([':u' => $requestcheck['username']]);
                if ($rxRefundClaim->rowCount() < 1) {
                    $pdo->rollBack();
                    telegram('answerCallbackQuery', array(
                        'callback_query_id' => $callback_query_id,
                        'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                        'show_alert' => true,
                        'cache_time' => 5,
                    ));
                    return;
                }
                if ($pricelast > 0) {
                    if (!\Faoxima\Wallet\Balance\Balances::credit($pdo, $nameloc['id_user'], $pricelast)) {
                        throw new RuntimeException('balance_atomic_credit returned false');
                    }
                    if (!\Faoxima\Wallet\Ledger\Ledger::record($pdo, $nameloc['id_user'], 'credit', $pricelast, 'refund', 'بازگشت وجه حذف سرویس (تایید ادمین)', null, 'invoice', (string) ($nameloc['id_invoice'] ?? ''))) {
                        throw new RuntimeException('wallet_ledger_record failed');
                    }
                }
                $pdo->commit();
                $rxRefundOk = true;
            } catch (Throwable $rxRefundErr) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[remoceserviceadmin] ' . $rxRefundErr->getMessage());
                if (function_exists('rx_log_event')) {
                    rx_log_event('ADMIN_REFUND_FAILED', $rxRefundErr->getMessage(), ['admin_id' => $from_id, 'id_invoice' => $nameloc['id_invoice'] ?? null, 'id_user' => $nameloc['id_user'] ?? null, 'amount' => $pricelast]);
                }
            }
            if (!$rxRefundOk) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ بازگشت وجه انجام نشد و سرویس حذف نگردید. دوباره تلاش کنید.", null, 'HTML');
                return;
            }
            if ($pricelast > 0) {
                sendmessage($nameloc['id_user'], "💰کاربر گرامی مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($pricelast) . " تومان به موجودی شما اضافه گردید.", null, 'HTML');
            }
            $ManagePanel->RemoveUser($nameloc['Service_location'], $requestcheck['username']);
            \Faoxima\Core\Database\Db::update("cancel_service", "resolved_at", time(), "username", $requestcheck['username']);
            if (is_callable([\Faoxima\Services\Fulfillment\ServiceReversal::class, 'refundHardDeleteService'])) {
                \Faoxima\Services\Fulfillment\ServiceReversal::refundHardDeleteService($pdo, $nameloc['Service_location'] ?? '', $requestcheck['username'] ?? '', $nameloc['id_invoice'] ?? '');
            }
            try {
                $delInvRs = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
                $delInvRs->bindValue(':iid', $nameloc['id_invoice'] ?? '', PDO::PARAM_STR);
                $delInvRs->execute();
            } catch (Throwable $e) {
                error_log('remoceserviceadmin invoice delete failed: ' . $e->getMessage());
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($pricelast) . " تومان به موجودی کاربر اضافه گردید.", null, 'HTML');
            sendmessage($nameloc['id_user'], "✅ کاربری گرامی درخواست حذف شما با نام کاربری  {$nameloc['username']} موافقت گردید.", null, 'HTML');
            $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد

اطلاعات کاربر تایید کننده  :

<blockquote>🪪 آیدی عددی : <code>$from_id</code></blockquote>
<blockquote>💰 مبلغ بازگشتی : " . \Faoxima\Core\Support\Formatting::rxFormatToman($pricelast) . " تومان</blockquote>
<blockquote>👤 نام کاربری : {$requestcheck['username']}</blockquote>
        <blockquote>آیدی عددی درخواست کننده کنسل کردن : {$nameloc['id_user']}</blockquote>";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
            break;
        case 3:
            $id_invoice = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $id_invoice, "id", $from_id);
            $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            $requestcheck = \Faoxima\Core\Database\Db::select("cancel_service", "*", "username", $invoice['username'], "select");
            if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
            $ManagePanel->RemoveUser($invoice['Service_location'], $requestcheck['username']);
            \Faoxima\Core\Database\Db::update("cancel_service", "status", "accept", "username", $requestcheck['username']);
            \Faoxima\Core\Database\Db::update("cancel_service", "resolved_at", time(), "username", $requestcheck['username']);
            \Faoxima\Core\Database\Db::update("invoice", "status", "removedbyadmin", "username", $requestcheck['username']);
            sendmessage($invoice['id_user'], "✅ کاربری گرامی درخواست حذف شما با نام کاربری  {$invoice['username']} موافقت گردید.", null, 'HTML');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مبلغ  برای بازگشت وجه را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricebackremove", $from_id);
            break;
        case 4:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
            if (!is_array($invoice) || empty($invoice['id_user'])) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ فاکتور پیدا نشد.", $keyboardadmin, 'HTML');
                return;
            }
            $rxManualRefundAmount = intval($text);
            $rxManualRefundOk = false;
            try {
                $pdo->beginTransaction();
                $rxManualRefundStep = $pdo->prepare("UPDATE user SET step = 'home' WHERE id = :admin AND step = 'getpricebackremove'");
                $rxManualRefundStep->execute([':admin' => $from_id]);
                if ($rxManualRefundStep->rowCount() < 1) {
                    $pdo->rollBack();
                    return;
                }
                if ($rxManualRefundAmount > 0) {
                    if (!\Faoxima\Wallet\Balance\Balances::credit($pdo, $invoice['id_user'], $rxManualRefundAmount)) {
                        throw new RuntimeException('balance_atomic_credit returned false');
                    }
                    if (!\Faoxima\Wallet\Ledger\Ledger::record($pdo, $invoice['id_user'], 'credit', $rxManualRefundAmount, 'refund', 'بازگشت وجه حذف سرویس (مبلغ دستی ادمین)', null, 'invoice', (string) $invoice['id_invoice'])) {
                        throw new RuntimeException('wallet_ledger_record failed');
                    }
                }
                $pdo->commit();
                $rxManualRefundOk = true;
            } catch (Throwable $rxManualRefundErr) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[getpricebackremove] ' . $rxManualRefundErr->getMessage());
                if (function_exists('rx_log_event')) {
                    rx_log_event('ADMIN_REFUND_FAILED', $rxManualRefundErr->getMessage(), ['admin_id' => $from_id, 'id_invoice' => $invoice['id_invoice'], 'id_user' => $invoice['id_user'], 'amount' => $rxManualRefundAmount]);
                }
            }
            if (!$rxManualRefundOk) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ بازگشت وجه انجام نشد. دوباره مبلغ را ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            if (is_callable([\Faoxima\Services\Fulfillment\ServiceReversal::class, 'refundHardDeleteService'])) {
                \Faoxima\Services\Fulfillment\ServiceReversal::refundHardDeleteService($pdo, $invoice['Service_location'] ?? '', $invoice['username'] ?? '', $invoice['id_invoice'] ?? '');
            }
            try {
                $delInvGpb = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
                $delInvGpb->bindValue(':iid', $invoice['id_invoice'] ?? '', PDO::PARAM_STR);
                $delInvGpb->execute();
            } catch (Throwable $e) {
                error_log('getpricebackremove invoice delete failed: ' . $e->getMessage());
            }
            sendmessage($invoice['id_user'], "💰کاربر گرامی مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($text) . " تومان به موجودی شما اضافه گردید.", null, 'HTML');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت به حساب کاربر اضافه گردید.", $keyboardadmin, 'HTML');
            $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد

اطلاعات کاربر تایید کننده  :

<blockquote>🪪 آیدی عددی : <code>$from_id</code></blockquote>
<blockquote>💰 مبلغ بازگشتی : " . \Faoxima\Core\Support\Formatting::rxFormatToman($text) . " تومان</blockquote>
<blockquote>👤 نام کاربری : {$invoice['username']}</blockquote>
<blockquote>آیدی عددی درخواست کننده کنسل کردن : {$invoice['id_user']}</blockquote>";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
            break;
        }

        return true;
    }
}
