<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $from_id, $keyboardadmin, $pdo, $textday, $user, $userslist.
 * Inherited or exported by reference: $data, $message_id, $panel, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BroadcastQueue
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        &$data,
        $from_id,
        $keyboardadmin,
        &$message_id,
        &$panel,
        $pdo,
        &$stmt,
        &$text,
        $textday,
        $user,
        $userslist
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $userdata = json_decode($user['Processing_value'], true);
            if (!isset($userdata['typeservice'])) {
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
                return;
            }
            if ($userdata['typeservice'] == "forwardmessage") {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "message", $message_id);
            } elseif ($userdata['typeservice'] == "xdaynotmessage") {
                if ($text) {
                    \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "message", $text);
                } else {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌  در بخش کاربرانی که به تعداد روز تعیین شده استفاده نکردند فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
                    return;
                }
            } elseif ($userdata['typeservice'] == "sendmessage") {
                if ($text) {
                    \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "message", $text);
                } else {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌  در بخش ارسال همگانی فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
                    return;
                }
            }
            $typesend = [
                "xdaynotmessage" => "کاربرانی که به تعداد روز تعیین شده استفاده نکردند",
                "sendmessage" => "ارسال همگانی",
                "forwardmessage" => "فوروارد همگانی",
                "unpinmessage" => "لغو پیام پین شده"
            ][$userdata['typeservice']];
            $typeservice = [
                "all" => "ارسال به همه کاربران",
                "customer" => "مشتریان",
                "nonecustomer" => "کسانی که خرید نداشتند",
            ][$userdata['typeusermessage']];
            if ($userdata['typeservice'] == "xdaynotmessage") {
                $textday = "تعداد روزی که کاربر پیام نداده است : {$userdata['daynoyuse']}";
            } else {
                $textday = "";
            }
            $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend
