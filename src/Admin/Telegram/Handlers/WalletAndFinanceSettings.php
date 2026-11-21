<?php

namespace Faoxima\Admin\Telegram\Handlers;

use DateTime;
use DateTimeZone;
use PDO;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminAction, $adminExecution, $backadmin, $dataget, $datagetr, $from_id, $keyboardadmin, $message_id, $pdo, $textbotlang, $update, $user.
 * Inherited or exported by reference: $Percent, $codeproduct, $datatime, $day, $extend, $grp, $id_user, $lastupdate, $namestatus, $output, $row, $status, $stmt, $text, $usernameconfig.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class WalletAndFinanceSettings
{
    public static function handle(
        $ManagePanel,
        &$Percent,
        $adminAction,
        $adminExecution,
        $backadmin,
        &$codeproduct,
        $dataget,
        $datagetr,
        &$datatime,
        &$day,
        &$extend,
        $from_id,
        &$grp,
        &$id_user,
        $keyboardadmin,
        &$lastupdate,
        $message_id,
        &$namestatus,
        &$output,
        $pdo,
        &$row,
        &$status,
        &$stmt,
        &$text,
        $textbotlang,
        $update,
        $user,
        &$usernameconfig
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $_minRaw = json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")['ValuePay'] ?? '{}', true);
            $_minRaw = is_array($_minRaw) ? $_minRaw : [];
            $_minLines = implode("\n", array_map(fn($k, $v) => "• <b>$k</b>: " . number_format((int)$v) . " تومان", array_keys($_minRaw), $_minRaw));
            $textmin = "📌 <b>کف شارژ کیف‌پول</b>\n\nمقادیر فعلی:\n" . ($_minLines ?: '—') . "\n\nمبلغ جدید را به تومان وارد کنید:";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textmin, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('minbalance', $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $text, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('getagentbalancemin', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 برای کدام گروه کاربری اعمال شود?\n\n• <b>n</b> — کاربر عادی\n• <b>n2</b> — کاربر ویژه\n• <b>f</b> — فروشنده\n• <b>allusers</b> — همه گروه‌ها", $backadmin, 'HTML');
            break;
        case 3:
            if (!isset($update['message']) && empty($text)) { return; }
            $agentst = ["n", "n2", "f", "allusers"];
            $grp = method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_resolveAgentGroupFromReplyButton') ? \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agentst) : (in_array($text, $agentst, true) ? $text : null);
            if ($grp === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
                return;
            }
            $text = $grp;
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $balancemaax = \Faoxima\Admin\Telegram\Support\FinanceFunctionsHelpers::rxAdminWalletLimitValue(
                \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")['ValuePay'] ?? '{}',
                "20000", $text, $user['Processing_value']
            );
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $balancemaax, "NamePay", "minbalance");
            break;
        case 4:
            $_maxRaw = json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'] ?? '{}', true);
            $_maxRaw = is_array($_maxRaw) ? $_maxRaw : [];
            $_maxLines = implode("\n", array_map(fn($k, $v) => "• <b>$k</b>: " . number_format((int)$v) . " تومان", array_keys($_maxRaw), $_maxRaw));
            $textmax = "📌 <b>سقف شارژ کیف‌پول</b>\n\nمقادیر فعلی:\n" . ($_maxLines ?: '—') . "\n\nمبلغ جدید را به تومان وارد کنید:";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textmax, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('maxbalance', $from_id);
            break;
        case 5:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $text, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('getagentbalancemax', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 برای کدام گروه کاربری اعمال شود?\n\n• <b>n</b> — کاربر عادی\n• <b>n2</b> — کاربر ویژه\n• <b>f</b> — فروشنده\n• <b>allusers</b> — همه گروه‌ها", $backadmin, 'HTML');
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            $agentst = ["n", "n2", "f", "allusers"];
            $grp = method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_resolveAgentGroupFromReplyButton') ? \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agentst) : (in_array($text, $agentst, true) ? $text : null);
            if ($grp === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
                return;
            }
            $text = $grp;
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $balancemaax = \Faoxima\Admin\Telegram\Support\FinanceFunctionsHelpers::rxAdminWalletLimitValue(
                \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'] ?? '{}',
                "1000000", $text, $user['Processing_value']
            );
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $balancemaax, "NamePay", "maxbalance");
            break;
        case 7:
            $id_user = $dataget[1];
            telegram('sendmessage', [
                'chat_id' => $from_id,
                'text' => $textbotlang['Admin']['agent']['useragentremoved'],
                'parse_mode' => "HTML",
                'reply_to_message_id' => $message_id,
            ]);
            \Faoxima\Core\Database\Db::update("user", "agent", "f", "id", $id_user);
            \Faoxima\Core\Database\Db::update("user", "pricediscount", "0", "id", $id_user);
            \Faoxima\Core\Database\Db::update("user", "expire", null, "id", $id_user);
            $stmt = $pdo->prepare("DELETE FROM Requestagent WHERE id = '$id_user'");
            $stmt->execute();
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 8:
            $id_user = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $id_user, "id", $from_id);
            telegram('sendmessage', [
                'chat_id' => $from_id,
                'text' => $textbotlang['Admin']['agent']['gettypeagent'],
                'parse_mode' => "HTML",
                'reply_markup' => $backadmin,
                'reply_to_message_id' => $message_id,
            ]);
            \Faoxima\Telegram\Navigation\Steps::step('gettypeagentoflist', $from_id);
            break;
        case 9:
            $agentst = ["n", "n2"];
            $grp = method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_resolveAgentGroupFromReplyButton') ? \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agentst) : (in_array($text, $agentst, true) ? $text : null);
            if ($grp === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
                return;
            }
            $text = $grp;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
            \Faoxima\Core\Database\Db::update("user", "expire", null, "id", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("user", "agent", $text, "id", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 10:
            $id_user = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $id_user, "id", $from_id);
            telegram('sendmessage', [
                'chat_id' => $from_id,
                'text' => "📌 تعداد درصدی که میخواهید در صورتی که کاربر هرگونه خریدی انجام داده است تخفیفی دریافت کند را ارسال نمایید.",
                'reply_markup' => $backadmin,
                'parse_mode' => "HTML",
                'reply_to_message_id' => $message_id,
            ]);
            \Faoxima\Telegram\Navigation\Steps::step('getpercentuser', $from_id);
            break;
        case 11:
            if (intval($text) > 100 || intval($text) < 0 || !ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "تغییرات با موفقیت اعمال شد", $keyboardadmin, 'HTML');
            \Faoxima\Core\Database\Db::update("user", "pricediscount", $text, "id", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 12:
            $id_user = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $id_user, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداکثر مبلغی که کاربر می توانید موجودی  اش در زمان خرید منفی شود را ارسال نمایید
توجه : عدد بدون خط تیره یا نماد منفی باشد
در صورتی که می خواهید کاربر نامحدود خریداری کند عدد 0 ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getmaxbuyagent', $from_id);
            break;
        case 13:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "تغییرات با موفقیت اعمال شد", $keyboardadmin, 'HTML');
            \Faoxima\Core\Database\Db::update("user", "maxbuyagent", $text, "id", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 14:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['order']['vieworderusername'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetusernameconfigAndOrdedrs', $from_id);
            break;
        case 15:
            \Faoxima\Services\Renewal\AdminRenewalFlow::rxArDraftResetIfAny($from_id, $user);
            if ($user['step'] == "GetusernameconfigAndOrdedrs") {
                if (!isset($update['message']) && empty($text)) { return; }
                $usernameconfig = $text;
                $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
                $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
            } elseif ($text[0] == "/") {
                $usernameconfig = explode(" ", $text)[1];
                $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
                $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
            } else {
                $usernameconfig = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $datagetr[1], "select")['username'];
                $sql = "SELECT * FROM invoice WHERE username = :username OR note  = :notes";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
                $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
            }
            $stmt->execute();
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            if ($stmt->rowCount() > 1) {
                $keyboardlists = [
                    'inline_keyboard' => [],
                ];
                $keyboardlists['inline_keyboard'][] = [
                    ['text' => "عملیات", 'callback_data' => "action"],
                    ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
                    ['text' => "نام کاربری", 'callback_data' => "username"],
                ];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $keyboardlists['inline_keyboard'][] = [
                        [
                            'text' => "مشاهده‌اطلاعات",
                            'callback_data' => "manageinvoice_" . $row['id_invoice']
                        ],
                        [
                            'text' => $row['Status'],
                            'callback_data' => "username"
                        ],
                        [
                            'text' => $row['username'],
                            'callback_data' => $row['username']
                        ],
                    ];
                }
                $keyboardlists = json_encode($keyboardlists);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ بیشتر از یک سرویس یافت از لیست زیر سرویس صحیح را انتخاب کنید", $keyboardlists, 'HTML');
                return;
            }
            $OrderUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$OrderUser) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['order']['notfound'], null, 'HTML');
                return;
            }
            $keyboardlists = [
                'inline_keyboard' => [],
            ];
            $keyboardlists['inline_keyboard'][] = [
                ['text' => "♻️ بروزرسانی", 'callback_data' => "manageinvoice_" . $OrderUser['id_invoice']],
            ];
            $keyboardlists['inline_keyboard'][] = [
                ['text' => $textbotlang['Admin']['ManageUser']['removeservice'], 'callback_data' => "removeservice-" . $OrderUser['id_invoice']],
                ['text' => $textbotlang['Admin']['ManageUser']['removeserviceandback'], 'callback_data' => "removeserviceandback-" . $OrderUser['id_invoice']],
            ];
            $keyboardlists['inline_keyboard'][] = [
                ['text' => "🗑 حذف کامل سرویس", 'callback_data' => "removefull-" . $OrderUser['id_invoice']],
            ];
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
            $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC LIMIT 20");
            $stmt->execute([
                ':username' => $OrderUser['username'],
            ]);
            if ($stmt->rowCount() != 0) {
                $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
                    $service_other = json_decode($service_other['value'], true);
                    $codeproduct = \Faoxima\Core\Database\Db::select("product", "name_product", "code_product", $service_other['code_product'], "select");
                    if ($codeproduct != false) {
                        $OrderUser['name_product'] = $codeproduct['name_product'];
                        $OrderUser['Volume'] = intval($codeproduct['Volume_constraint']) == 0 ? $textbotlang['users']['stateus']['Unlimited'] : $codeproduct['Volume_constraint'];
                        $OrderUser['Service_time'] = $codeproduct['Service_time'];
                    }
                }
            }
            $text_order = "
