<?php

namespace Faoxima\Admin\Telegram\Handlers;

use DateTime;
use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $from_id, $keyboard_stat, $keyboardadmin, $message_id, $pdo, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReportsAndTicketDepartments
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $from_id,
        $keyboard_stat,
        $keyboardadmin,
        $message_id,
        $pdo,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            deletemessage($from_id, $message_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 2:
            $desired_date_time_start = time() - 3600;
            $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " sales WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate();
            $stmt = $pdo->prepare($sql);
            $time_current = time();
            $stmt->bindParam(':requestedDate', $desired_date_time_start);
            $stmt->bindParam(':requestedDateend', $time_current);
            $stmt->execute();
            $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_order = $statorder['count'];
            $sum_order = number_format($statorder['sum'], 0);
            $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':requestedDate', $desired_date_time_start);
            $stmt->bindParam(':requestedDateend', $time_current);
            $stmt->execute();
            $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extend_user' AND status = 'paid'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_extend = $extend_stat['count'];
            $sum_extend = number_format($extend_stat['sum'], 0);
            $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_user'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_extra_volume = $extra_volume_stat['count'];
            $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
            $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_time_user'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_extra_time = $extra_time_stat['count'];
            $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
            $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'change_location'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_change_location = $extra_time_stat['count'];
            $sum_change_location = number_format($extra_time_stat['sum'], 0);
            $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
            $stmt->bindParam(':requestedDate', $desired_date_time_start);
            $stmt->bindParam(':requestedDateend', $time_current);
            $stmt->execute();
            $countextendday = $stmt->rowCount();
            $statisticsall = "
🕐 <b>آمار ۱ ساعت گذشته</b>

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countextendday نفر
";
            Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
            break;
        case 3:
            $start_time = date('Y/m/d', strtotime("-1 days")) . " 00:00:00";
            $end_time = date('Y/m/d', strtotime("-1 days")) . " 23:59:59";
            $rxPeriodStats = \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminPeriodStatistics($pdo, $start_time, $end_time);
            $statisticsall = "
🕐 <b>آمار روز گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : {$rxPeriodStats['count_order']} عدد
💸 جمع مبلغ سفارشات  : {$rxPeriodStats['sum_order']} تومان

🧲 تعداد تمدید  : {$rxPeriodStats['count_extend']} عدد
💰 جمع مبلغ تمدید: {$rxPeriodStats['sum_extend']} تومان

📦 حجم‌های اضافه  :{$rxPeriodStats['count_extra_volume']} عدد
💰 مبلغ حجم‌های اضافه : {$rxPeriodStats['sum_extra_volume']} تومان

⏱️ زمان‌های اضافه  : {$rxPeriodStats['count_extra_time']} عدد
💰 مبلغ زمان‌های اضافه  : {$rxPeriodStats['sum_extrat_time']} تومان

📍 تغییر لوکیشن  : {$rxPeriodStats['count_change_location']} عدد
💰 مبلغ تغییر لوکیشن : {$rxPeriodStats['sum_change_location']} تومان

🔑 اکانت‌های تست  : {$rxPeriodStats['count_test']} عدد
👤 تعداد کاربران  : {$rxPeriodStats['countuser_new']} نفر
";
            Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
            break;
        case 4:
            $start_time = date('Y/m/d') . " 00:00:00";
            $end_time = date('Y/m/d H:i:s');
            $rxPeriodStats = \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminPeriodStatistics($pdo, $start_time, $end_time);
            $statisticsall = "
🕐 <b>آمار روز فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : {$rxPeriodStats['count_order']} عدد
💸 جمع مبلغ سفارشات  : {$rxPeriodStats['sum_order']} تومان

🧲 تعداد تمدید  : {$rxPeriodStats['count_extend']} عدد
💰 جمع مبلغ تمدید: {$rxPeriodStats['sum_extend']} تومان

📦 حجم‌های اضافه  :{$rxPeriodStats['count_extra_volume']} عدد
💰 مبلغ حجم‌های اضافه : {$rxPeriodStats['sum_extra_volume']} تومان

⏱️ زمان‌های اضافه  : {$rxPeriodStats['count_extra_time']} عدد
💰 مبلغ زمان‌های اضافه  : {$rxPeriodStats['sum_extrat_time']} تومان

📍 تغییر لوکیشن  : {$rxPeriodStats['count_change_location']} عدد
💰 مبلغ تغییر لوکیشن : {$rxPeriodStats['sum_change_location']} تومان

🔑 اکانت‌های تست  : {$rxPeriodStats['count_test']} عدد
👤 تعداد کاربران  : {$rxPeriodStats['countuser_new']} نفر
";
            Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
            break;
        case 5:
            $firstDayLastMonth = new DateTime('first day of last month');
            $lastDayLastMonth = new DateTime('last day of last month');
            $start_time = $firstDayLastMonth->format('Y/m/d');
            $end_time = $lastDayLastMonth->format('Y/m/d');
            $rxPeriodStats = \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminPeriodStatistics($pdo, $start_time, $end_time);
            $statisticsall = "
