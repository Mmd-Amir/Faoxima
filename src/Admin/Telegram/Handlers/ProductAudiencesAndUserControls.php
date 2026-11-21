<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $dataget, $from_id, $json_list_marzban_panel, $keyboardadmin, $keyboardtimereset, $pdo, $rxGiftBatch, $rxGiftResume, $shopkeyboard, $textbotlang, $update, $user.
 * Inherited or exported by reference: $data, $grp, $keyboardagent, $message_id, $panel, $product, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProductAudiencesAndUserControls
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        &$data,
        $dataget,
        $from_id,
        &$grp,
        $json_list_marzban_panel,
        $keyboardadmin,
        &$keyboardagent,
        $keyboardtimereset,
        &$message_id,
        &$panel,
        $pdo,
        &$product,
        $rxGiftBatch,
        $rxGiftResume,
        $shopkeyboard,
        &$stmt,
        &$text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "نوع کاربری جدید را ارسال کنید :
نوع کاربری ها :f , n , n2", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('change_type_agent', $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            $grp = method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_resolveAgentGroupFromReplyButton') ? \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, ['f', 'n', 'n2']) : (in_array($text, ['f', 'n', 'n2'], true) ? $text : null);
            if ($grp === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ گروه کاربری نامعتبر می باشد", null, 'HTML');
                return;
            }
            $text = $grp;
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("UPDATE product SET agent = :agents WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':agents', $text);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "نوع ریست حجم را ارسال کنید", $keyboardtimereset, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('change_reset_data', $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("UPDATE product SET data_limit_reset = :data_limit_reset WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':data_limit_reset', $text);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 موقعیت جدید محصول را انتخاب کنید", $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('change_loc_data', $from_id);
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            if ($text == "/all") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نمی توانید محصول تعریف شده را به نام موقعیت /all تغییر دهید.", $shopkeyboard, 'HTML');
                return;
            }
            $panelResolved = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : null;
            $targetLocation = is_array($panelResolved) && !empty($panelResolved['name_panel']) ? $panelResolved['name_panel'] : $text;
            $product = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $user['Processing_value']);
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("UPDATE product SET Location = :Location2 WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':Location2', $targetLocation);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            $stmt = $pdo->prepare("UPDATE invoice SET Service_location = :Service_location WHERE name_product = :name_product AND Service_location = :Location ");
            $stmt->bindParam(':Service_location', $targetLocation);
            $stmt->bindParam(':name_product', $product['name_product']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅موقعیت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "حجم جدید را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('change_val', $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
                return;
            }
            $product = \Faoxima\Core\Database\Db::select("product", "*", "id", $user['Processing_value']);
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
            $stmt = $pdo->prepare("UPDATE product SET Volume_constraint = :Volume_constraint WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':Volume_constraint', $text);
            $stmt->bindParam(':name_product', $product['id']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['volumeUpdated'], $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['NewTime'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('change_time', $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidtime'] ?? '❌ زمان نامعتبر است', $backadmin, 'HTML');
                return;
            }
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("UPDATE product SET Service_time = :Service_time WHERE id = :id_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':Service_time', $text);
            $stmt->bindParam(':id_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['TimeUpdated'], $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['addallbalance'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('add_Balance_all', $from_id);
            break;
        case 12:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "price", $text);
            $keyboardagent = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "همه کاربران", 'callback_data' => 'typebalanceall_all'],
                    ],
                    [
                        ['text' => "کاربران گروه f", 'callback_data' => 'typebalanceall_f'],
                        ['text' => "کاربران گروه n", 'callback_data' => 'typebalanceall_nl'],
                        ['text' => "کاربران گروه n2", 'callback_data' => 'typebalanceall_n2'],
                    ],
                    [
                        ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
                    ]
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 شارژ برای کدام یک از گروه کاربری زیر واریز شود.", $keyboardagent, 'HTML');
            break;
        case 13:
            $typeagent = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "agent", $typeagent);
            $keyboardtypeuser = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "همه کاربران", 'callback_data' => 'typecustomer_all'],
                    ],
                    [
                        ['text' => "کاربرانی که خرید داشتند", 'callback_data' => 'typecustomer_customer'],
                    ],
                    [
                        ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typecustomer_notcustomer'],
                    ],
                    [
                        ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, "📌 چه کاربر شارژ همگانی ارسال شود", $keyboardtypeuser);
            break;
        case 14:
            $typecustomer = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "typecustomer", $typecustomer);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 برای کاربران پیام ارسال شارژ ارسال شود یا خیر؟
