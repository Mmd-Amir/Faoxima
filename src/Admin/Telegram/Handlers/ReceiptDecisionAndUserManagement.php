<?php

namespace Faoxima\Admin\Telegram\Handlers;

use RuntimeException;
use Throwable;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $callback_query_id, $datagetr, $from_id, $keyboardadmin, $message_id, $pdo, $setting, $textbotlang.
 * Inherited or exported by reference: $id_user, $label, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReceiptDecisionAndUserManagement
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $callback_query_id,
        $datagetr,
        $from_id,
        &$id_user,
        $keyboardadmin,
        &$label,
        $message_id,
        $pdo,
        $setting,
        &$stmt,
        $textbotlang
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:

            $id_user = $datagetr[1];
            $request_agent = \Faoxima\Core\Database\Db::select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);

            if (!$request_agent) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "درخواست مورد نظر یافت نشد.",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }

            if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                return;
            }

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected_status");
                $stmt->execute([
                    ':status' => 'reject',
                    ':type' => 'None',
                    ':id' => $id_user,
                    ':expected_status' => 'waiting',
                ]);

                if ($stmt->rowCount() === 0) {
                    $pdo->rollBack();
                    telegram('answerCallbackQuery', array(
                        'callback_query_id' => $callback_query_id,
                        'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                        'show_alert' => true,
                        'cache_time' => 0,
                    ));
                    return;
                }

                $rxAgentRefund = intval($setting['agentreqprice']);
                if ($rxAgentRefund > 0) {
                    $stmtBalance = $pdo->prepare("UPDATE user SET Balance = Balance + :amount WHERE id = :id");
                    $stmtBalance->execute([
                        ':amount' => $rxAgentRefund,
                        ':id' => $id_user,
                    ]);
                    if ($stmtBalance->rowCount() < 1) {
                        throw new RuntimeException('agent request refund affected no user row');
                    }
                    if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                        \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $id_user);
                    }
                    if (!\Faoxima\Wallet\Ledger\Ledger::record($pdo, $id_user, 'credit', $rxAgentRefund, 'refund', 'بازگشت هزینه درخواست نمایندگی', null, 'Requestagent', (string) $id_user)) {
                        throw new RuntimeException('agent request refund ledger failed');
                    }
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            $keyboardreject = json_encode([
                'inline_keyboard' => [
                    [['text' => "✅درخواست رد شده.", 'callback_data' => "reject"]],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ درخواست با موفقیت رد گردید.", null, 'HTML');
            sendmessage($id_user, "❌ کاربر گرامی درخواست نمایندگی شما رد گردید.", null, 'HTML');
            $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
            $textrequestagent .= "\nوضعیت: رد شد.";
            Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "درخواست با موفقیت رد شد.",
                'show_alert' => false,
                'cache_time' => 5,
            ));
            break;
        case 2:

            $id_user = $datagetr[1];
            $request_agent = \Faoxima\Core\Database\Db::select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
            if (!$request_agent) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "درخواست مورد نظر یافت نشد.",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                return;
            }
            $confirmKeyboard = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "✅ بله، تایید کن", 'callback_data' => "cfmacea_" . $id_user],
                        ['text' => "🔙 لغو", 'callback_data' => "cnclagentreq_" . $id_user],
                    ],
                ]
            ], JSON_UNESCAPED_UNICODE);
            $textConfirm = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
            $textConfirm .= "\n\n⚠️ آیا از <b>تایید</b> این درخواست اطمینان دارید؟";
            Editmessagetext($from_id, $message_id, $textConfirm, $confirmKeyboard);
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "برای تایید نهایی روی «بله، تایید کن» بزنید.",
                'show_alert' => false,
                'cache_time' => 0,
            ));
            break;
        case 3:

            $id_user = $datagetr[1];
            $request_agent = \Faoxima\Core\Database\Db::select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
            if (!$request_agent) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "درخواست مورد نظر یافت نشد.",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "این درخواست قبلاً بررسی شده است",
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                return;
            }
            $keyboardmanage = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['agenttext']['acceptrequest'], 'callback_data' => "addagentrequest_" . $id_user],
                        ['text' => $textbotlang['users']['agenttext']['rejectrequest'], 'callback_data' => "rejectrequesta_" . $id_user],
                    ],
                    [
                        ['text' => $textbotlang['users']['SendMessage'], 'callback_data' => 'Response_' . $id_user],
                    ],
                ]
            ], JSON_UNESCAPED_UNICODE);
            $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
            Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardmanage);
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "عملیات لغو شد.",
                'show_alert' => false,
                'cache_time' => 0,
            ));
            break;
        case 4:

            $id_user = $datagetr[1];
            $request_agent = \Faoxima\Core\Database\Db::select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
            if (!$request_agent) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "درخواست مورد نظر یافت نشد.",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                return;
            }
            $defaultAgentType = 'n';
            $agentTypeLabels = [
                'n' => 'نماینده عادی',
                'n2' => 'نماینده پیشرفته',
            ];

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected_status");
                $stmt->execute([
                    ':status' => 'accept',
                    ':type' => $defaultAgentType,
                    ':id' => $id_user,
                    ':expected_status' => 'waiting',
                ]);

                if ($stmt->rowCount() === 0) {
                    $pdo->rollBack();
                    telegram('answerCallbackQuery', array(
                        'callback_query_id' => $callback_query_id,
                        'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                        'show_alert' => true,
                        'cache_time' => 0,
                    ));
                    return;
                }

                $stmtUser = $pdo->prepare("UPDATE user SET agent = :agent, expire = NULL WHERE id = :id");
                $stmtUser->execute([
                    ':agent' => $defaultAgentType,
                    ':id' => $id_user,
                ]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            sendmessage($id_user, "✅ کاربر گرامی با درخواست نمایندگی شما موافقت و شما نماینده شدید.", null, 'HTML');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
            $agentTypeButtons = [];
            foreach ($agentTypeLabels as $typeCode => $label) {
                $buttonText = ($typeCode === $defaultAgentType ? "✅ " : "") . $label;
                $agentTypeButtons[] = [
                    'text' => $buttonText,
                    'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
                ];
            }
            $keyboardreject = json_encode([
                'inline_keyboard' => [
                    [['text' => "✅درخواست تایید شده.", 'callback_data' => "accept"]],
                    $agentTypeButtons,
                    [['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => 'expireset_' . $id_user]],
                    [['text' => "مدیریت کاربر", 'callback_data' => 'manageuser_' . $id_user]]
                ]
            ], JSON_UNESCAPED_UNICODE);
            $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
            $textrequestagent .= "\nوضعیت: تایید شد ({$agentTypeLabels[$defaultAgentType]})";
            $textrequestagent .= "\nبرای تغییر نوع نماینده از دکمه‌های زیر استفاده کنید.";
            Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "درخواست تایید شد و نماینده عادی فعال شد.",
                'show_alert' => false,
                'cache_time' => 5,
            ));
            break;
        }

        return true;
    }
}