🛒 شماره سفارش  :  <code>{$OrderUser['id_invoice']}</code>
🛒  وضعیت سفارش در ربات : <code>{$OrderUser['Status']}</code>
🙍‍♂️ شناسه کاربر : <code>{$OrderUser['id_user']}</code>
👤 نام کاربری اشتراک :  <code>{$OrderUser['username']}</code>
📍 موقعیت سرویس :  {$OrderUser['Service_location']}
🛍 نام محصول :  {$OrderUser['name_product']}
💰 قیمت پرداختی سرویس : " . \Faoxima\Core\Support\Formatting::rxFormatToman($OrderUser['price_product']) . " تومان
⚜️ حجم سرویس خریداری شده : {$OrderUser['Volume']}
⏳ زمان سرویس خریداری شده : {$OrderUser['Service_time']}
📆 تاریخ خرید : $datatime
";
            $DataUserOut = $ManagePanel->DataUser($OrderUser['Service_location'], $OrderUser['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                $keyboard_json = json_encode($keyboardlists);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "کاربر در پنل وجود ندارد", $keyboardadmin, 'html');
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_order, $keyboard_json, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $lastonline = \Faoxima\Services\Delivery\ServiceDelivery::formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

            $status = $DataUserOut['status'];
            $status_var = [
                'active' => $textbotlang['users']['stateus']['active'],
                'limited' => $textbotlang['users']['stateus']['limited'],
                'disabled' => $textbotlang['users']['stateus']['disabled'],
                'expired' => $textbotlang['users']['stateus']['expired'],
                'on_hold' => $textbotlang['users']['stateus']['on_hold'],
                'Unknown' => $textbotlang['users']['stateus']['Unknown'],
                'deactivev' => $textbotlang['users']['stateus']['disabled'],
            ][$status];

            $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['stateus']['Unlimited'];

            $LastTraffic = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

            $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
            $RemainingVolume = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($output) : "نامحدود";

            $usedTrafficGb = $DataUserOut['used_traffic'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

            $timeDiff = $DataUserOut['expire'] - time();
            $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

            $lastupdate = "";
            if ($DataUserOut['sub_updated_at'] !== null) {
                $sub_updated = $DataUserOut['sub_updated_at'];
                $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
                $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
                $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
            }
            $limitValue = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
            $usedTrafficValue = isset($DataUserOut['used_traffic']) ? (float) $DataUserOut['used_traffic'] : 0;
            $Percent = \Faoxima\Core\Support\Formatting::safe_divide(($limitValue - $usedTrafficValue) * 100, $limitValue, 100);
            if ($Percent < 0) {
                $Percent = -$Percent;
            }
            $Percent = round($Percent, 2);
            $text_order .= "

 وضعیت سرویس : $status_var

🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)

لینک اشتراک کاربر :
<code>{$DataUserOut['subscription_url']}</code>

📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>";
            if ($DataUserOut['status'] == "active") {
                $namestatus = '❌ خاموش کردن اکانت';
            } else {
                $namestatus = '💡 روشن کردن اکانت';
            }
            $keyboardlists['inline_keyboard'][] = [
                ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extendadmin_' . $OrderUser['id_invoice']],
                ['text' => $textbotlang['users']['stateus']['config'], 'callback_data' => 'config_' . $OrderUser['id_invoice']],
            ];
            $keyboardlists['inline_keyboard'][] = [
                ['text' => $namestatus, 'callback_data' => 'changestatusadmin_' . $OrderUser['id_invoice']],
            ];
            $keyboard_json = json_encode($keyboardlists);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_order, $keyboard_json, 'HTML');
            $stmt = $pdo->prepare("SELECT * FROM service_other s WHERE username = :uc AND (status = 'paid' OR status IS NULL)");
            $stmt->execute([':uc' => (string) $usernameconfig]);
            $list_service = $stmt->fetchAll();
            if ($list_service) {
                foreach ($list_service as $extend) {
                    $extend_type = [
                        'extend_user' => "تمدید",
                        'extend_user_by_admin' => 'تمدید شده توسط ادمین',
                        'extra_user' => "حجم اضافه",
                        "extra_time_user" => "زمان اضافه",
                        "transfertouser" => "انتقال به حساب دیگر",
                        "extends_not_user" => "تمدید از نوع نبودن یوزر در لیست",
                        "change_location" => "تغییر لوکیشن",
                        'gift_time' => 'هدیه همگانی زمان',
                        'gift_volume' => 'هدیه همگانی حجم'
                    ][$extend['type']];
                    $time_jalali = jdate('Y/m/d H:i:s', strtotime($extend['time']));

                    $extendtext = "
📌 گزارش سرویس
🔗  نوع سرویس : $extend_type
🕰 زمان انجام سرویس : {$extend['time']} \n\n($time_jalali)
💰مبلغ انجام سرویس : " . \Faoxima\Core\Support\Formatting::rxFormatToman($extend['price']) . "
👤 آیدی عددی کاربر : {$extend['id_user']}
👤 نام کاربری کانفیگ: {$extend['username']}";
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $extendtext, null, 'HTML');
                }
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }

        return true;
    }
}
