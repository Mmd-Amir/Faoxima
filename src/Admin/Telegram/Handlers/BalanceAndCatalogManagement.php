<?php

namespace Faoxima\Admin\Telegram\Handlers;

use RuntimeException;
use Throwable;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $connect, $dataget, $datain, $from_id, $id_user, $keyboardadmin, $lastmessage, $message_id, $paymentreports, $pdo, $setting, $sumvolume, $text, $text_expie_agent, $textbotlang, $update, $username.
 * Inherited or exported by reference: $stmt, $user, $userjoin.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BalanceAndCatalogManagement
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $connect,
        $dataget,
        $datain,
        $from_id,
        $id_user,
        $keyboardadmin,
        $lastmessage,
        $message_id,
        $paymentreports,
        $pdo,
        $setting,
        &$stmt,
        $sumvolume,
        $text,
        $text_expie_agent,
        $textbotlang,
        $update,
        &$user,
        &$userjoin,
        $username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['NegativeBalance'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('Negative_Balance', $from_id);
            break;
        case 2:
            if (!\Faoxima\Core\Database\Db::userExists($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['PriceBalancek'], $backadmin, 'HTML');
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $text, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('get_price_Negative', $from_id);
            break;
        case 3:
            if (!ctype_digit($text) || intval($text) <= 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
                return;
            }
            if (intval($text) >= 100000000) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداکثر مقدار 100 میلیون ریال است.", $backadmin, 'HTML');
                return;
            }
            $rxNegOk = false;
            $rxNegReason = '';
            try {
                $pdo->beginTransaction();
                $rxNegClaim = $pdo->prepare("UPDATE user SET step = 'home' WHERE id = :admin AND step = 'get_price_Negative'");
                $rxNegClaim->execute([':admin' => $from_id]);
                if ($rxNegClaim->rowCount() < 1) {
                    $pdo->rollBack();
                    return;
                }
                $rxNegCharge = \Faoxima\Wallet\Balance\Balances::charge($pdo, $user['Processing_value'], (int) $text, 0);
                if (empty($rxNegCharge['ok'])) {
                    $rxNegReason = (string) ($rxNegCharge['reason'] ?? '');
                    throw new RuntimeException('balance_atomic_charge failed: ' . $rxNegReason);
                }
                if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                    \Faoxima\Wallet\Ledger\Ledger::record($pdo, $user['Processing_value'], 'debit', $text, 'admin_debit', 'کاهش موجودی توسط ادمین');
                }
                $pdo->commit();
                $rxNegOk = true;
            } catch (Throwable $rxNegErr) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin_negative_balance] ' . $rxNegErr->getMessage());
                if (function_exists('rx_log_event')) {
                    rx_log_event('ADMIN_NEGATIVE_BALANCE_FAILED', $rxNegErr->getMessage(), [
                        'admin_id' => $from_id,
                        'id_user' => $user['Processing_value'],
                        'amount' => $text,
                    ]);
                }
            }
            if (!$rxNegOk) {
                $rxNegMsg = $rxNegReason === 'insufficient-or-stale'
                    ? "❌ موجودی کاربر برای کسر این مبلغ کافی نیست."
                    : "❌ کسر موجودی انجام نشد. دوباره تلاش کنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxNegMsg, $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['NegativeBalanceUser'], $keyboardadmin, 'HTML');
            $balances1 = number_format($text, 0);
            $Balance_user_afters = number_format(\Faoxima\Core\Database\Db::select("user", "*", "id", $user['Processing_value'], "select", ['cache' => false])['Balance']);
            $textkam = "❌ کاربر عزیز مبلغ $balances1 تومان از  موجودی کیف پول تان کسر گردید.";
            sendmessage($user['Processing_value'], $textkam, null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            if (strlen($setting['Channel_Report']) > 0) {
                $textaddbalance = "📌 یک ادمین موجودی کاربر را کم کرده است :

🪪 اطلاعات ادمین کم کننده موجودی :
<blockquote>نام کاربری :@$username</blockquote>
<blockquote>آیدی عددی : $from_id</blockquote>
👤 اطلاعات کاربر  :
<blockquote>آیدی عددی کاربر  : {$user['Processing_value']}</blockquote>
<blockquote>مبلغ موجودی : $text</blockquote>
<blockquote>موجودی کاربر پس از کم کردن : $Balance_user_afters</blockquote>";
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $paymentreports,
                    'text' => $textaddbalance,
                    'parse_mode' => "HTML"
                ]);
            }
            break;
        case 4:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetIdUserunblock'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('show_info', $from_id);
            break;
        case 5:
            $rxCardpayToggle = is_string($datain) && preg_match('/^toggle_user_cardpayment_(\d+)$/', $datain, $rxCardpayMatch);
            $rxCardpayNotice = null;
            if ($rxCardpayToggle) {
                $id_user = $rxCardpayMatch[1];
            } elseif ($user['step'] == "show_info") {
                if (!isset($update['message']) && empty($text)) { return; }
                $id_user = $text;
            } elseif (explode(" ", $text)[0] == "/user") {
                $id_user = explode(" ", $text)[1];
            } elseif (explode(" ", $text)[0] == "/id") {
                $id_user = explode(" ", $text)[1];
            } else {
                $id_user = $dataget[1];
            }
            if (!\Faoxima\Core\Database\Db::userExists($id_user)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
                return;
            }
            if ($rxCardpayToggle) {
                $rxCardpayOk = false;
                $rxCardpayNew = null;
                try {
                    $rxCardpayStmt = $pdo->prepare("SELECT cardpayment FROM user WHERE id = ? LIMIT 1");
                    $rxCardpayStmt->execute([$id_user]);
                    $rxCardpayCurrent = $rxCardpayStmt->fetchColumn();
                    $rxCardpayStmt->closeCursor();
                    if ($rxCardpayCurrent !== false) {
                        $rxCardpayNew = intval($rxCardpayCurrent) === 1 ? "0" : "1";
                        \Faoxima\Core\Database\Db::update("user", "cardpayment", $rxCardpayNew, "id", $id_user);
                        $rxCardpayStmt->execute([$id_user]);
                        $rxCardpayAfter = $rxCardpayStmt->fetchColumn();
                        $rxCardpayStmt->closeCursor();
                        $rxCardpayOk = $rxCardpayAfter !== false && (string) intval($rxCardpayAfter) === $rxCardpayNew;
                    }
                } catch (Throwable $rxCardpayErr) {
                    error_log('[toggle_user_cardpayment] ' . $rxCardpayErr->getMessage());
                    $rxCardpayOk = false;
                }
                if (!$rxCardpayOk) {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => "❌ تغییر وضعیت نمایش شماره کارت انجام نشد. دوباره تلاش کنید.",
                        'show_alert' => true,
                        'cache_time' => 0,
                    ]);
                    return;
                }
                $rxCardpayNotice = $rxCardpayNew === "1"
                    ? "✅ نمایش شماره کارت برای این کاربر فعال شد"
                    : "✅ نمایش شماره کارت برای این کاربر غیرفعال شد";
            }
            $date = date("Y-m-d");
            $_stmt = $connect->prepare("SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ?");
            $_stmt->bind_param("s", $id_user); $_stmt->execute();
            $dayListSell = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
            $_stmt = $connect->prepare("SELECT SUM(price) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = ? AND Payment_Method != 'low balance by admin'");
            $_stmt->bind_param("s", $id_user); $_stmt->execute();
            $balanceall = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
            $_stmt = $connect->prepare("SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ?");
            $_stmt->bind_param("s", $id_user); $_stmt->execute();
            $subbuyuser = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
            $invoicecount = \Faoxima\Core\Database\Db::select("invoice", '*', "id_user", $id_user, "count");
            if ($invoicecount == 0) {
                $sumvolume['SUM(Volume)'] = 0;
            } else {
                $_stmt = $connect->prepare("SELECT SUM(Volume) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ? AND name_product != 'سرویس تست'");
                $_stmt->bind_param("s", $id_user); $_stmt->execute();
                $sumvolume = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
            }
            $user = \Faoxima\Core\Database\Db::select("user", "*", "id", $id_user, "select", ['cache' => false]);
            $roll_Status = [
                '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'],
                '0' => $textbotlang['Admin']['ManageUser']['Failedphone'],
            ][$user['roll_Status']];
            if ($subbuyuser['SUM(price_product)'] == null)
                $subbuyuser['SUM(price_product)'] = 0;
            $keyboardmanage = [
                'inline_keyboard' => [
                    [['text' => "♻️  بروزرسانی اطلاعات", 'callback_data' => "updateinfouser_" . $id_user],],
                    [['text' => $textbotlang['Admin']['ManageUser']['addbalanceuser'], 'callback_data' => "addbalanceuser_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['lowbalanceuser'], 'callback_data' => "lowbalanceuser_" . $id_user],],
                    [['text' => $textbotlang['Admin']['ManageUser']['banuserlist'], 'callback_data' => "banuserlist_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['unbanuserlist'], 'callback_data' => "unbanuserr_" . $id_user]],
                    [['text' => $textbotlang['Admin']['ManageUser']['addagent'], 'callback_data' => "addagent_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['removeagent'], 'callback_data' => "removeagent_" . $id_user]],
                    [['text' => $textbotlang['Admin']['ManageUser']['confirmnumber'], 'callback_data' => "confirmnumber_" . $id_user]],
                    [['text' => "🎁 درصد تخفیف", 'callback_data' => "Percentlow_" . $id_user], ['text' => "✍️ ارسال پیام", 'callback_data' => "sendmessageuser_" . $id_user]],
                    [['text' => $textbotlang['Admin']['ManageUser']['vieworderuser'], 'callback_data' => "vieworderuser_" . $id_user]],
                    [['text' => "👥 زیرمجموعه‌ها", 'callback_data' => "affiliates-" . $id_user]],
                    [['text' => "🔄 خروج از زیرمجموعه", 'callback_data' => "removeaffiliate-" . $id_user], ['text' => "🔄 حذف زیرمجموعه‌ها", 'callback_data' => "removeaffiliateuser-" . $id_user]],
                    [['text' => "احراز هویت کاربر", 'callback_data' => "verify_" . $id_user], ['text' => "عدم احراز کاربر", 'callback_data' => "unverify-" . $id_user]],
                    [['text' => "🛒 افزودن سفارش", 'callback_data' => "addordermanualـ" . $id_user], ['text' => "➕ محدودیت تست", 'callback_data' => "limitusertest_" . $id_user]],
                    [['text' => $textbotlang['Admin']['ManageUser']['viewpaymentuser'], 'callback_data' => "viewpaymentuser_" . $id_user], ['text' => "انتقال حساب کاربری ", 'callback_data' => "transferaccount_" . $id_user]],
                    [['text' => "💡 خاموش کردن", 'callback_data' => "disableconfig-" . $id_user], ['text' => "💡 روشن کردن", 'callback_data' => "activeconfig-" . $id_user]],
                    [['text' => "📑 احراز عضویت", 'callback_data' => "confirmchannel-" . $id_user], ['text' => "0️⃣ صفر کردن موجودی", 'callback_data' => "zerobalance-" . $id_user]],
                    [['text' => "🕚 وضعیت ارسال پیام های کرون", 'callback_data' => "statuscronuser-" . $id_user]],
                    [[
                        'text' => intval($user['cardpayment'] ?? 0) === 1
                            ? "💳 غیرفعال‌سازی نمایش شماره کارت"
                            : "💳 فعال‌سازی نمایش شماره کارت",
                        'callback_data' => "toggle_user_cardpayment_" . $id_user,
                    ]],
                    [['text' => "💳 منوی کارت", 'callback_data' => "usercardmenu_" . $id_user]],
                    [[
                        'text' => intval($user['card_verify_bypass'] ?? 0) === 1
                            ? "🛡️ فعال‌سازی احراز کارت"
                            : "🛡️ حذف احراز کارت",
                        'callback_data' => intval($user['card_verify_bypass'] ?? 0) === 1
                            ? "cardverifyon_" . $id_user
                            : "cardverifyoff_" . $id_user,
                    ]],
                ]
            ];
            if ($user['agent'] == "n2")
                $keyboardmanage['inline_keyboard'][] = [['text' => "سقف خرید  نماینده", 'callback_data' => "maxbuyagent_" . $id_user]];
            if ($user['agent'] != "f") {
                $keyboardmanage['inline_keyboard'][] = [
                    ['text' => "🤖 فعال‌سازی ربات", 'callback_data' => "createbot_" . $id_user],
                    ['text' => "❌ حذف ربات فروش", 'callback_data' => "removebotsell_" . $id_user]
                ];
            }
            if ($user['agent'] != "f") {
                $keyboardmanage['inline_keyboard'][] = [
                    ['text' => "🔋 قیمت پایه حجم", 'callback_data' => "setvolumesrc_" . $id_user],
                    ['text' => "⏳ قیمت پایه زمان", 'callback_data' => "settimepricesrc_" . $id_user]
                ];
                $keyboardmanage['inline_keyboard'][] = [
                    ['text' => "❌ مخفی کردن یک پنل برای نماینده", 'callback_data' => "hidepanel_" . $id_user],
                ];
                $keyboardmanage['inline_keyboard'][] = [
                    ['text' => "🗑 نمایش پنل های مخفی شده", 'callback_data' => "removehide_" . $id_user],
                ];
                $keyboardmanage['inline_keyboard'][] = [
                    ['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => "expireset_" . $id_user],
                ];
            }
            if (intval($setting['statuslimitchangeloc']) == 1) {
                $keyboardmanage['inline_keyboard'][] = [
                    ['text' => "محدودیت تغییر لوکیشن", 'callback_data' => "changeloclimitbyuser_" . $id_user]
                ];
            }
            $keyboardmanage['inline_keyboard'][] = [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ];
            $keyboardmanage = json_encode($keyboardmanage, JSON_UNESCAPED_UNICODE);
            if ($user['register'] != "none") {
                if ($user['register'] == null)
                    return;
                $userjoin = jdate('Y/m/d H:i:s', $user['register']);
            } else {
                $userjoin = "نامشخص";
            }
            $userverify = [
                '0' => "احراز نشده",
                '1' => "احراز شده"
            ][$user['verify']];
            $showcart = [
                '0' => "مخفی",
                '1' => "نمایش داده می شود"
            ][$user['cardpayment']];
            if ($user['last_message_time'] == null) {
                $lastmessage = "";
            } else {
                $lastmessage = jdate('Y/m/d H:i:s', $user['last_message_time']);
            }
            $datefirst = time() - 86400;
            $desired_date_time_start = time() - 3600;
            $month_date_time_start = time() - 2592000;
            $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_user', $id_user);
            $stmt->bindParam(':requestedDate', $desired_date_time_start);
            $stmt->execute();
            $listhours = $stmt->rowCount();
            $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_user', $id_user);
            $stmt->bindParam(':requestedDate', $desired_date_time_start);
            $stmt->execute();
            $suminvoicehours = $stmt->fetchColumn();
            if ($suminvoicehours == null) {
                $suminvoicehours = "0";
            }
            $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_user', $id_user);
            $stmt->bindParam(':requestedDate', $month_date_time_start);
            $stmt->execute();
            $listmonth = $stmt->rowCount();
            $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_user', $id_user);
            $stmt->bindParam(':requestedDate', $month_date_time_start);
            $stmt->execute();
            $suminvoicemonth = $stmt->fetchColumn();
            if ($suminvoicemonth == null) {
                $suminvoicemonth = "0";
            }
            if ($user['agent'] != "f" && $user['expire'] != null) {
                $text_expie_agent = "⭕️ تاریخ پایان نمایندگی : " . jdate('Y/m/d H:i:s', $user['expire']);
            } else {
                $text_expie_agent = "";
            }
            $rxFmtUserBalance = \Faoxima\Core\Support\Formatting::rxFormatToman($user['Balance']);
            $rxFmtBalanceAllSum = \Faoxima\Core\Support\Formatting::rxFormatToman($balanceall['SUM(price)'] ?? 0);
            $rxFmtSubbuyuserSum = \Faoxima\Core\Support\Formatting::rxFormatToman($subbuyuser['SUM(price_product)'] ?? 0);
            $rxFmtSuminvoicehours = \Faoxima\Core\Support\Formatting::rxFormatToman($suminvoicehours);
            $rxFmtSuminvoicemonth = \Faoxima\Core\Support\Formatting::rxFormatToman($suminvoicemonth);
            $textinfouser = "👀 اطلاعات کاربر:

🔗 اطلاعات کاربری کاربر

⭕️ وضعیت کاربر : {$user['User_Status']}
⭕️ نام کاربری کاربر : @{$user['username']}
⭕️ آیدی عددی کاربر :  <a href = \"tg://user?id=$id_user\">$id_user</a>
⭕️ کد معرف کاربر : {$user['codeInvitation']}
⭕️ زمان عضویت کاربر : $userjoin
⭕️ آخرین زمان  استفاده کاربر از ربات : $lastmessage
⭕️ محدودیت اکانت تست :  {$user['limit_usertest']}
⭕️ وضعیت تایید قانون : $roll_Status
⭕️ شماره موبایل : <code>{$user['number']}</code>
⭕️ نوع کاربری : {$user['agent']}
⭕️ تعداد زیرمجموعه کاربر : {$user['affiliatescount']}
⭕  معرف کاربر : {$user['affiliates']}
⭕  وضعیت احراز هویت: $userverify
⭕  نمایش شماره کارت :‌$showcart
⭕ امتیاز کاربر : {$user['score']}
⭕️  مجموع حجم خریداری شده فعال ( برای آمار دقیق حجم باید کرون روشن باشد): {$sumvolume['SUM(Volume)']}
$text_expie_agent

💎 گزارشات مالی

🔰 موجودی کاربر : {$rxFmtUserBalance}
🔰 تعداد خرید کل کاربر : {$dayListSell['COUNT(*)']}
🔰️ مبلغ کل پرداختی  :  {$rxFmtBalanceAllSum}
🔰 جمع کل خرید : {$rxFmtSubbuyuserSum}
🔰 درصد تخفیف کاربر : {$user['pricediscount']}
🔰 تعداد فروش یک ساعت گذشته : $listhours عدد
🔰 مجموع فروش یک ساعت گذشته : {$rxFmtSuminvoicehours} تومان
🔰 تعداد فروش یک ماه گذشته : $listmonth عدد
🔰 مجموع فروش یک ماه گذشته : {$rxFmtSuminvoicemonth} تومان

";
            if ($rxCardpayNotice !== null) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $rxCardpayNotice,
                    'show_alert' => false,
                    'cache_time' => 0,
                ]);
                Editmessagetext($from_id, $message_id, $textinfouser, $keyboardmanage);
            } elseif (is_string($datain) && isset($datain[0]) && $datain[0] == "u") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "اطلاعات بروزرسانی گردید",
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                Editmessagetext($from_id, $message_id, $textinfouser, $keyboardmanage);
            } elseif (is_string($datain) && strpos($datain, 'manageuser_') === 0) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
                sendmessage($from_id, $textinfouser, $keyboardmanage, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textinfouser, $keyboardmanage, 'HTML');
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }

        return true;
    }
}
