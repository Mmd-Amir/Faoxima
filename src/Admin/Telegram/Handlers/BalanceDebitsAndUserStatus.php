<?php

namespace Faoxima\Admin\Telegram\Handlers;

use RuntimeException;
use Throwable;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $connect, $dataget, $from_id, $keyboard, $keyboardadmin, $message_id, $otherservice, $paymentreports, $pdo, $setting, $text, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $Response, $invoice, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BalanceDebitsAndUserStatus
{
    public static function handle(
        &$Response,
        $adminAction,
        $adminExecution,
        $backadmin,
        $connect,
        $dataget,
        $from_id,
        &$invoice,
        $keyboard,
        $keyboardadmin,
        $message_id,
        $otherservice,
        $paymentreports,
        $pdo,
        &$randomString,
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
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $iduser, "id", $from_id);
            telegram('sendmessage', [
                'chat_id' => $from_id,
                'text' => $textbotlang['Admin']['ManageUser']['lowbalanceuserdec'],
                'reply_markup' => $backadmin,
                'parse_mode' => "HTML",
                'reply_to_message_id' => $message_id,
            ]);
            \Faoxima\Telegram\Navigation\Steps::step('addbalanceuser', $from_id);
            break;
        case 2:
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
            $Payment_Method = "low balance by admin";
            $invoice = null;
            $rxLowBalOk = false;
            $rxLowBalReason = '';
            try {
                $pdo->beginTransaction();
                $rxLowBalClaim = $pdo->prepare("UPDATE user SET step = 'home' WHERE id = :admin AND step = 'addbalanceuser'");
                $rxLowBalClaim->execute([':admin' => $from_id]);
                if ($rxLowBalClaim->rowCount() < 1) {
                    $pdo->rollBack();
                    return;
                }
                $rxLowBalCharge = \Faoxima\Wallet\Balance\Balances::charge($pdo, $user['Processing_value'], (int) $text, 0);
                if (empty($rxLowBalCharge['ok'])) {
                    $rxLowBalReason = (string) ($rxLowBalCharge['reason'] ?? '');
                    throw new RuntimeException('balance_atomic_charge failed: ' . $rxLowBalReason);
                }
                $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice]);
                if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                    \Faoxima\Wallet\Ledger\Ledger::record($pdo, $user['Processing_value'], 'debit', $text, 'admin_debit', 'کاهش موجودی توسط ادمین', $randomString);
                }
                $pdo->commit();
                $rxLowBalOk = true;
            } catch (Throwable $rxLowBalErr) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin_low_balance] ' . $rxLowBalErr->getMessage());
                if (function_exists('rx_log_event')) {
                    rx_log_event('ADMIN_LOW_BALANCE_FAILED', $rxLowBalErr->getMessage(), [
                        'admin_id' => $from_id,
                        'id_user' => $user['Processing_value'],
                        'amount' => $text,
                    ]);
                }
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            if (!$rxLowBalOk) {
                $rxLowBalMsg = $rxLowBalReason === 'insufficient-or-stale'
                    ? "❌ موجودی کاربر برای کسر این مبلغ کافی نیست."
                    : "❌ کسر موجودی انجام نشد. دوباره تلاش کنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxLowBalMsg, $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['lowbalanced'], $keyboardadmin, 'html');
            $lowbalanceuser = number_format($text, 0);
            $textkam = "❌ کاربر عزیز مبلغ $lowbalanceuser تومان از  موجودی کیف پول تان کسر گردید.";
            sendmessage($user['Processing_value'], $textkam, null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $Balance_user_afters = number_format(\Faoxima\Core\Database\Db::select("user", "*", "id", $user['Processing_value'], "select", ['cache' => false])['Balance']);
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
        case 3:
            $iduser = $dataget[1];
            $userdata = \Faoxima\Core\Database\Db::select("user", "*", "id", $iduser, "select");
            if ($userdata['User_Status'] == "block") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockedUser'], null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "", "id", $from_id);
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "تایید", 'callback_data' => 'acceptblock_' . $iduser],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "در صورت تایید روی دکمه تایید کلیک کنید", $Response, 'HTML');
            break;
        case 4:
            $iduser = $dataget[1];
            $_blockfake_chat_id = $update['callback_query']['message']['chat']['id'] ?? $from_id;
            $_blockfake_thread_id = (int) ($update['callback_query']['message']['message_thread_id'] ?? 0);
            $userdata = \Faoxima\Core\Database\Db::select("user", "*", "id", $iduser, "select");
            if ($userdata['User_Status'] == "block") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockedUser'], null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $_blockfake_chat_id . ':' . $message_id . ':' . $_blockfake_thread_id, "id", $from_id);
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "تایید", 'callback_data' => 'acceptblock_' . $iduser],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "در صورت تایید روی دکمه تایید کلیک کنید", $Response, 'HTML');
            break;
        case 5:
            \Faoxima\Core\Database\Db::update("user", "description_blocking", $text, "id", $user['Processing_value']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['DescriptionBlock'], $keyboardadmin, 'HTML');
            $_blockfake_origin = explode(':', (string) ($user['Processing_value_four'] ?? ''), 3);
            $_blockfake_chat_id = $_blockfake_origin[0] ?? '';
            $_blockfake_message_id = $_blockfake_origin[1] ?? '';
            $_blockfake_thread_id = (int) ($_blockfake_origin[2] ?? 0);
            if ($_blockfake_chat_id !== '' && $_blockfake_message_id !== '') {
                $_blockfake_confirmed_text = "🚫 کاربر مسدود شد.

<blockquote>آیدی عددی کاربر : {$user['Processing_value']}</blockquote>
<blockquote>دلیل مسدودی : $text</blockquote>
<blockquote>👤آیدی عددی ادمین مسدودکننده : $from_id</blockquote>
<blockquote>نام کاربری ادمین مسدودکننده : @$username</blockquote>";
                Editmessagetext($_blockfake_chat_id, $_blockfake_message_id, $_blockfake_confirmed_text, null, 'HTML', $_blockfake_thread_id > 0 ? $_blockfake_thread_id : null);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "", "id", $from_id);
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 6:

            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $iduser, "id", $from_id);
            \Faoxima\Admin\Users\UserStatus::change($iduser, 'block', 'update');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockUser'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('adddecriptionblock', $from_id);
            $textblok = "کاربر با آیدی عددی
