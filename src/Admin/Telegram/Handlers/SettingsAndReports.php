<?php

namespace Faoxima\Admin\Telegram\Handlers;

use Faoxima\Admin\Security\TelegramAccess;
use PDO;

final class SettingsAndReports
{
    public static function promptChannelLink(TelegramAccess $access, array &$user, array $editLinkMatches, mixed $backadmin): ?bool
    {
        $from_id = $access->senderId;
        $chanId = isset($editLinkMatches[1]) ? (int)$editLinkMatches[1] : (int)$user['Processing_value'];
        \Faoxima\Core\Database\Db::update("user", "Processing_value", $chanId, "id", $from_id);
        \Faoxima\Telegram\Navigation\Steps::step('ch_edit_link', $from_id);
        $editBackKb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_edit_back_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_edit_back_keyboard($chanId) : $backadmin;
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 یوزرنیم کانال (با @) یا آیدی عددی کانال (که با -100 شروع می‌شود) را ارسال کنید:", $editBackKb, 'HTML');
        return true;
    }

    public static function saveChannelLink(TelegramAccess $access, PDO $pdo, array &$user, mixed &$text, mixed &$channelkeyboard, array $update): ?bool
    {
        $from_id = $access->senderId;
        if (!isset($update['message']) && empty($text)) { return null; }
        $chanId = (int)($user['Processing_value'] ?? 0);
        if ($chanId > 0) {
            $stmt = $pdo->prepare("UPDATE channels SET link = :lnk WHERE id = :id");
            $stmt->execute([':lnk' => trim($text), ':id' => $chanId]);
        }
        if ($chanId > 0 && method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_render_channel_manage')) {
            \Faoxima\Admin\Telegram\ChannelNavigation::rx_render_channel_manage($chanId, $from_id, "✅ آیدی کانال با موفقیت ویرایش شد.");
        } else {
            $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
            \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
            if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آیدی کانال با موفقیت ویرایش شد.", $channelkeyboard, 'HTML');
        }
        return true;
    }

    public static function promptChannelRemoval(TelegramAccess $access, mixed &$channelkeyboard, array $textbotlang): ?bool
    {
        $from_id = $access->senderId;
        $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
        \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
        if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
            \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removechannel'], $channelkeyboard, 'HTML');
        return true;
    }

    public static function removeChannel(TelegramAccess $access, PDO $pdo, mixed &$text, mixed &$channelkeyboard, array $update, array $textbotlang): ?bool
    {
        $from_id = $access->senderId;
        if (!isset($update['message']) && empty($text)) { return null; }
        $stmt = $pdo->prepare("DELETE FROM channels WHERE link = :link OR id = :id");
        $stmt->bindValue(':link', $text, PDO::PARAM_STR);
        $stmt->bindValue(':id', is_numeric($text) ? (int)$text : 0, PDO::PARAM_INT);
        $stmt->execute();
        $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
        \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
        if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
            \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removedchannel'], $channelkeyboard, 'HTML');
        return true;
    }

    public static function promptAdminId(TelegramAccess $access, array $textbotlang, mixed $backadmin): ?bool
    {
        $from_id = $access->senderId;
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('addadmin', $from_id);
        return true;
    }

    public static function acceptAdminId(TelegramAccess $access, mixed &$text, array $update, array $textbotlang, mixed $backadmin, mixed $adminrule): ?bool
    {
        $from_id = $access->senderId;
        if (!isset($update['message']) && empty($text)) { return null; }
        $adminId = trim($text);
        if ($adminId === '') {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
            return null;
        }
        \Faoxima\Core\Database\Db::update("user", "Processing_value", $adminId, "id", $from_id);
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['setrule'], $adminrule, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('getrule', $from_id);
        return true;
    }

