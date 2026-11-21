<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $callback_query_id, $dataget, $from_id, $keyboardadmin, $message_id, $pdo, $rxResumeMatch, $textbotlang, $user.
 * Inherited or exported by reference: $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderTypeConfirmation
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $callback_query_id,
        $dataget,
        $from_id,
        $keyboardadmin,
        $message_id,
        $pdo,
        $rxResumeMatch,
        &$stmt,
        $textbotlang,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $userdata = json_decode($user['Processing_value'], true);
            $codePanel = $userdata['code_panel'];
            $namePanel = $userdata['name_panel'];
            $finalType = $userdata['new_type'];
            $versionPanel = '0';
            $urlPanel = isset($userdata['url_panel']) ? $userdata['url_panel'] : 'null';
            $usernamePanel = isset($userdata['username']) ? $userdata['username'] : 'null';
            $passwordPanel = isset($userdata['password']) ? $userdata['password'] : 'null';
            $apiKey = isset($userdata['api_key']) ? $userdata['api_key'] : null;
            $xuiApiMode = isset($userdata['xui_api_mode']) ? $userdata['xui_api_mode'] : 'legacy';
            $xuiApiToken = isset($userdata['xui_api_token']) ? $userdata['xui_api_token'] : null;
            $remnaApiToken = isset($userdata['remna_api_token']) ? $userdata['remna_api_token'] : null;
            $pasarguardAuthMode = (isset($userdata['pasarguard_auth_mode']) && $userdata['pasarguard_auth_mode'] === 'password') ? 'password' : 'api_key';
            $sqlSwitchType = "UPDATE marzban_panel SET type = :type, version_panel = :version_panel, url_panel = :url_panel, username_panel = :username_panel, password_panel = :password_panel, api_key = :api_key, xui_api_mode = :xui_api_mode, xui_api_token = :xui_api_token, remna_api_token = :remna_api_token";
            if ($finalType == "guard") {
                $sqlSwitchType .= ", guard_service_ids = :guard_service_ids, guard_version = :guard_version";
            }
            if ($finalType == "rebecca") {
                $sqlSwitchType .= ", rebecca_service_id = :rebecca_service_id";
            }
            if ($finalType == "pasarguard") {
                $sqlSwitchType .= ", pasarguard_auth_mode = :pasarguard_auth_mode";
            }
            $sqlSwitchType .= " WHERE code_panel = :code_panel";
            $stmt = $pdo->prepare($sqlSwitchType);
            $stmt->bindParam(':type', $finalType);
            $stmt->bindParam(':version_panel', $versionPanel);
            $stmt->bindParam(':url_panel', $urlPanel);
            $stmt->bindParam(':username_panel', $usernamePanel);
            $stmt->bindParam(':password_panel', $passwordPanel);
            $stmt->bindParam(':api_key', $apiKey);
            $stmt->bindParam(':xui_api_mode', $xuiApiMode);
            $stmt->bindParam(':xui_api_token', $xuiApiToken);
            $stmt->bindParam(':remna_api_token', $remnaApiToken);
            if ($finalType == "pasarguard") {
                $stmt->bindParam(':pasarguard_auth_mode', $pasarguardAuthMode);
            }
            if ($finalType == "guard") {
                $guardServiceIds = null;
                $guardVersionSwitch = isset($userdata['guard_version']) && $userdata['guard_version'] === 'v2' ? 'v2' : 'v1';
                $stmt->bindParam(':guard_service_ids', $guardServiceIds);
                $stmt->bindParam(':guard_version', $guardVersionSwitch);
            }
            if ($finalType == "rebecca") {
                $rebeccaServiceIdSwitch = isset($userdata['rebecca_service_id']) ? $userdata['rebecca_service_id'] : 'auto';
                $stmt->bindParam(':rebecca_service_id', $rebeccaServiceIdSwitch);
            }
            $stmt->bindParam(':code_panel', $codePanel);
            $stmt->execute();
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $namePanel, "id", $from_id);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($finalType, "✅ نوع پنل با موفقیت تغییر کرد.");
            break;
        case 2:

            $broadcastStatus = method_exists(\Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::class, 'nm_getBroadcastStatus') ? \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_getBroadcastStatus() : null;
            if ($broadcastStatus !== null) {
                Editmessagetext(
                    $from_id,
                    $message_id,
                    \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusText($broadcastStatus),
                    \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusKeyboard($broadcastStatus),
                    'HTML'
                );
                return;
            }

            if (!is_file('cronbot/users.json')) {
                @file_put_contents('cronbot/users.json', json_encode([]));
            }
            $listbtn = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "ارسال همگانی", 'callback_data' => 'typeservice-sendmessage'],
                    ],
                    [
                        ['text' => "فوروارد همگانی", 'callback_data' => 'typeservice-forwardmessage'],
                    ],
                    [
                        ['text' => "تعداد روزی که استفاده نکردند", 'callback_data' => 'typeservice-xdaynotmessage'],
                    ],
                    [
                        ['text' => "لغو پیام های پین شده", 'callback_data' => 'typeservice-unpinmessage'],
                    ],
                    [
                        ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $listbtn);
            break;
        case 3:

            $broadcastStatus = method_exists(\Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::class, 'nm_getBroadcastStatus') ? \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_getBroadcastStatus() : null;
            if ($broadcastStatus === null) {
                Editmessagetext(
                    $from_id,
                    $message_id,
                    "✅ عملیات ارسال پیامی در حال انجام نیست.\n\nبرای شروع یک ارسال جدید از منوی اصلی وارد بخش پیام‌رسانی شوید.",
                    json_encode([
                        'inline_keyboard' => [
                            [['text' => "📨 منوی پیام‌رسانی",  'callback_data' => 'systemsms']],
                            [['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser']],
                        ]
                    ]),
                    'HTML'
                );
                if (!empty($callback_query_id)) {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text'              => 'عملیاتی در حال انجام نیست.',
                        'show_alert'        => false,
                        'cache_time'        => 0,
                    ]);
                }
                return;
            }
            Editmessagetext(
                $from_id,
                $message_id,
                \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusText($broadcastStatus),
                \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusKeyboard($broadcastStatus),
                'HTML'
            );
            if (!empty($callback_query_id)) {
                $toast = '🚀 ارسال‌شده: ' . number_format((int) $broadcastStatus['sent'])
                       . ' | 📊 باقی‌مانده: ' . number_format((int) $broadcastStatus['remaining']);
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text'              => $toast,
                    'show_alert'        => false,
                    'cache_time'        => 0,
                ]);
            }
            return;
            break;
        case 4:
            $rxResumeResult = 'not_found';
            if (method_exists(\Faoxima\Admin\Broadcast\BroadcastQueue::class, 'rx_broadcast_info_update')) {
                \Faoxima\Admin\Broadcast\BroadcastQueue::rx_broadcast_info_update('cronbot/info', $rxResumeMatch[1], static function (array &$c) use (&$rxResumeResult) {
                    if (!\Faoxima\Admin\Broadcast\BroadcastQueue::rx_broadcast_resume_now($c, true)) {
                        $rxResumeResult = 'not_paused';
                        return false;
                    }
                    $rxResumeResult = 'resumed';
                });
            }
            $rxResumeToast = [
                'resumed'    => '▶️ عملیات از همان نقطه ادامه می‌یابد (حداکثر تا یک دقیقه دیگر).',
                'not_paused' => 'این عملیات در حالت توقف نیست.',
                'not_found'  => 'این عملیات دیگر وجود ندارد یا لغو شده است.',
            ][$rxResumeResult];
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text'              => $rxResumeToast,
                    'show_alert'        => $rxResumeResult !== 'resumed',
                    'cache_time'        => 0,
                ]);
            }
            $broadcastStatus = method_exists(\Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::class, 'nm_getBroadcastStatus') ? \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_getBroadcastStatus() : null;
            if ($broadcastStatus !== null) {
                Editmessagetext(
                    $from_id,
                    $message_id,
                    \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusText($broadcastStatus),
                    \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusKeyboard($broadcastStatus),
                    'HTML'
                );
            } else {
                Editmessagetext($from_id, $message_id, "❌ عملیات ارسال پیامی برای ادامه وجود ندارد.", null, 'HTML');
            }
            return;
            break;
        case 5:

            $broadcastStatus = method_exists(\Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::class, 'nm_getBroadcastStatus') ? \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_getBroadcastStatus() : null;
            if ($broadcastStatus !== null) {
                Editmessagetext(
                    $from_id,
                    $message_id,
                    \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusText($broadcastStatus),
                    \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::nm_buildBroadcastStatusKeyboard($broadcastStatus),
                    'HTML'
                );
                if (!empty($callback_query_id)) {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text'              => '⏳ یک عملیات ارسال در حال انجام است.',
                        'show_alert'        => true,
                        'cache_time'        => 0,
                    ]);
                }
                return;
            }
            $type = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "typeservice", $type);
            if ($type == "unpinmessage") {
                deletemessage($from_id, $message_id);
                $typesend = [
                    "unpinmessage" => "لغو پیام پین شده"
                ][$type];
                $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend";
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
                return;
            }
            $listbtn = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "همه کاربران", 'callback_data' => 'typeusermessage-all'],
                    ],
                    [
                        ['text' => "مشتریانی که خرید داشتند", 'callback_data' => 'typeusermessage-customer'],
                    ],
                    [
                        ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typeusermessage-nonecustomer'],
                    ],
                    [
                        ['text' => "بازگشت به منوی قبل", 'callback_data' => 'systemsms'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "📌 سرویس برای کدام گروه کاربری اعمال شود؟", $listbtn);
            break;
        }

        return true;
    }
}
