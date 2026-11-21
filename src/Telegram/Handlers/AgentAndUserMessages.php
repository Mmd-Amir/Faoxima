<?php

namespace Faoxima\Telegram\Handlers;

use PDOException;
use RuntimeException;

/**
 * Handler stage. Inherited by value: $admin_ids, $backuser, $caption, $datain, $datatextbot, $first_name, $from_id, $keyboard, $keyboardagent, $message_id, $pdo, $photo, $photoid, $setting, $telegramRoute, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $Response, $adminrulecheck, $status, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AgentAndUserMessages
{
    public static function handle(
        &$Response,
        $admin_ids,
        &$adminrulecheck,
        $backuser,
        $caption,
        $datain,
        $datatextbot,
        $first_name,
        $from_id,
        $keyboard,
        $keyboardagent,
        $message_id,
        $pdo,
        $photo,
        $photoid,
        $setting,
        &$status,
        &$stmt,
        $telegramRoute,
        &$text,
        $textbotlang,
        $update,
        $user,
        $username
    ) {
        switch ($telegramRoute) {
        case 'service_search':
            sendmessage($from_id, $textbotlang['users']['search']['usernamgeget'], $backuser, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getuseragnetservice', $from_id);
            break;
        case 'user_response':
            \Faoxima\Telegram\Navigation\Steps::step('getmessageAsuser', $from_id);
            sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backuser, 'HTML');
            break;
        case 'user_message':
            if (!isset($update['message']) && empty($text)) { return; }
            sendmessage($from_id, $textbotlang['users']['support']['sendmessageadmin'], $keyboard, 'HTML');
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
                    ],
                ]
            ]);
            foreach ($admin_ids as $id_admin) {
                $adminrulecheck = \Faoxima\Core\Database\Db::select("admin", "*", "id_admin", $id_admin, "select");
                if ($adminrulecheck['rule'] == "Seller")
                    continue;
                if ($text) {
                    $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['usermessage'], $from_id, $username, $caption . $text);
                    sendmessage($id_admin, $textsendadmin, $Response, 'HTML');
                }
                if ($photo) {
                    $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['userresponse'], $from_id, $username, $caption);
                    telegram('sendphoto', [
                        'chat_id' => $id_admin,
                        'photo' => $photoid,
                        'reply_markup' => $Response,
                        'caption' => $textsendadmin,
                        'parse_mode' => "HTML",
                    ]);
                }
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 'agent_panel':
            if ($setting['inlinebtnmain'] == "oninline" || (method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_richSectionActive') && \Faoxima\Telegram\Presentation\Sections::rx_richSectionActive('agent'))) {
                Editmessagetext($from_id, $message_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
            } else {
                sendmessage($from_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
            }
            break;
        case 'agent_name_prompt':
            sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
            \Faoxima\Telegram\Navigation\Steps::step('selectusernamecustom', $from_id);
            break;
        case 'agent_name':
            if (!isset($update['message']) && empty($text)) { return; }
            $text = str_replace('_', '-', $text);
            if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
                sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
                return;
            }
            sendmessage($from_id, $textbotlang['Admin']['agent']['submitusername'], $keyboardagent, 'html');
            \Faoxima\Core\Database\Db::update("user", "namecustom", $text, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 'agent_start':
            if ($user['Balance'] < $setting['agentreqprice']) {
                $priceagent = number_format($setting['agentreqprice']);
                sendmessage($from_id, sprintf($textbotlang['users']['agenttext']['insufficientbalanceagent'], $priceagent), $backuser, 'HTML');
                return;
            }
            $existingAgentRequest = \Faoxima\Core\Database\Db::select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
            if ($existingAgentRequest) {
                // فقط درخواستی که هنوز در حال بررسی (waiting) است مانع ثبت درخواست جدید می‌شود.
                if ($existingAgentRequest['status'] == "waiting") {
                    sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], null, 'html');
                    return;
                }
                // اگر درخواست قبلی رد شده بود، رکورد قدیمی پاک می‌شود تا کاربر بتواند مجدداً درخواست دهد.
                if ($existingAgentRequest['status'] == "reject") {
                    $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id AND status = 'reject'");
                    $stmtDelOld->execute([':id' => $from_id]);
                }
            }
            if ($user['agent'] != "f") {
                sendmessage($from_id, $textbotlang['users']['agenttext']['isagent'], null, 'html');
                return;
            }
            if ($datain == "requestagent") {
                Editmessagetext($from_id, $message_id, $datatextbot['text_request_agent_dec'], $backuser);
            } else {
                sendmessage($from_id, $datatextbot['text_request_agent_dec'], $backuser, 'html');
            }
            \Faoxima\Telegram\Navigation\Steps::step("getagentrequest", $from_id);
            break;
        case 'agent_request':
            if (!isset($update['message']) && empty($text)) { return; }
            // اطمینان از نبود درخواست قدیمیِ رد/تاییدشده تا INSERT جدید با خطای کلید تکراری مواجه نشود.
            $oldReq = \Faoxima\Core\Database\Db::select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
            if ($oldReq && $oldReq['status'] == "waiting") {
                // اگر همزمان درخواست در حال بررسی ثبت شده، از ثبت دوباره جلوگیری می‌کنیم.
                sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], $keyboard, 'html');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            if ($oldReq) {
                $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id");
                $stmtDelOld->execute([':id' => $from_id]);
            }
            $balancelow = $user['Balance'] - $setting['agentreqprice'];
            \Faoxima\Core\Database\Db::update("user", "Balance", $balancelow, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['agenttext']['endrequest'], $keyboard, 'html');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $stmt = $pdo->prepare("INSERT INTO Requestagent (id, username, time, Description, status, type) VALUES (:id, :username, :time, :description, :status, :type)");
            $status = "waiting";
            $type = "None";
            $current_time = time();
            $description = $text;
            $requestAgentInserted = false;
            try {
                $stmt->execute([
                    ':id' => $from_id,
                    ':username' => $username,
                    ':time' => $current_time,
                    ':description' => $description,
                    ':status' => $status,
                    ':type' => $type,
                ]);
                $requestAgentInserted = true;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
                    $tableConverted = \Faoxima\Core\Database\Db::ensureTableUtf8mb4('Requestagent');
                    if ($tableConverted) {
                        try {
                            $stmt->execute([
                                ':id' => $from_id,
                                ':username' => $username,
                                ':time' => $current_time,
                                ':description' => $description,
                                ':status' => $status,
                                ':type' => $type,
                            ]);
                            $requestAgentInserted = true;
                        } catch (PDOException $retryException) {
                            error_log('Retry after charset conversion failed: ' . $retryException->getMessage());
                        }
                    }

                    if (!$requestAgentInserted) {
                        $sanitisedDescription = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $description);
                        if ($sanitisedDescription !== $description) {
                            $stmt->execute([
                                ':id' => $from_id,
                                ':username' => $username,
                                ':time' => $current_time,
                                ':description' => $sanitisedDescription,
                                ':status' => $status,
                                ':type' => $type,
                            ]);
                            $requestAgentInserted = true;
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    throw $e;
                }
            }

            if (!$requestAgentInserted) {
                throw new RuntimeException('Failed to persist agent request description.');
            }
            $textrequestagent = sprintf($textbotlang['users']['agenttext']['agent-request'], $from_id, $username, $first_name, $text);
            $keyboardmanage = json_encode([
                'inline_keyboard' => [
                    [['text' => $textbotlang['users']['agenttext']['acceptrequest'], 'callback_data' => "addagentrequest_" . $from_id], ['text' => $textbotlang['users']['agenttext']['rejectrequest'], 'callback_data' => "rejectrequesta_" . $from_id]],
                    [
                        ['text' => $textbotlang['users']['SendMessage'], 'callback_data' => 'Response_' . $from_id],
                    ],
                ]
            ]);
            foreach ($admin_ids as $admin) {
                sendmessage($admin, $textrequestagent, $keyboardmanage, 'HTML');
            }
            break;
        }

        return true;
    }
}