    public static function saveAdmin(TelegramAccess $access, PDO $pdo, array &$user, mixed &$text, array $update, array $textbotlang, mixed $backadmin, mixed $keyboardadmin, mixed $username, array $setting, mixed $otherreport): ?bool
    {
        $from_id = $access->senderId;
        if (!isset($update['message']) && empty($text)) { return null; }
        $rule = ['administrator', 'Seller', 'support'];
        if (!in_array($text, $rule)) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['invalidrule'], $backadmin, 'HTML');
            return null;
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['addadminset'], $keyboardadmin, 'HTML');
        sendmessage($user['Processing_value'], $textbotlang['Admin']['manageadmin']['adminedsenduser'], null, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        $usernamepanel = "root";
        $randomString = bin2hex(random_bytes(5));
        $randomStringHash = password_hash($randomString, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin (id_admin, username, password, password_hash, rule) VALUES (:id_admin, :username, :password, :password_hash, :rule)");
        $stmt->bindParam(':id_admin', $user['Processing_value'], PDO::PARAM_STR);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':password', $randomString, PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $randomStringHash, PDO::PARAM_STR);
        $stmt->bindParam(':rule', $text, PDO::PARAM_STR);
        $stmt->execute();
        $text_report = sprintf($textbotlang['Admin']['reportgroup']['adminadded'], $username, $from_id, $text, $user['Processing_value']);
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        return true;
    }

    public static function promptUserTestLimit(TelegramAccess $access, array $dataget, array $textbotlang, mixed $backadmin): ?bool
    {
        $from_id = $access->senderId;
        $iduser = $dataget[1];
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['getid'], $backadmin, 'HTML');
        \Faoxima\Core\Database\Db::update("user", "Processing_value", $iduser, "id", $from_id);
        \Faoxima\Telegram\Navigation\Steps::step('get_number_limit', $from_id);
        return true;
    }

    public static function saveUserTestLimit(TelegramAccess $access, array &$user, mixed &$text, array $update, array $textbotlang, mixed $backadmin, mixed $keyboardadmin): ?bool
    {
        $from_id = $access->senderId;
        if (!isset($update['message']) && empty($text)) { return null; }
        $text = method_exists(\Faoxima\Services\TestAccounts\TestAccountPolicy::class, 'rx_test_normalize_digits') ? \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_normalize_digits($text) : trim((string) $text);
        if (!ctype_digit((string) $text) || strlen((string) $text) > 7) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, '❌ مقدار نامعتبر است. یک عدد صحیح ارسال کنید.', $backadmin, 'HTML');
            return null;
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['setlimit'], $keyboardadmin, 'HTML');
        $id_user_set = $text;
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        \Faoxima\Core\Database\Db::update("user", "limit_usertest", $text, "id", $user['Processing_value']);
        if (method_exists(\Faoxima\Services\TestAccounts\TestAccountPolicy::class, 'rx_test_override_set')) {
            \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_override_set($user['Processing_value'], $text, $from_id);
        }
        return true;
    }

    public static function promptGlobalTestLimit(TelegramAccess $access, array $textbotlang, mixed $backadmin): ?bool
    {
        $from_id = $access->senderId;
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['limitall'], $backadmin, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('limit_usertest_allusers', $from_id);
        return true;
    }

    public static function saveGlobalTestLimit(TelegramAccess $access, mixed &$text, array $update, array $textbotlang, mixed $backadmin, mixed $keyboardadmin): ?bool
    {
        $from_id = $access->senderId;
        if (!isset($update['message']) || !ctype_digit((string) $text) || strlen((string) $text) > 7) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, '❌ مقدار نامعتبر است. یک عدد صحیح ارسال کنید.', $backadmin, 'HTML');
            return null;
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['setlimitall'], $keyboardadmin, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        \Faoxima\Core\Database\Db::update("user", "limit_usertest", $text);
        \Faoxima\Core\Database\Db::update("setting", "limit_usertest_all", $text);
        return true;
    }

    public static function showChannels(TelegramAccess $access, array &$user, mixed &$channelkeyboard, array $textbotlang): ?bool
    {
        $from_id = $access->senderId;
        $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
        \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
        if (isset($user) && is_array($user)) {
            $user['step'] = 'channel';
        }
        if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
            \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['description'], $channelkeyboard, 'HTML');
        return true;
    }

    public static function reportStatistics(TelegramAccess $access, PDO $pdo, array $datatextbot, mixed $datain, mixed $message_id, mixed $keyboard_stat): ?bool
    {
        $from_id = $access->senderId;
        $Balanceall = \Faoxima\Core\Database\Db::select("user", "SUM(Balance)", null, null, "select")['SUM(Balance)'];
        $statistics = \Faoxima\Admin\Reporting\Statistics::rx_stats_total_users($pdo);
        $sumpanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", null, null, "count");
        $sql1 = "SELECT COUNT(id) AS count FROM user WHERE agent != 'f'";
        $stmt1 = $pdo->query($sql1);
        $agentsum = $stmt1->fetch(PDO::FETCH_ASSOC)['count'];
        $agentsumn = \Faoxima\Core\Database\Db::select("user", "COUNT(id)", "agent", "n", "select")['COUNT(id)'];
        $agentsumn2 = \Faoxima\Core\Database\Db::select("user", "COUNT(id)", "agent", "n2", "select")['COUNT(id)'];
        $rxOperationalSummary = \Faoxima\Admin\Reporting\Statistics::rx_stats_operational_summary($pdo);
        $rxSalesSummary = \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_summary($pdo);
        $invoiceactive = $rxOperationalSummary['count'];
        $invoice = $rxSalesSummary['count'];
        $invoicesum = $rxOperationalSummary['amount'];
        $invoiceTotal = $rxSalesSummary['amount'];
        $invoicesumall = number_format($invoiceTotal, 0);
        $extendsum = \Faoxima\Admin\Reporting\Statistics::rx_stats_extend_paid_sum($pdo);
        $count_usertest = \Faoxima\Core\Database\Db::select("invoice", "*", "name_product", "سرویس تست", "count");
        $timeacc = jdate('H:i:s', time());
        $statisticsorder = $rxSalesSummary['paying_users'];
        $statispay = \Faoxima\Admin\Reporting\Statistics::rx_stats_real_payment_totals($pdo);
        $date = date("Y-m-d");
        $timeacc = jdate('H:i:s', time());
        $start_time = date('d.m.Y', strtotime("-1 days")) . " 00:00:00";
        $end_time = date('d.m.Y', strtotime("-1 days")) . " 23:59:59";
        $start_time_timestamp = strtotime($start_time);
        $end_time_timestamp = strtotime($end_time);
        $sql = "SELECT SUM(price_product) FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . \Faoxima\Admin\Reporting\Statistics::rx_stats_operational_service_predicate();
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':requestedDate', $start_time_timestamp);
        $stmt->bindParam(':requestedDateend', $end_time_timestamp);
        $stmt->execute();
        $suminvoiceday = $stmt->fetch(PDO::FETCH_ASSOC)['SUM(price_product)'];
        $invoicesum = (float) ($invoicesum ?? 0);
        $extendsum = (float) ($extendsum ?? 0);
        $suminvoiceday = (float) ($suminvoiceday ?? 0);
        $statistics = (int) ($statistics ?? 0);
        $statisticsorder = (int) ($statisticsorder ?? 0);
        $paycount = "";
        $ratecustomer = round(\Faoxima\Core\Support\Formatting::safe_divide($statisticsorder * 100, $statistics, 0), 2);
        $averagePurchase = \Faoxima\Core\Support\Formatting::safe_divide($invoiceTotal, $statisticsorder, 0);
        $avgbuy_customer = $averagePurchase > 0 ? number_format($averagePurchase) : '0';
        $monthe_buy = number_format($suminvoiceday * 30);
        $percent_of_extend = round(\Faoxima\Core\Support\Formatting::safe_divide($extendsum * 100, $invoicesum, 0), 2);
        $percent_of_extend = $percent_of_extend > 100 ? 100 : $percent_of_extend;
        $extendsum = number_format($extendsum, 0);
        if (!empty($statispay)) {
            $statusLabels = [
                'cart to cart' => $datatextbot['carttocart'] ?? 'cart to cart',
                'aqayepardakht' => $datatextbot['aqayepardakht'] ?? 'aqayepardakht',
                'zarinpal' => $datatextbot['zarinpal'] ?? 'zarinpal',
                'zarinpey' => $datatextbot['zarinpey'] ?? 'zarinpey',
                'zarinpay' => $datatextbot['zarinpey'] ?? ($datatextbot['zarinpal'] ?? 'zarinpay'),
                'plisio' => $datatextbot['textnowpayment'] ?? 'plisio',
                'arze digital offline' => $datatextbot['textnowpaymenttron'] ?? 'arze digital offline',
                'Currency Rial 1' => $datatextbot['iranpay2'] ?? 'Currency Rial 1',
                'Currency Rial 2' => $datatextbot['iranpay3'] ?? 'Currency Rial 2',
                'Currency Rial 3' => $datatextbot['iranpay1'] ?? 'Currency Rial 3',
                'tonpay' => $datatextbot['tonpay'] ?? 'tonpay',
                'rialit' => $datatextbot['rialit'] ?? 'rialit',
                'cubepay' => $datatextbot['cubepay'] ?? 'cubepay',
                'blupal' => $datatextbot['blupal'] ?? 'blupal',
                'abangateway' => $datatextbot['abangateway'] ?? 'abangateway',
                'atlaspay' => $datatextbot['atlaspay'] ?? 'atlaspay',
                'tetrapay' => $datatextbot['tetrapay'] ?? 'tetrapay',
                'paymentnotverify' => $datatextbot['textpaymentnotverify'] ?? 'paymentnotverify',
                'Star Telegram' => $datatextbot['text_star_telegram'] ?? 'Star Telegram',
            ];

            foreach ($statispay as $tracepay) {
                $paymentMethod = $tracepay['Payment_Method'] ?? '';
                $status_var = $statusLabels[$paymentMethod] ?? $paymentMethod;
                $paycount .= "
📌 نام درگاه : <code>$status_var</code>
 - تعداد پرداخت موفق : <code>{$tracepay['countpay']}</code>
 - جمع پرداختی ها : <code>{$tracepay['sumpay']}</code>\n";
            }
        }
        $bot_ping = 'نامشخص';
        $ping_start_time = microtime(true);
        $ping_response = telegram('getMe');
        $ping_duration = (microtime(true) - $ping_start_time) * 1000;
        if (is_array($ping_response) && !empty($ping_response['ok'])) {
            $bot_ping = number_format(max($ping_duration, 0), 0) . ' میلی‌ثانیه';
        }

        $statisticsall = "📊 <b>آمار کلی ربات</b>
