<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $connect, $datagetr, $from_id, $inline_query_id, $keyboardadmin, $message_id, $pdo, $query, $text, $textbotlang, $user.
 * Inherited or exported by reference: $datatime, $destination, $id_user, $next_page, $page, $result, $results, $row, $rows, $stmt, $userdate.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class InlineInvoiceAndResellerTools
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $connect,
        $datagetr,
        &$datatime,
        &$destination,
        $from_id,
        &$id_user,
        $inline_query_id,
        $keyboardadmin,
        $message_id,
        &$next_page,
        &$page,
        $pdo,
        $query,
        &$result,
        &$results,
        &$row,
        &$rows,
        &$stmt,
        $text,
        $textbotlang,
        $user,
        &$userdate
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':username', $query, PDO::PARAM_STR);
            $stmt->bindParam(':Service_time', $query, PDO::PARAM_STR);
            $stmt->bindParam(':Volume', $query, PDO::PARAM_STR);
            $stmt->bindParam(':notes', $query, PDO::PARAM_STR);
            $stmt->execute();
            $invoices = $stmt->fetchAll();
            $results = [];
            foreach ($invoices as $OrderUser) {
                if (isset($OrderUser['time_sell'])) {
                    $datatime = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
                } else {
                    $datatime = $textbotlang['Admin']['ManageUser']['dataorder'];
                }
                if ($OrderUser['name_product'] == "سرویس تست") {
                    $OrderUser['Service_time'] = $OrderUser['Service_time'] . "ساعته";
                } else {
                    $OrderUser['Service_time'] = $OrderUser['Service_time'] . "روزه";
                }
                $OrderUser['Volume'] = \Faoxima\Core\Support\Formatting::formatInvoiceVolume($OrderUser);
                $rxFmtOrderUserPrice = \Faoxima\Core\Support\Formatting::rxFormatToman($OrderUser['price_product']);
                $results[] = [
                    "type" => "article",
                    "id" => uniqid(),
                    'cache_time' => 0,
                    'is_personal' => true,
                    "title" => $OrderUser['username'],
                    "input_message_content" => [
                        "message_text" => "
🛒 شماره سفارش  :  {$OrderUser['id_invoice']}
🛒  وضعیت سفارش در ربات : {$OrderUser['Status']}
🙍‍♂️ شناسه کاربر : {$OrderUser['id_user']}
👤 نام کاربری اشتراک :  {$OrderUser['username']}
📍 موقعیت سرویس :  {$OrderUser['Service_location']}
🛍 نام محصول :  {$OrderUser['name_product']}
💰 قیمت پرداختی سرویس : {$rxFmtOrderUserPrice} تومان
⚜️ حجم سرویس خریداری شده : {$OrderUser['Volume']}
⏳ زمان سرویس خریداری شده : {$OrderUser['Service_time']}
📆 تاریخ خرید : $datatime
"
                    ]
                ];
            }
            answerInlineQuery($inline_query_id, $results);
            break;
        case 2:
            $id_user = $datagetr[1];
            \Faoxima\Core\Database\Db::update("user", "pagenumber", "1", "id", $from_id);
            $page = 1;
            $items_per_page = 10;
            $start_index = ($page - 1) * $items_per_page;
            $result = \Faoxima\Admin\Keyboard\InvoicePagesHelpers::rxAdminInvoicePageResult($connect, $id_user, $start_index, $items_per_page);
            $keyboard_json = \Faoxima\Admin\Keyboard\InvoicePagesHelpers::rxAdminInvoicePageKeyboard($result, $id_user, $textbotlang);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json, 'html');
            break;
        case 3:
            $id_user = $datagetr[1];
            $numpage = \Faoxima\Core\Database\Db::select("invoice", "*", "id_user", $id_user, "count");
            $page = $user['pagenumber'];
            $items_per_page = 10;
            $sum = $user['pagenumber'] * $items_per_page;
            if ($sum > $numpage) {
                $next_page = 1;
            } else {
                $next_page = $page + 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = \Faoxima\Admin\Keyboard\InvoicePagesHelpers::rxAdminInvoicePageResult($connect, $id_user, $start_index, $items_per_page);
            $keyboard_json = \Faoxima\Admin\Keyboard\InvoicePagesHelpers::rxAdminInvoicePageKeyboard($result, $id_user, $textbotlang);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 4:
            $id_user = $datagetr[1];
            $numpage = \Faoxima\Core\Database\Db::select("invoice", "*", "id_user", $id_user, "count");
            $page = $user['pagenumber'];
            $items_per_page = 10;
            if ($user['pagenumber'] <= 1) {
                $next_page = 1;
            } else {
                $next_page = $page - 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = \Faoxima\Admin\Keyboard\InvoicePagesHelpers::rxAdminInvoicePageResult($connect, $id_user, $start_index, $items_per_page);
            $keyboard_json = \Faoxima\Admin\Keyboard\InvoicePagesHelpers::rxAdminInvoicePageKeyboard($result, $id_user, $textbotlang);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 5:
            \Faoxima\Core\Database\Db::update("user", "pagenumber", "1", "id", $from_id);
            $page = 1;
            $items_per_page = 10;
            $start_index = ($page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE cardpayment = \'1\' LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageusercart', 'previous_pageusercart', true);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 6:
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
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE cardpayment = \'1\' LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageusercart', 'previous_pageusercart', true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 7:
            $page = $user['pagenumber'];
            $items_per_page = 10;
            if ($user['pagenumber'] <= 1) {
                $next_page = 1;
            } else {
                $next_page = $page - 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE cardpayment = \'1\' LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageusercart', 'previous_pageusercart', true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "این بخش در حال توسعه می‌باشد!", $keyboardadmin, 'HTML');
            return;
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "این بخش در حال توسعه می‌باشد!", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            return;
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "این بخش در حال توسعه می‌باشد!", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            return;
            break;
        case 11:
            $id_user = $datagetr[1];
            $contentbto = \Faoxima\Core\Database\Db::select("botsaz", "*", "id_user", $id_user, "select");
            $destination = getcwd();
            $dirsource = "$destination/vpnbot/$id_user{$contentbto['username']}";
            if (is_dir($dirsource) && !\Faoxima\Core\Support\Filesystem::deleteDirectory($dirsource)) {
                error_log('Failed to remove bot directory: ' . $dirsource);
            }
            if (!empty($contentbto['bot_token'])) {
                file_get_contents("https://api.telegram.org/bot{$contentbto['bot_token']}/deletewebhook");
            }
            $stmt = $pdo->prepare("DELETE FROM botsaz WHERE id_user = :id_user");
            $stmt->bindParam(':id_user', $id_user, PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ربات فروش نماینده با موفقیت حذف گردید.", $keyboardadmin, 'HTML');
            break;
        case 12:
            $id_user = $datagetr[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "id_user", $id_user);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 کمترین قیمتی که میخواهید نماینده بابت هر گیگ حجم بپردازد را تعیین کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricevolumesrc", $from_id);
            break;
        case 13:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $userdate = json_decode($user['Processing_value'], true);
            $botinfo = json_decode(\Faoxima\Core\Database\Db::select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
            $botinfo['minpricevolume'] = $text;
            \Faoxima\Core\Database\Db::update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ قیمت با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
            break;
        case 14:
            $id_user = $datagetr[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "id_user", $id_user);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 کمترین قیمتی که میخواهید نماینده بابت هر روز زمان بپردازد را تعیین کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricetimesrc", $from_id);
            break;
        case 15:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $userdate = json_decode($user['Processing_value'], true);
            $botinfo = json_decode(\Faoxima\Core\Database\Db::select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
            $botinfo['minpricetime'] = $text;
            \Faoxima\Core\Database\Db::update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ قیمت با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
            break;
        }

        return true;
    }
}
