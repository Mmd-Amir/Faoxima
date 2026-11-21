<?php

namespace Faoxima\Admin\Telegram\Handlers;

use RuntimeException;
use Throwable;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $connect, $dataget, $from_id, $keyboardadmin, $message_id, $paymentreports, $pdo, $setting, $text, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $invoice, $next_page, $page, $randomString, $result, $row, $rows, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class UserDirectoryAndCredits
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $connect,
        $dataget,
        $from_id,
        &$invoice,
        $keyboardadmin,
        $message_id,
        &$next_page,
        &$page,
        $paymentreports,
        $pdo,
        &$randomString,
        &$result,
        &$row,
        &$rows,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        $username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Core\Database\Db::update("user", "pagenumber", "1", "id", $from_id);
            $page = 1;
            $items_per_page = 10;
            $start_index = ($page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuserbalance', 'previous_pageuserbalance', true);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 2:
            $numpage = \Faoxima\Core\Database\Db::select("user", "*", null, null, "count");
            $page = $user['pagenumber'];
            $items_per_page = 10;
            $sum = $user['pagenumber'] * $items_per_page;
            if ($sum > $numpage) {
                $next_page = 1;
            } else {
                $next_page = $page + 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuserbalance', 'previous_pageuserbalance', true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 3:
            $page = $user['pagenumber'];
            $items_per_page = 10;
            if ($user['pagenumber'] <= 1) {
                $next_page = 1;
            } else {
                $next_page = $page - 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuserbalance', 'previous_pageuserbalance', true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 4:
            \Faoxima\Core\Database\Db::update("user", "pagenumber", "1", "id", $from_id);
            $page = 1;
            $items_per_page = 10;
            $start_index = ($page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT DISTINCT user.* FROM user INNER JOIN invoice ON invoice.id_user = user.id WHERE invoice.Status IN ('active','sendedwarn','send_on_hold') LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuseractivesub', 'previous_pageuseractivesub', true);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 5:
            $numpage = \Faoxima\Core\Database\Db::select("user", "*", null, null, "count");
            $page = $user['pagenumber'];
            $items_per_page = 10;
            $sum = $user['pagenumber'] * $items_per_page;
            if ($sum > $numpage) {
                $next_page = 1;
            } else {
                $next_page = $page + 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT DISTINCT user.* FROM user INNER JOIN invoice ON invoice.id_user = user.id WHERE invoice.Status IN ('active','sendedwarn','send_on_hold') LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuseractivesub', 'previous_pageuseractivesub', true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 6:
            $page = $user['pagenumber'];
            $items_per_page = 10;
            if ($user['pagenumber'] <= 1) {
                $next_page = 1;
            } else {
                $next_page = $page - 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT DISTINCT user.* FROM user INNER JOIN invoice ON invoice.id_user = user.id WHERE invoice.Status IN ('active','sendedwarn','send_on_hold') LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuseractivesub', 'previous_pageuseractivesub', true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 7:
            \Faoxima\Core\Database\Db::update("user", "pagenumber", "1", "id", $from_id);
            $page = 1;
            $items_per_page = 10;
            $start_index = ($page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuserrefral', 'previous_pageuserrefral', true);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 8:
            $numpage = \Faoxima\Core\Database\Db::select("user", "*", null, null, "count");
            $page = $user['pagenumber'];
            $items_per_page = 10;
            $sum = $user['pagenumber'] * $items_per_page;
            if ($sum > $numpage) {
                $next_page = 1;
            } else {
                $next_page = $page + 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuserrefral', 'previous_pageuserrefral', true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 9:
            $page = $user['pagenumber'];
            $items_per_page = 10;
            if ($user['pagenumber'] <= 1) {
                $next_page = 1;
            } else {
                $next_page = $page - 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuserrefral', 'previous_pageuserrefral', true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 10:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $iduser, "id", $from_id);
            telegram('sendmessage', [
                'chat_id' => $from_id,
                'text' => $textbotlang['Admin']['ManageUser']['addbalanceuserdec'],
                'reply_markup' => $backadmin,
                'parse_mode' => "HTML",
                'reply_to_message_id' => $message_id,
            ]);
            \Faoxima\Telegram\Navigation\Steps::step('addbalanceusercurrent', $from_id);
            break;
        case 11:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text) || intval($text) <= 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
                return;
            }
            if ($text > 100000000) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
                return;
            }
            $dateacc = date('Y/m/d H:i:s');
            $randomString = bin2hex(random_bytes(5));
            $payment_Status = "paid";
            $Payment_Method = "add balance by admin";
            $invoice = null;
            $rxAddBalOk = false;
            try {
                $pdo->beginTransaction();
                $rxAddBalClaim = $pdo->prepare("UPDATE user SET step = 'home' WHERE id = :admin AND step = 'addbalanceusercurrent'");
                $rxAddBalClaim->execute([':admin' => $from_id]);
                if ($rxAddBalClaim->rowCount() < 1) {
                    $pdo->rollBack();
                    return;
                }
                if (!\Faoxima\Wallet\Balance\Balances::credit($pdo, $user['Processing_value'], (int) $text)) {
                    throw new RuntimeException('balance_atomic_credit returned false');
                }
                $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice]);
                if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                    \Faoxima\Wallet\Ledger\Ledger::record($pdo, $user['Processing_value'], 'credit', $text, 'admin_credit', 'افزایش موجودی توسط ادمین', $randomString);
                }
                $pdo->commit();
                $rxAddBalOk = true;
            } catch (Throwable $rxAddBalErr) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin_add_balance] ' . $rxAddBalErr->getMessage());
                if (function_exists('rx_log_event')) {
                    rx_log_event('ADMIN_ADD_BALANCE_FAILED', $rxAddBalErr->getMessage(), [
                        'admin_id' => $from_id,
                        'id_user' => $user['Processing_value'],
                        'amount' => $text,
                    ]);
                }
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            if (!$rxAddBalOk) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ افزایش موجودی انجام نشد. دوباره تلاش کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['addbalanced'], $keyboardadmin, 'html');
            $heibalanceuser = number_format($text, 0);
            $textadd = "💎 کاربر عزیز مبلغ $heibalanceuser تومان به موجودی کیف پول تان اضافه گردید.";
            sendmessage($user['Processing_value'], $textadd, null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $Balance_user_after = number_format(\Faoxima\Core\Database\Db::select("user", "*", "id", $user['Processing_value'], "select", ['cache' => false])['Balance']);
            $pricadd = number_format($text);
            if (strlen($setting['Channel_Report']) > 0) {
                $textaddbalance = "📌 یک ادمین موجودی کاربر را افزایش داده است :

🪪 اطلاعات ادمین افزایش دهنده موجودی :
<blockquote>نام کاربری :@$username</blockquote>
<blockquote>آیدی عددی : $from_id</blockquote>
👤 اطلاعات کاربر دریافت کننده موجودی :
<blockquote>آیدی عددی کاربر  : {$user['Processing_value']}</blockquote>
<blockquote>مبلغ موجودی : $pricadd</blockquote>
<blockquote>موجودی کاربر پس از افزایش : $Balance_user_after</blockquote>";
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $paymentreports,
                    'text' => $textaddbalance,
                    'parse_mode' => "HTML"
                ]);
            }
            break;
        }

        return true;
    }
}