━━━━━━━━━━━━━━━━━━
👥 <b>تعداد کل کاربران:</b> <code>$statistics</code> نفر
💳 <b>کاربران دارای خرید:</b> <code>$statisticsorder</code> نفر
🧪 <b>اکانت‌های تست:</b> <code>$count_usertest</code> نفر
💰 <b>موجودی کل کاربران:</b> <code>$Balanceall</code> تومان

🧾 <b>تعداد کل فروش:</b> <code>$invoice</code> عدد
🧾 <b>تعداد کل فروش سرویس های فعال:</b> <code>$invoiceactive</code> عدد
💵 <b>جمع کل فروش :</b> <code>$invoicesumall</code> تومان
💵 <b>جمع کل فروش سرویس های فعال:</b> <code>$invoicesum</code> تومان
🔄 <b>جمع کل تمدید:</b> <code>$extendsum</code> تومان
📈 <b>نرخ تبدیل به مشتری:</b> <code>$ratecustomer</code>٪
💳 <b>میانگین خرید هر مشتری:</b> <code>$avgbuy_customer</code> تومان
📅 <b>درآمد پیش‌بینی‌شده ماهانه:</b> <code>$monthe_buy</code> تومان
📊 <b>درصد تمدید از فروش:</b> <code>$percent_of_extend</code>٪

👨‍💼 <b>تعداد کل نمایندگان:</b> <code>$agentsum</code> نفر
🔹 <b>نمایندگان نوع N:</b> <code>$agentsumn</code> نفر
🔸 <b>نمایندگان نوع N2:</b> <code>$agentsumn2</code> نفر
🧩 <b>تعداد پنل‌ها:</b> <code>$sumpanel</code> عدد
📡 <b>پینگ ربات:</b> $bot_ping
$paycount
";
        if ($datain == "stat_all_bot") {
            Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
        } else {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $statisticsall, $keyboard_stat, 'HTML');
        }
        return true;
    }
}
