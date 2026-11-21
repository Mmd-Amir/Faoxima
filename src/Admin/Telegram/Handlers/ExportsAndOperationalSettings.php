<?php

namespace Faoxima\Admin\Telegram\Handlers;

use Spreadsheet;
use Throwable;
use Xlsx;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $backuser, $callback_query_id, $connect, $dataget, $from_id, $keyboardexportdata, $rx_cron_apply, $rx_cron_cfg, $rx_cron_h, $rx_cron_hs, $setting, $setting_panel, $text, $textbotlang.
 * Inherited or exported by reference: $filename, $id_invoice, $invoice, $result, $row, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ExportsAndOperationalSettings
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $backuser,
        $callback_query_id,
        $connect,
        $dataget,
        &$filename,
        $from_id,
        &$id_invoice,
        &$invoice,
        $keyboardexportdata,
        &$result,
        &$row,
        $rx_cron_apply,
        $rx_cron_cfg,
        $rx_cron_h,
        $rx_cron_hs,
        $setting,
        $setting_panel,
        $text,
        $textbotlang,
        &$value
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardexportdata, 'HTML');
            break;
        case 2:
            \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cron_settings', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
            break;
        case 3:
            if (method_exists(\Faoxima\Core\Cron\Schedules::class, 'buildCronJobsKeyboard')) {
                \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cron_jobs', $from_id);
                $rx_cron_title = "🕚 زمان‌بندی کرون‌ها\n\nبرای تغییر بازهٔ اجرای هر کرون روی ⚙️ تنظیمات همان ردیف بزنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rx_cron_title, \Faoxima\Core\Cron\Schedules::buildCronJobsKeyboard(), 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ سیستم کرون در دسترس نیست.", $setting_panel, 'HTML');
            }
            break;
        case 4:
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 1,
                ]);
            }
            break;
        case 5:
            \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cron_settings', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
            break;
        case 6:
            if (method_exists(\Faoxima\Core\Cron\Schedules::class, 'getCronJobDefinitions') && method_exists(\Faoxima\Core\Cron\Schedules::class, 'loadCronSchedules') && method_exists(\Faoxima\Core\Cron\Schedules::class, 'describeCronSchedule')) {
                $rx_cron_key = $rx_cron_cfg[1];
                $rx_cron_defs = \Faoxima\Core\Cron\Schedules::getCronJobDefinitions();
                if (!isset($rx_cron_defs[$rx_cron_key])) {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => "❌ کرون نامعتبر",
                        'show_alert' => true,
                        'cache_time' => 5,
                    ]);
                    return;
                }
                $rx_cron_def = $rx_cron_defs[$rx_cron_key];
                $rx_cron_schedules = \Faoxima\Core\Cron\Schedules::loadCronSchedules();
                $rx_cron_current = $rx_cron_schedules[$rx_cron_key] ?? $rx_cron_def['default'];
                $rx_cron_current_label = \Faoxima\Core\Cron\Schedules::describeCronSchedule($rx_cron_current);
                $rx_cron_label = (string) ($rx_cron_def['admin_label'] ?? $rx_cron_key);
                $rx_cron_minute_options = [1, 2, 3, 5, 10, 15, 30];
                $rx_cron_hour_options   = [1, 2, 3, 6, 12];
                $rx_cron_day_options    = [1, 2, 5, 7];
                $rx_cron_rows = [];
                $rx_cron_row = [];
                foreach ($rx_cron_minute_options as $rx_cron_v) {
                    $rx_cron_row[] = ['text' => "🕒 هر {$rx_cron_v} دقیقه", 'callback_data' => "cronjob_apply-{$rx_cron_key}-minute-{$rx_cron_v}"];
                    if (count($rx_cron_row) === 2) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
                }
                if (!empty($rx_cron_row)) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
                foreach ($rx_cron_hour_options as $rx_cron_v) {
                    $rx_cron_row[] = ['text' => "⏰ هر {$rx_cron_v} ساعت", 'callback_data' => "cronjob_apply-{$rx_cron_key}-hour-{$rx_cron_v}"];
                    if (count($rx_cron_row) === 2) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
                }
                if (!empty($rx_cron_row)) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
                foreach ($rx_cron_day_options as $rx_cron_v) {
                    $rx_cron_row[] = ['text' => "📅 هر {$rx_cron_v} روز", 'callback_data' => "cronjob_apply-{$rx_cron_key}-day-{$rx_cron_v}"];
                    if (count($rx_cron_row) === 2) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
                }
                if (!empty($rx_cron_row)) { $rx_cron_rows[] = $rx_cron_row; }
                $rx_cron_rows[] = [
                    ['text' => "⛔ غیرفعال", 'callback_data' => "cronjob_apply-{$rx_cron_key}-disabled-1"],
                ];
                $rx_cron_hour_fields = ['lottery' => 'lottery_hour', 'statusday' => 'statusday_hour'];
                $rx_cron_has_hour = isset($rx_cron_hour_fields[$rx_cron_key]);
                $rx_cron_hour_now = $rx_cron_has_hour ? (int) ($setting[$rx_cron_hour_fields[$rx_cron_key]] ?? 0) : 0;
                if ($rx_cron_has_hour) {
                    $rx_cron_rows[] = [
                        ['text' => "🕛 ساعت اجرا: {$rx_cron_hour_now}:00", 'callback_data' => "cronjob_sethour-{$rx_cron_key}"],
                    ];
                }
                $rx_cron_rows[] = [
                    ['text' => "🔙 بازگشت به لیست کرون‌ها", 'callback_data' => "cronjobs_settings"],
                ];
                $rx_cron_keyboard = json_encode(['inline_keyboard' => $rx_cron_rows], JSON_UNESCAPED_UNICODE);
                $rx_cron_text = "⚙️ تنظیم زمان‌بندی\n\n📌 کرون: <b>{$rx_cron_label}</b>\n⏱ زمان فعلی: <b>{$rx_cron_current_label}</b>\n\nزمان‌بندی جدید را انتخاب کنید:";
                if ($rx_cron_has_hour) {
                    $rx_cron_text .= "\n\n🕛 اگر بازه را روی «هر ۱ روز» بگذاری، این کرون رأس ساعت <b>{$rx_cron_hour_now}:00</b> (به وقت تهران) اجرا می‌شود.\nبرای تغییرِ این ساعت، دکمهٔ «تنظیم ساعت اجرا» را بزن.\n(برای حالت «هر N ساعت/دقیقه» این ساعت بی‌اثر است و دقیقاً طبق همان بازه اجرا می‌شود.)";
                }
                \Faoxima\Telegram\Navigation\Steps::step('cronjob_set_value', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rx_cron_text, $rx_cron_keyboard, 'HTML');
            }
            break;
        case 7:
            if (method_exists(\Faoxima\Core\Cron\Schedules::class, 'updateCronSchedule') && method_exists(\Faoxima\Core\Cron\Schedules::class, 'buildCronJobsKeyboard')) {
                $rx_cron_key  = $rx_cron_apply[1];
                $rx_cron_unit = $rx_cron_apply[2];
                $rx_cron_val  = max(1, (int) $rx_cron_apply[3]);
                $rx_cron_ok   = \Faoxima\Core\Cron\Schedules::updateCronSchedule($rx_cron_key, ['unit' => $rx_cron_unit, 'value' => $rx_cron_val]);
                if (method_exists(\Faoxima\Core\Cron\Crontab::class, 'activecron')) {
                    try { @\Faoxima\Core\Cron\Crontab::activecron(); } catch (Throwable $rx_cron_e) {}
                }
                if (!empty($callback_query_id)) {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => $rx_cron_ok ? "✅ ذخیره شد" : "❌ ذخیره نشد",
                        'show_alert' => false,
                        'cache_time' => 0,
                    ]);
                }
                \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cron_jobs', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🕚 زمان‌بندی کرون‌ها\n\nبرای تغییر بازهٔ اجرای هر کرون روی ⚙️ تنظیمات همان ردیف بزنید.", \Faoxima\Core\Cron\Schedules::buildCronJobsKeyboard(), 'HTML');
            }
            break;
        case 8:
            $rx_h_field = $rx_cron_h[1] === 'lottery' ? 'lottery_hour' : 'statusday_hour';
            $rx_h_now   = (int) ($setting[$rx_h_field] ?? 0);
            \Faoxima\Telegram\Navigation\Steps::step("cronjob_get_hour-{$rx_cron_h[1]}", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🕛 ساعت اجرای این کرون را به‌صورت عددی بین <b>0</b> تا <b>23</b> ارسال کنید (به وقت تهران).\n\nساعت فعلی: <b>{$rx_h_now}:00</b>", $backadmin, 'HTML');
            break;
        case 9:
            $rx_h_field = $rx_cron_hs[1] === 'lottery' ? 'lottery_hour' : 'statusday_hour';
            if (!ctype_digit((string) $text) || (int) $text < 0 || (int) $text > 23) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفاً فقط یک عدد بین 0 تا 23 ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("setting", $rx_h_field, (int) $text, null, null);
            \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cron_jobs', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ ساعت اجرا روی <b>" . (int) $text . ":00</b> تنظیم شد.", \Faoxima\Core\Cron\Schedules::buildCronJobsKeyboard(), 'HTML');
            break;
        case 10:
            $counttable = \Faoxima\Core\Database\Db::select("user", "*", null, null, "count");
            if ($counttable == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
                return;
            }
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sql = "SELECT * FROM user";
            $result = $connect->query($sql);

            $col = 1;
            $headers = array_keys($result->fetch_assoc());
            foreach ($headers as $header) {
                $sheet->setCellValue([$col, 1], $header);
                $col++;
            }

            $row = 2;
            while ($row_data = $result->fetch_assoc()) {
                $col = 1;
                foreach ($row_data as $value) {
                    $sheet->setCellValue([$col, $row], $value);
                    $col++;
                }
                $row++;
            }
            $date = date("Y-m-d");
            $filename = "users_{$date}.xlsx";
            $writer = new Xlsx($spreadsheet);
            $writer->save($filename);
            sendDocument($from_id, $filename, "🪪 خروجی دیتای کاربران");
            unlink($filename);
            break;
        case 11:
            $counttable = \Faoxima\Core\Database\Db::select("invoice", "*", null, null, "count");
            if ($counttable == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
                return;
            }
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sql = "SELECT * FROM invoice";
            $result = $connect->query($sql);

            $col = 1;
            $headers = array_keys($result->fetch_assoc());
            foreach ($headers as $header) {
                $sheet->setCellValue([$col, 1], $header);
                $col++;
            }

            $row = 2;
            while ($row_data = $result->fetch_assoc()) {
                $col = 1;
                foreach ($row_data as $value) {
                    $sheet->setCellValue([$col, $row], $value);
                    $col++;
                }
                $row++;
            }
            $date = date("Y-m-d");
            $filename = "invoice_{$date}.xlsx";
            $writer = new Xlsx($spreadsheet);
            $writer->save($filename);
            sendDocument($from_id, $filename, "🪪 خروجی سفارشات کاربران");
            unlink($filename);
            break;
        case 12:
            $counttable = \Faoxima\Core\Database\Db::select("Payment_report", "*", null, null, "count");
            if ($counttable == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
                return;
            }
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sql = "SELECT * FROM Payment_report";
            $result = $connect->query($sql);

            $col = 1;
            $headers = array_keys($result->fetch_assoc());
            foreach ($headers as $header) {
                $sheet->setCellValue([$col, 1], $header);
                $col++;
            }

            $row = 2;
            while ($row_data = $result->fetch_assoc()) {
                $col = 1;
                foreach ($row_data as $value) {
                    $sheet->setCellValue([$col, $row], $value);
                    $col++;
                }
                $row++;
            }
            $date = date("Y-m-d");
            $filename = "Payment_report_{$date}.xlsx";
            $writer = new Xlsx($spreadsheet);
            $writer->save($filename);
            sendDocument($from_id, $filename, "🪪 خروجی پرداختی های کاربران");
            unlink($filename);
            break;
        case 13:
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
            \Faoxima\Telegram\Navigation\Steps::step("descriptionsrequsts", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $requestcheck['username'], "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['requestadmin'], $backuser, 'HTML');
            break;
        }

        return true;
    }
}