🕐 <b>آمار ماه گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : {$rxPeriodStats['count_order']} عدد
💸 جمع مبلغ سفارشات  : {$rxPeriodStats['sum_order']} تومان

🧲 تعداد تمدید  : {$rxPeriodStats['count_extend']} عدد
💰 جمع مبلغ تمدید: {$rxPeriodStats['sum_extend']} تومان

📦 حجم‌های اضافه  :{$rxPeriodStats['count_extra_volume']} عدد
💰 مبلغ حجم‌های اضافه : {$rxPeriodStats['sum_extra_volume']} تومان

⏱️ زمان‌های اضافه  : {$rxPeriodStats['count_extra_time']} عدد
💰 مبلغ زمان‌های اضافه  : {$rxPeriodStats['sum_extrat_time']} تومان

📍 تغییر لوکیشن  : {$rxPeriodStats['count_change_location']} عدد
💰 مبلغ تغییر لوکیشن : {$rxPeriodStats['sum_change_location']} تومان

🔑 اکانت‌های تست  : {$rxPeriodStats['count_test']} عدد
👤 تعداد کاربران  : {$rxPeriodStats['countuser_new']} نفر
";
            Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
            break;
        case 6:
            $firstDayLastMonth = new DateTime('first day of this month');
            $lastDayLastMonth = new DateTime('last day of this month');
            $start_time = $firstDayLastMonth->format('Y/m/d');
            $end_time = $lastDayLastMonth->format('Y/m/d');
            $rxPeriodStats = \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminPeriodStatistics($pdo, $start_time, $end_time);
            $statisticsall = "
🕐 <b>آمار ماه فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : {$rxPeriodStats['count_order']} عدد
💸 جمع مبلغ سفارشات  : {$rxPeriodStats['sum_order']} تومان

🧲 تعداد تمدید  : {$rxPeriodStats['count_extend']} عدد
💰 جمع مبلغ تمدید: {$rxPeriodStats['sum_extend']} تومان

📦 حجم‌های اضافه  :{$rxPeriodStats['count_extra_volume']} عدد
💰 مبلغ حجم‌های اضافه : {$rxPeriodStats['sum_extra_volume']} تومان

⏱️ زمان‌های اضافه  : {$rxPeriodStats['count_extra_time']} عدد
💰 مبلغ زمان‌های اضافه  : {$rxPeriodStats['sum_extrat_time']} تومان

📍 تغییر لوکیشن  : {$rxPeriodStats['count_change_location']} عدد
💰 مبلغ تغییر لوکیشن : {$rxPeriodStats['sum_change_location']} تومان

🔑 اکانت‌های تست  : {$rxPeriodStats['count_test']} عدد
👤 تعداد کاربران  : {$rxPeriodStats['countuser_new']} نفر
";
            Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, sprintf($textbotlang['Admin']['getstats'], date('Y/m/d')), $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("get_time_start", $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!\Faoxima\Core\Support\Formatting::isValidDate($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "start_time", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "تاریخ پایان را ارسال کنید بطور مثال :  \n<code>2025/09/08</code>", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("get_time_end", $from_id);
            break;
        case 9:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!\Faoxima\Core\Support\Formatting::isValidDate($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $start_time = $userdata['start_time'] . "00:00:00";
            $end_time = $text . "23:59:00";
            $start_time_timestamp = strtotime($start_time);
            $end_time_timestamp = strtotime($end_time);
            $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " sales WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate();
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':requestedDate', $start_time_timestamp);
            $stmt->bindParam(':requestedDateend', $end_time_timestamp);
            $stmt->execute();
            $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_order = $statorder['count'];
            $sum_order = number_format($statorder['sum'], 0);
            $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':requestedDate', $start_time_timestamp);
            $stmt->bindParam(':requestedDateend', $end_time_timestamp);
            $stmt->execute();
            $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status = 'paid'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':requestedDate', $start_time);
            $stmt->bindParam(':requestedDateend', $end_time);
            $stmt->execute();
            $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_extend = $extend_stat['count'];
            $sum_extend = number_format($extend_stat['sum'], 0);
            $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':requestedDate', $start_time);
            $stmt->bindParam(':requestedDateend', $end_time);
            $stmt->execute();
            $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_extra_volume = $extra_volume_stat['count'];
            $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
            $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':requestedDate', $start_time);
            $stmt->bindParam(':requestedDateend', $end_time);
            $stmt->execute();
            $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_extra_time = $extra_time_stat['count'];
            $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
            $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':requestedDate', $start_time);
            $stmt->bindParam(':requestedDateend', $end_time);
            $stmt->execute();
            $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
            $count_change_location = $change_location_stat['count'];
            $sum_change_location = number_format($change_location_stat['sum'], 0);
            $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
            $stmt->bindParam(':requestedDate', $start_time_timestamp);
            $stmt->bindParam(':requestedDateend', $end_time_timestamp);
            $stmt->execute();
            $countuser_new = $stmt->rowCount();
            $statisticsall = "
🕐 <b>آمار تاریخ انتخابی</b>

⏳ بازه تایم  : $start_time تا $end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $statisticsall, $keyboardadmin, 'HTML');
            break;
        }

        return true;
    }
}