🎛 نوع سرویس : $typeservice
🗂 نوع کاربری : {$userdata['agent']}
$textday
";
            $startaction = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "تایید و شروع عملیات", 'callback_data' => 'startaction'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textconfirm, $startaction, 'HTML');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "با تایید گزینه بالا فرآیند ارسال شروع خواهد شد", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 2:
            $userdata = json_decode($user['Processing_value'], true);
            if (!isset($userdata['typeservice'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
                return;
            }
            $agent = $userdata['agent'];
            $typeservice = $userdata['typeservice'];
            $typeusermessage = $userdata['typeusermessage'];
            $text = $userdata['message'];
            $cancelmessage = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
                    ],
                ]
            ]);

            @ini_set('memory_limit', '1G');
            @set_time_limit(300);


            if ($typeservice == "unpinmessage") {
                $userlist = json_encode(\Faoxima\Core\Database\Db::select("user", "id", null, null, "fetchAll"));
                $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
                $dataunpin = json_encode(array(
                    "id_admin" => $from_id,
                    'type' => "unpinmessage",
                    "id_message" => $message_id['result']['message_id']
                ));
                \Faoxima\Admin\Telegram\Handlers\BroadcastQueueHelpers::nm_writeBroadcastQueueFromJson($userlist); $userlist = null;
                file_put_contents('cronbot/info', $dataunpin);
            } elseif ($typeservice == "sendmessage") {
                $rxStartLock = @fopen('cronbot/startaction.lock', 'c');
                if ($rxStartLock && !@flock($rxStartLock, LOCK_EX | LOCK_NB)) {
                    @fclose($rxStartLock);
                    if (!empty($callback_query_id)) {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'text'              => '⏳ عملیات در حال آماده‌سازی است.',
                            'show_alert'        => false,
                            'cache_time'        => 0,
                        ]);
                    }
                    return;
                }
                $broadcastStatus = method_exists(\Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::class, 'nm_getBroadcastStatus') ? \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_getBroadcastStatus() : null;
                if ($broadcastStatus !== null) {
                    if ($rxStartLock) { @flock($rxStartLock, LOCK_UN); @fclose($rxStartLock); }
                    Editmessagetext($from_id, $message_id, \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusText($broadcastStatus), \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusKeyboard($broadcastStatus), 'HTML');
                    return;
                }
                $statusMessageId = $message_id;
                if ($agent == "all") {
                    if ($typeusermessage == "all") {
                        $userslist = json_encode(\Faoxima\Core\Database\Db::select("user", "id", "User_Status", "Active", "fetchAll"));
                    } elseif ($typeusermessage == "customer") {
                        if ($userdata['selectpanel'] == "all") {
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                        } else {
                            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                        }
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    } elseif ($typeusermessage == "nonecustomer") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    }
                } else {
                    if ($typeusermessage == "all") {
                        $userslist = json_encode(\Faoxima\Core\Database\Db::select("user", "id", "agent", $agent, "fetchAll"));
                    } elseif ($typeusermessage == "customer") {
                        if ($userdata['selectpanel'] == "all") {
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                        } else {
                            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE  u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                        }
                        $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    } elseif ($typeusermessage == "nonecustomer") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                        $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    }
                }
                Editmessagetext($from_id, $statusMessageId, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
                $rxBuilt = \Faoxima\Admin\Telegram\Handlers\BroadcastQueueHelpers::nm_writeBroadcastQueueFromJson($userslist); $userslist = null;
                $data = json_encode(array(
                    "id_admin" => $from_id,
                    'type' => "sendmessage",
                    "id_message" => $statusMessageId,
                    "message" => $userdata['message'],
                    "pingmessage" => $userdata['typepinmessage'],
                    "pinduration" => $userdata['pinduration'] ?? 'none',
                    "btnmessage" => $userdata['btntypemessage'],
                    "status" => "queued",
                    "stats" => array(
                        "total" => (int) $rxBuilt,
                        "success" => 0,
                        "blocked" => 0,
                        "deleted" => 0,
                        "failed" => 0,
                        "chat_not_found" => 0,
                        "started_at" => time()
                    )
                ));
                $rxInfoWritten = file_put_contents('cronbot/info', $data);
                if ($rxStartLock) { @flock($rxStartLock, LOCK_UN); @fclose($rxStartLock); }
                $broadcastStatus = ($rxInfoWritten !== false && method_exists(\Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::class, 'nm_getBroadcastStatus')) ? \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_getBroadcastStatus() : null;
                if ($broadcastStatus !== null) {
                    Editmessagetext($from_id, $statusMessageId, \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusText($broadcastStatus), \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusKeyboard($broadcastStatus), 'HTML');
                }
            } elseif ($typeservice == "forwardmessage") {
                if ($agent == "all") {
                    if ($typeusermessage == "all") {
                        $userslist = json_encode(\Faoxima\Core\Database\Db::select("user", "id", "User_Status", "Active", "fetchAll"));
                    } elseif ($typeusermessage == "customer") {
                        if ($userdata['selectpanel'] == "all") {
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                        } else {
                            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                        }
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    } elseif ($typeusermessage == "nonecustomer") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    }
                } else {
                    if ($typeusermessage == "all") {
                        $userslist = json_encode(\Faoxima\Core\Database\Db::select("user", "id", "agent", $agent, "fetchAll"));
                    } elseif ($typeusermessage == "customer") {
                        if ($userdata['selectpanel'] == "all") {
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                        } else {
                            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                        }
                        $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    } elseif ($typeusermessage == "nonecustomer") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                        $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    }
                }
                $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
                $data = json_encode(array(
                    "id_admin" => $from_id,
                    'type' => "forwardmessage",
                    "id_message" => $message_id['result']['message_id'],
                    "message" => $userdata['message'],
                    "pingmessage" => $userdata['typepinmessage'],
                    "pinduration" => $userdata['pinduration'] ?? 'none',
                ));
                $rxBuilt = \Faoxima\Admin\Telegram\Handlers\BroadcastQueueHelpers::nm_writeBroadcastQueueFromJson($userslist); $userslist = null;        file_put_contents('cronbot/info', $data);
            } elseif ($typeservice == "xdaynotmessage") {
                $timedaystamp = intval($userdata['daynoyuse']) * 86400;
                $timenouser = time() - $timedaystamp;
                if ($agent == "all") {
                    $stmt = $pdo->prepare("SELECT id FROM user  WHERE last_message_time < $timenouser");
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } else {
                    if ($typeusermessage == "all") {
                        if ($typeusermessage == "all") {
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time");
                            $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                            $stmt->execute();
                            $userslist = json_encode($stmt->fetchAll());
                        } elseif ($typeusermessage == "customer") {
                            if ($userdata['selectpanel'] == "all") {
                                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                            } else {
                                $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                            }
                            $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                            $stmt->execute();
                            $userslist = json_encode($stmt->fetchAll());
                        } elseif ($typeusermessage == "nonecustomer") {
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                            $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                            $stmt->execute();
                            $userslist = json_encode($stmt->fetchAll());
                        }
                    } elseif ($typeusermessage == "customer") {
                        if ($userdata['selectpanel'] == "all") {
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                        } else {
                            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                            $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                        }
                        $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                        $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    } elseif ($typeusermessage == "nonecustomer") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                        $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                        $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                        $stmt->execute();
                        $userslist = json_encode($stmt->fetchAll());
                    }
                }
                $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
                $data = json_encode(array(
                    "id_admin" => $from_id,
                    'type' => "xdaynotmessage",
                    "id_message" => $message_id['result']['message_id'],
                    "message" => $userdata['message'],
                    "pingmessage" => $userdata['typepinmessage'],
                    "pinduration" => $userdata['pinduration'] ?? 'none',
                    "btnmessage" => $userdata['btntypemessage']
                ));
                $rxBuilt = \Faoxima\Admin\Telegram\Handlers\BroadcastQueueHelpers::nm_writeBroadcastQueueFromJson($userslist); $userslist = null;        file_put_contents('cronbot/info', $data);
            }
            break;
        }

        return true;
    }
}