$iduser  در ربات مسدود گردید
<blockquote>ادمین مسدود کننده : $from_id</blockquote>";
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
                    ],
                ]
            ]);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $textblok,
                    'parse_mode' => "HTML",
                    'reply_markup' => $Response
                ]);
            }
            break;
        case 7:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "verify", "1", "id", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ کاربر با موفقیت احراز گردید.", null, 'HTML');
            sendmessage($iduser, "💎 کاربر گرامی حساب کاربری شما توسط ادمین با موفقیت احراز هویت گردید و هم اکنون می توانیدخرید خود را انجام دهید", $keyboard, 'HTML');
            break;
        case 8:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "verify", "0", "id", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ کاربر با موفقیت از حالت احراز خارج گردید.", null, 'HTML');
            break;
        case 9:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "card_verify_bypass", "1", "id", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ احراز کارت برای این کاربر حذف شد. کاربر بدون تایید کارت پرداخت می‌کند.", null, 'HTML');
            break;
        case 10:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "card_verify_bypass", "0", "id", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ احراز کارت برای این کاربر فعال شد. کاربر باید تایید کارت را طی کند.", null, 'HTML');
            break;
        case 11:
            $iduser = (int)$dataget[1];
            $allCards = [];
            $_ac_res = mysqli_query($connect, "SELECT id, cardnumber, namecard FROM card_number WHERE is_active = 1 ORDER BY created_at ASC LIMIT 50");
            if ($_ac_res) {
                while ($_ac_row = mysqli_fetch_assoc($_ac_res)) {
                    $allCards[] = $_ac_row;
                }
            }
            if (empty($allCards)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ هیچ کارت فعالی در سیستم ثبت نشده است.", null, 'HTML');
            } else {
                $blockedIds = [];
                $_bl_res = mysqli_query($connect, "SELECT card_id FROM user_card_block WHERE user_id = {$iduser}");
                if ($_bl_res) {
                    while ($_bl_row = mysqli_fetch_assoc($_bl_res)) {
                        $blockedIds[] = (int)$_bl_row['card_id'];
                    }
                }
                $_uck_rows = [];
                foreach ($allCards as $_uc_card) {
                    $_uc_cid = (int)$_uc_card['id'];
                    $_uc_last4 = substr((string)$_uc_card['cardnumber'], -4);
                    $_uc_blocked = in_array($_uc_cid, $blockedIds, true);
                    $_uc_status = $_uc_blocked ? "❌ پنهان" : "✅ نمایان";
                    $_uck_rows[] = [[
                        'text' => "{$_uc_status} | *{$_uc_last4} — {$_uc_card['namecard']}",
                        'callback_data' => "toggleusercard_{$_uc_cid}_{$iduser}",
                    ]];
                }
                $_uck_rows[] = [['text' => "🔙 بازگشت", 'callback_data' => "manageuser_{$iduser}"]];
                $ucMenu = json_encode(['inline_keyboard' => $_uck_rows], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "💳 مدیریت کارت‌های کاربر #{$iduser}\n\nروی هر کارت کلیک کنید تا وضعیت نمایش آن برای این کاربر تغییر کند:", $ucMenu, 'HTML');
            }
            break;
        }

        return true;
    }
}
