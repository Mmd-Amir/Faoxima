<?php

namespace Faoxima\Telegram\Bootstrap;

use PDO;

/**
 * Handler stage. Inherited by value: $callback_query_id, $domainhosts, $first_name, $from_id, $message_id, $pdo, $rxAsGateMutedUntil, $setting, $textbotlang, $username, $valueverify.
 * Inherited or exported by reference: $Response, $admin_ids, $admin_ids_str, $otherreport, $randomString, $stmt, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class User
{
    public static function handle(
        &$Response,
        &$admin_ids,
        &$admin_ids_str,
        $callback_query_id,
        $domainhosts,
        $first_name,
        $from_id,
        $message_id,
        &$otherreport,
        $pdo,
        &$randomString,
        $rxAsGateMutedUntil,
        $setting,
        &$stmt,
        $textbotlang,
        &$user,
        $username,
        $valueverify
    ) {
        $user = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id, "select", ['cache' => false]);
        $isNewUser = !is_array($user);
        $otherreport = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
        if ($isNewUser && $setting['statusnewuser'] == "onnewuser") {
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
                    ],
                ]
            ]);
            $newuser = sprintf($textbotlang['Admin']['ManageUser']['newuser'], $first_name, $username, "<a href = \"tg://user?id=$from_id\">$from_id</a>");
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $newuser,
                    'reply_markup' => $Response,
                    'parse_mode' => "HTML"
                ]);
            }
        }
        $date = time();
        if ($from_id != 0 && $isNewUser) {
            if ($setting['verifystart'] != "onverify") {
                $valueverify = 1;
            } else {
                $valueverify = 0;
            }
            $randomString = bin2hex(random_bytes(6));
            $initialProcessingValue = '0';
            $initialProcessingValueOne = 'none';
            $initialProcessingValueTow = 'none';
            $initialProcessingValueFour = '0';
            $initialRollStatus = '0';
            $stmt = $pdo->prepare("INSERT IGNORE INTO user (id , step,limit_usertest,User_Status,number,Balance,pagenumber,username,agent,message_count,last_message_time,affiliates,affiliatescount,cardpayment,number_username,namecustom,register,verify,codeInvitation,pricediscount,maxbuyagent,joinchannel,score,status_cron,roll_Status,Processing_value,Processing_value_one,Processing_value_tow,Processing_value_four) VALUES (:from_id, 'none',:limit_usertest_all,'Active','none','0','1',:username,'f','0','0','0','0',:showcard,'100','none',:date,:verifycode,:codeInvitation,'0','0','0','0','1',:roll_status,:processing_value,:processing_value_one,:processing_value_tow,:processing_value_four)");
            $stmt->bindParam(':from_id', $from_id);
            $stmt->bindParam(':limit_usertest_all', $setting['limit_usertest_all']);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':showcard', $setting['showcard']);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':verifycode', $valueverify);
            $stmt->bindParam(':codeInvitation', $randomString);
            $stmt->bindParam(':roll_status', $initialRollStatus);
            $stmt->bindParam(':processing_value', $initialProcessingValue);
            $stmt->bindParam(':processing_value_one', $initialProcessingValueOne);
            $stmt->bindParam(':processing_value_tow', $initialProcessingValueTow);
            $stmt->bindParam(':processing_value_four', $initialProcessingValueFour);
            $stmt->execute();
            \Faoxima\Core\Database\SelectCache::clearSelectCache('user');
            $user = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id, "select", ['cache' => false]);
            $isNewUser = !is_array($user);
        }
        if (!is_array($user)) {
            $user = array();
            $user = array(
                'step' => '',
                'Processing_value' => '',
                'User_Status' => '',
                'agent' => '',
                'username' => '',
                'limit_usertest' => '',
                'message_count' => '',
                'affiliates' => '',
                'last_message_time' => '',
                'cardpayment' => '',
                'roll_Status' => '',
                'number_username' => '',
                'number' => '',
                'register' => '',
                'codeInvitation' => '',
                'pricediscount' => '',
                'joinchannel' => '',
                'score' => "",
                'limitchangeloc' => ''
            );
        } else {
            $user['codeInvitation'] = \Faoxima\Services\Referrals\Referrals::ensureUserInvitationCode($from_id, $user['codeInvitation'] ?? null);
        }
        $admin_ids = \Faoxima\Core\Database\Db::select("admin", "id_admin", null, null, "FETCH_COLUMN", ['cache' => false]);
        if (!is_array($admin_ids)) {
            $admin_ids = [];
        }
        $admin_ids_str = array_map('strval', $admin_ids);

        if (\Faoxima\Telegram\Conversation\MiniAppRedirect::forced($setting, $from_id, $admin_ids_str)) {
            $rxSuggestText = method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_textbot_get') ? trim((string) \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('miniapp_suggest_1', '')) : '';
            \Faoxima\Telegram\Conversation\MiniAppRedirect::show(
                $from_id,
                $message_id,
                $user['first_name'] ?? '',
                $domainhosts ?? null,
                $rxSuggestText !== '' ? $rxSuggestText : '📱 برای استفاده از خدمات ما، مینی‌اپ را باز کنید.',
                '/app/',
                'Editmessagetext',
                'sendmessage',
                !empty($callback_query_id)
                    ? static fn () => telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0])
                    : null,
            );
            return;
        }

        if (
            isset($from_id)
            && intval($from_id) !== 0
            && is_array($setting)
            && (string)($setting['antispam_status'] ?? '0') === '1'
            && !in_array((string)$from_id, $admin_ids_str, true)
            && !in_array((string)($user['agent'] ?? 'f'), ['n', 'n2'], true)
        ) {
            try {
                $rxAsGateStmt = $pdo->prepare("SELECT antispam_muted_until FROM user WHERE id = :id LIMIT 1");
                $rxAsGateStmt->bindValue(':id', (string)$from_id, PDO::PARAM_STR);
                $rxAsGateStmt->execute();
                $rxAsGateRow = $rxAsGateStmt->fetch(PDO::FETCH_ASSOC);

                if (is_array($rxAsGateRow)) {
                    $rxAsGateMutedUntil = (int)($rxAsGateRow['antispam_muted_until'] ?? 0);
                } else {
                    $rxAsGateMutedUntil = 0;
                }
            } catch (\Throwable $rxAsGateErr) {

                $rxAsGateMutedUntil = (int)($user['antispam_muted_until'] ?? 0);
            }
            if ($rxAsGateMutedUntil > 0 && time() < $rxAsGateMutedUntil) {

                if (!empty($callback_query_id)) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time' => 0,
                        ]);
                    } catch (\Throwable $rxAsAckErr) {  }
                }
                return;
            }
        }

        return true;
    }
}