بله : 1
خیر : 0", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getmeesagestatus", $from_id);
            break;
        case 15:
            if (!empty($rxGiftResume[1])) {
                $rxGiftBatch = $rxGiftResume[1];
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '⏳ در حال ادامه شارژ همگانی...', 'cache_time' => 0]);
            } else {
                if (!isset($update['message']) && empty($text)) { return; }
                if ($text !== "0" && $text !== "1") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ فقط عدد 1 (ارسال پیام) یا 0 (بدون پیام) را ارسال کنید.", $backadmin, 'HTML');
                    return;
                }
                $userdata = json_decode((string) $user['Processing_value'], true);
                $rxGiftAmount = (is_array($userdata) && ctype_digit((string) ($userdata['price'] ?? ''))) ? (int) $userdata['price'] : 0;
                $rxGiftAgent = is_array($userdata) ? (string) ($userdata['agent'] ?? '') : '';
                if ($rxGiftAgent === 'nl') {
                    $rxGiftAgent = 'n';
                }
                $rxGiftType = is_array($userdata) ? (string) ($userdata['typecustomer'] ?? '') : '';
                if ($rxGiftAmount <= 0 || $rxGiftAmount > 100000000
                    || !in_array($rxGiftAgent, ['all', 'f', 'n', 'n2'], true)
                    || !in_array($rxGiftType, ['all', 'customer', 'notcustomer'], true)) {
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ اطلاعات شارژ همگانی نامعتبر است (مبلغ باید بین 1 تا 100,000,000 باشد). عملیات را از ابتدا شروع کنید.", $keyboardadmin, 'HTML');
                    return;
                }
                $rxGiftClaim = $pdo->prepare("UPDATE user SET step = 'home' WHERE id = :admin AND step = 'getmeesagestatus'");
                $rxGiftClaim->execute([':admin' => $from_id]);
                if ($rxGiftClaim->rowCount() < 1) {
                    return;
                }
                $rxGiftWhere = [];
                $rxGiftParams = [];
                if ($rxGiftAgent !== 'all') {
                    $rxGiftWhere[] = "u.agent = ?";
                    $rxGiftParams[] = $rxGiftAgent;
                }
                if ($rxGiftType === 'customer') {
                    $rxGiftWhere[] = "EXISTS (SELECT 1 FROM invoice i WHERE i.id_user = u.id)";
                } elseif ($rxGiftType === 'notcustomer') {
                    $rxGiftWhere[] = "NOT EXISTS (SELECT 1 FROM invoice i WHERE i.id_user = u.id)";
                }
                $stmt = $pdo->prepare("SELECT u.id FROM user u" . (empty($rxGiftWhere) ? "" : " WHERE " . implode(" AND ", $rxGiftWhere)) . " ORDER BY u.id");
                $stmt->execute($rxGiftParams);
                $rxGiftIds = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                if (empty($rxGiftIds)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ هیچ کاربری با شرایط انتخاب‌شده پیدا نشد؛ شارژی انجام نشد.", $keyboardadmin, 'HTML');
                    return;
                }
                $rxGiftBatch = bin2hex(random_bytes(8));
                $rxGiftState = [
                    'batch' => $rxGiftBatch,
                    'admin' => (string) $from_id,
                    'amount' => $rxGiftAmount,
                    'agent' => $rxGiftAgent,
                    'typecustomer' => $rxGiftType,
                    'notify' => $text === "1",
                    'ids' => $rxGiftIds,
                    'pos' => 0,
                    'credited' => 0,
                    'already' => 0,
                    'missing' => 0,
                    'failed' => [],
                    'status' => 'pending',
                    'notified' => false,
                    'started_at' => time(),
                ];
                if (!\Faoxima\Wallet\Gifts\Gifts::save(\Faoxima\Wallet\Gifts\Gifts::statePath($rxGiftBatch), $rxGiftState)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ذخیره وضعیت شارژ همگانی ممکن نشد؛ هیچ شارژی انجام نشد.", $keyboardadmin, 'HTML');
                    return;
                }
                if (function_exists('rx_log_event')) {
                    rx_log_event('BULK_GIFT_START', 'bulk gift started', ['batch' => $rxGiftBatch, 'admin' => $from_id, 'amount' => $rxGiftAmount, 'users' => count($rxGiftIds), 'agent' => $rxGiftAgent, 'type' => $rxGiftType]);
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⏳ شارژ همگانی " . \Faoxima\Core\Support\Formatting::rxFormatToman($rxGiftAmount) . " تومان برای " . number_format(count($rxGiftIds)) . " کاربر آغاز شد.\n🆔 <code>{$rxGiftBatch}</code>", json_encode(['inline_keyboard' => [[['text' => "▶️ ادامه شارژ همگانی", 'callback_data' => 'bulkgift_resume_' . $rxGiftBatch]]]]), 'HTML');
            }
            $rxGiftState = \Faoxima\Wallet\Gifts\Gifts::run($pdo, $rxGiftBatch);
            $rxGiftStatus = (string) ($rxGiftState['status'] ?? 'missing');
            if ($rxGiftStatus === 'missing') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ عملیات شارژ همگانی پیدا نشد.", $keyboardadmin, 'HTML');
                return;
            }
            $rxGiftTotal = count((array) ($rxGiftState['ids'] ?? []));
            $rxGiftFailed = (array) ($rxGiftState['failed'] ?? []);
            $rxGiftSummary = "📊 گزارش شارژ همگانی <code>{$rxGiftBatch}</code>\n\n"
                . "👥 کل کاربران: " . number_format($rxGiftTotal) . "\n"
                . "✅ شارژ شده: " . number_format((int) ($rxGiftState['credited'] ?? 0) + (int) ($rxGiftState['already'] ?? 0)) . "\n"
                . "⏭ کاربر حذف‌شده: " . number_format((int) ($rxGiftState['missing'] ?? 0)) . "\n"
                . "❌ ناموفق: " . number_format(count($rxGiftFailed)) . "\n"
                . "⏳ پردازش‌نشده: " . number_format(max(0, $rxGiftTotal - (int) ($rxGiftState['pos'] ?? 0)));
            if (!empty($rxGiftFailed)) {
                $rxGiftSummary .= "\n\n❌ شناسه‌های ناموفق: <code>" . htmlspecialchars(implode(', ', array_slice(array_keys($rxGiftFailed), 0, 30))) . "</code>";
            }
            if ($rxGiftStatus !== 'done') {
                $rxGiftSummary .= "\n\n" . ($rxGiftStatus === 'busy' ? "⏳ این عملیات هم‌اکنون در حال اجراست." : "⚠️ عملیات کامل نشد؛ برای ادامه از دکمه زیر استفاده کنید.");
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxGiftSummary, json_encode(['inline_keyboard' => [[['text' => "▶️ ادامه شارژ همگانی", 'callback_data' => 'bulkgift_resume_' . $rxGiftBatch]]]]), 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['AddBalanceUsers'] . "\n\n" . $rxGiftSummary, $keyboardadmin, 'HTML');
            if (!empty($rxGiftState['notify']) && empty($rxGiftState['notified'])) {
                $rxGiftState['notified'] = true;
                \Faoxima\Wallet\Gifts\Gifts::save(\Faoxima\Wallet\Gifts\Gifts::statePath($rxGiftBatch), $rxGiftState);
                $Balance_user = array_map(function ($id) { return ['id' => $id]; }, \Faoxima\Wallet\Gifts\Gifts::creditedIds($pdo, $rxGiftBatch));
                if (!empty($Balance_user)) {
                    $cancelmessage = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
                            ],
                        ]
                    ]);
                    $textgift = "🎁 کاربر  عزیز مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($rxGiftState['amount']) . " تومان از طرف مدیریت به عنوان هدیه به کیف پول شما واریز گردید.";
                    $message_id = sendmessage($from_id, "✅ عملیات ارسال پیام آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage, "html");
                    $data = json_encode(array(
                        "id_admin" => $from_id,
                        'type' => "sendmessage",
                        "id_message" => $message_id['result']['message_id'],
                        "message" => $textgift,
                        "pingmessage" => "no",
                        "btnmessage" => "start"
                    ));
                    file_put_contents("cronbot/users.json", json_encode($Balance_user));
                    file_put_contents('cronbot/info', $data);
                }
            }
            break;
        }

        return true;
    }
}
