<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $dataget, $from_id, $keyboardadmin, $message_id, $pdo, $text, $textbotlang, $user.
 * Inherited or exported by reference: $result, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BroadcastAudience
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $dataget,
        $from_id,
        $keyboardadmin,
        $message_id,
        $pdo,
        &$result,
        &$stmt,
        $text,
        $textbotlang,
        $user
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
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "typeusermessage", $dataget[1]);
            $listbtn = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "همه کاربران", 'callback_data' => 'typeagent-all'],
                    ],
                    [
                        ['text' => "کاربران گروه f", 'callback_data' => 'typeagent-f'],
                    ],
                    [
                        ['text' => "کاربران گروه n", 'callback_data' => 'typeagent-n'],
                    ],
                    [
                        ['text' => "کاربران گروه n2", 'callback_data' => 'typeagent-n2'],
                    ],
                    [
                        ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeservice-' . $userdata['typeservice']],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "📌 سرویس برای چه دسته از کاربران اعمال شود؟", $listbtn);
            break;
        case 2:
            $type = $dataget[1];
            $userdata = json_decode($user['Processing_value'], true);
            if (!isset($userdata['typeservice'])) {
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "agent", $type);
            if ($userdata['typeusermessage'] == "customer") {
                $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE agent = :agent OR agent = 'all' ORDER BY (position = 0) ASC, position ASC, id ASC");
                $stmt->bindParam(':agent', $type);
                $stmt->execute();
                $list_panel = ['inline_keyboard' => []];
                $list_panel['inline_keyboard'][] = [['text' => "تمامی پنل ها", 'callback_data' => 'locationmessage_all']];
                while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $list_panel['inline_keyboard'][] = [
                        ['text' => $result['name_panel'], 'callback_data' => "locationmessage_{$result['code_panel']}"]
                    ];
                }
                $list_panel['inline_keyboard'][] = [['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],];
                Editmessagetext($from_id, $message_id, "📌 پیام برای کدام کاربران موجود در پنل های زیر ارسال شود.", json_encode($list_panel));
                return;
            }
            if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
                $listbtn = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                            ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                        ],
                        [
                            ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],
                        ],
                    ]
                ]);
                Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
                return;
            }
            if ($userdata['typeservice'] == "xdaynotmessage") {
                \Faoxima\Telegram\Navigation\Steps::step("gettextday", $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step("gettextSystemMessage", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
            break;
        case 3:
            $duration = $dataget[1];
            $userdata = json_decode($user['Processing_value'], true);
            if (!isset($userdata['typeservice'])) {
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "pinduration", $duration);
            $listbtn = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "دکمه استارت", 'callback_data' => 'btntypemessage-start'],
                        ['text' => "دکمه آموزش", 'callback_data' => 'btntypemessage-helpbtn'],
                    ],
                    [
                        ['text' => "دکمه خرید", 'callback_data' => 'btntypemessage-buy'],
                        ['text' => "دکمه اکانت تست", 'callback_data' => 'btntypemessage-usertestbtn'],
                    ],
                    [
                        ['text' => "دکمه زیرمجموعه گیری ", 'callback_data' => 'btntypemessage-affiliatesbtn'],
                        ['text' => "شارژ حساب کاربری", 'callback_data' => 'btntypemessage-addbalance'],
                    ],
                    [
                        ['text' => "ارسال بدون دکمه", 'callback_data' => 'btntypemessage-none'],
                    ],
                    [
                        ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "📌 اگر می خواهید زیر پیام دکمه ای نمایش داده شود از لیست زیر گزینه ای را انتخاب کنید در غیر اینصورت دکمه  ارسال بدون دکمه را بزنید", $listbtn);
            break;
        case 4:
            $typeoanel = $dataget[1];
            $userdata = json_decode($user['Processing_value'], true);
            if (!isset($userdata['typeservice'])) {
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "selectpanel", $typeoanel);
            if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
                $listbtn = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                            ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                        ],
                        [
                            ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
                        ],
                    ]
                ]);
                Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
                return;
            }
            if ($userdata['typeservice'] == "xdaynotmessage") {
                \Faoxima\Telegram\Navigation\Steps::step("gettextday", $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step("gettextSystemMessage", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
            break;
        case 5:
            $type = $dataget[1];
            $userdata = json_decode($user['Processing_value'], true);
            if (!isset($userdata['typeservice'])) {
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "typepinmessage", $type);
            if ($type == "yes") {
                $listpinduration = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => "پین ۲۴ ساعته", 'callback_data' => 'pinduration-24'],
                            ['text' => "پین ۴۸ ساعته", 'callback_data' => 'pinduration-48'],
                        ],
                        [
                            ['text' => "بدون تایم (نامحدود)", 'callback_data' => 'pinduration-none'],
                        ],
                        [
                            ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
                        ],
                    ]
                ]);
                Editmessagetext($from_id, $message_id, "📌 مدت زمان پین ماندن پیام را انتخاب کنید. پس از این مدت پیام به‌صورت خودکار از پین خارج می‌شود.", $listpinduration);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "pinduration", "none");
            $listbtn = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "دکمه استارت", 'callback_data' => 'btntypemessage-start'],
                        ['text' => "دکمه آموزش", 'callback_data' => 'btntypemessage-helpbtn'],
                    ],
                    [
                        ['text' => "دکمه خرید", 'callback_data' => 'btntypemessage-buy'],
                        ['text' => "دکمه اکانت تست", 'callback_data' => 'btntypemessage-usertestbtn'],
                    ],
                    [
                        ['text' => "دکمه زیرمجموعه گیری ", 'callback_data' => 'btntypemessage-affiliatesbtn'],
                        ['text' => "شارژ حساب کاربری", 'callback_data' => 'btntypemessage-addbalance'],
                    ],
                    [
                        ['text' => "ارسال بدون دکمه", 'callback_data' => 'btntypemessage-none'],
                    ],
                    [
                        ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
                    ],
                ]
            ]);
            if ($userdata['typeservice'] == "forwardmessage") {
                \Faoxima\Telegram\Navigation\Steps::step("gettextSystemMessage", $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
                return;
            }
            Editmessagetext($from_id, $message_id, "📌 اگر می خواهید زیر پیام دکمه ای نمایش داده شود از لیست زیر گزینه ای را انتخاب کنید در غیر اینصورت دکمه  ارسال بدون دکمه را بزنید", $listbtn);
            break;
        case 6:
            deletemessage($from_id, $message_id);
            $type = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "btntypemessage", $type);
            $userdata = json_decode($user['Processing_value'], true);
            if (!isset($userdata['typeservice'])) {
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
                return;
            }
            if ($userdata['typeservice'] == "xdaynotmessage") {
                \Faoxima\Telegram\Navigation\Steps::step("gettextday", $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step("gettextSystemMessage", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
            break;
        case 7:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            if (!isset($userdata['typeservice'])) {
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "daynoyuse", $text);
            \Faoxima\Telegram\Navigation\Steps::step("gettextSystemMessage", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
            break;
        }

        return true;
    }
}
