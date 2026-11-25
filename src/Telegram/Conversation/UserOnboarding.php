<?php

namespace Faoxima\Telegram\Conversation;

use mysqli;
use PDO;
use Throwable;

final class UserOnboarding
{
    public static function run(
        PDO $pdo,
        &$connect,
        &$text,
        &$datain,
        array &$user,
        $from_id,
        $username,
        array $admin_ids,
        $callback_query_id,
        $message_id,
        $Chat_type,
        $domainhosts,
        array $setting,
        array $datatextbot,
        array $textbotlang,
        $keyboard,
        $confrimrolls,
    ): ?bool {
        $isAdmin = in_array($from_id, $admin_ids);
        $rxStartLanding = false;
        if (strpos($text, "/start ") !== false && $user['step'] != "gettextSystemMessage") {
            $affiliatesid = explode(" ", $text)[1];
            if (!in_array($affiliatesid, ['start', "usertest", "/start", "buy", "help"])) {
                \Faoxima\Services\Referrals\Referrals::isValidInvitationCode($setting, $from_id, $user['verify']);
                if (is_numeric($affiliatesid) && $setting['affiliatesstatus'] == "offaffiliates") {
                    sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], $keyboard, 'HTML');
                    return null;
                }
                if (is_numeric($affiliatesid) && \Faoxima\Core\Database\Db::userExists($affiliatesid)) {
                    if ($affiliatesid == $from_id) {
                        sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
                        return null;
                    }
                    $user = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id, "select", ['cache' => false]);
                    if (intval($user['affiliates']) != 0) {
                        sendmessage($from_id, $textbotlang['users']['affiliates']['affiliateedago'], null, 'html');
                        return null;
                    }
                    $rxReferralState = \Faoxima\Services\Referrals\Referrals::rxReferralRegister($from_id, $affiliatesid);
                    if ($rxReferralState === 'already_referred') {
                        sendmessage($from_id, $textbotlang['users']['affiliates']['affiliateedago'], null, 'html');
                        return null;
                    }
                    if ($rxReferralState === 'registered') {
                        $useraffiliates = \Faoxima\Core\Database\Db::select("user", "*", 'id', $affiliatesid, "select");
                        $rxWelcomeAlertTpl = $datatextbot['dyn_referral_welcome_alert'] ?? "<b>🎉 خوش آمدی!</b>\n\nشما با دعوت <b>@%s</b> وارد ربات شدی و به عنوان زیرمجموعه ثبت شدی ✅\n\nبرای دریافت هدیه عضویت:\n🔘 به منوی <b>زیرمجموعه‌گیری</b> برو\n🔘 دکمه <b>🎁 هدیه عضویت</b> را بزن\n\nبا این کار، هم خودت و هم معرفت هدیه می‌گیرید! 💰";
                        sendmessage($from_id, sprintf($rxWelcomeAlertTpl, $useraffiliates['username']), $keyboard, 'html');
                        $rxInviterRewardTpl = $datatextbot['dyn_referral_inviter_reward_alert'] ?? "<b>🎉 یک زیرمجموعه جدید!</b>\n\nکاربر <b>@%s</b> با لینک دعوت شما وارد ربات شد ✅\n\nبا خریدهای این کاربر، <b>سهم هدیه شما</b> به حسابت واریز می‌شه 🔥";
                        sendmessage($affiliatesid, sprintf($rxInviterRewardTpl, $username), $keyboard, 'html');
                        \Faoxima\Services\Referrals\Referrals::rxReferralGiftNotify(\Faoxima\Services\Referrals\Referrals::rxReferralGiftPayout($from_id));
                    }
                } else {
                    if (!is_numeric($affiliatesid) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string)$affiliatesid) && strpos((string)$affiliatesid, 'manageuser_') !== 0) {
                        try {
                            $stmtSourceInv = $pdo->prepare("SELECT 1 FROM user WHERE codeInvitation = ? LIMIT 1");
                            $stmtSourceInv->execute([$affiliatesid]);
                            if (!$stmtSourceInv->fetchColumn()) {
                                $stmtSource = $pdo->prepare("UPDATE user SET `source` = ? WHERE id = ? AND (`source` IS NULL OR `source` = '')");
                                $stmtSource->execute([$affiliatesid, $from_id]);
                                if ($stmtSource->rowCount() > 0 && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                                    \Faoxima\Core\Database\SelectCache::clearSelectCache('user');
                                }
                            }
                        } catch (Throwable $e) {
                            error_log('[start_source] ' . $e->getMessage());
                        }
                    }
                    $rxStartLanding = true;
                }
            } else {
                $text = $affiliatesid;
            }
        }
        if (intval($user['verify']) == 0 && !$isAdmin && $setting['verifystart'] == "onverify" && !\Faoxima\Telegram\Support\Replies::rx_auth_skip_user($user)) {
            $textverify = \Faoxima\Telegram\Support\MessageTemplates::get('onboarding_verify_pending', ['support' => $setting['id_support']]);
            sendmessage($from_id, $textverify, null, 'html');
            return null;
        }

        if ($setting['roll_Status'] == "rolleon" && $user['roll_Status'] == 0 && ($text != "✅ قوانین را می پذیرم" and $datain != "acceptrule") && !$isAdmin) {
            sendmessage($from_id, $datatextbot['text_roll'], $confrimrolls, 'html');
            return null;
        }
        if ($text == "✅ قوانین را می پذیرم" or $datain == "acceptrule") {
            if ($setting['roll_Status'] == "rolleon" && intval($user['roll_Status']) == 0) {
                $rxStartLanding = true;
            }
            deletemessage($from_id, $message_id);
            sendmessage($from_id, $textbotlang['users']['Rules'], null, 'html');
            $confrim = true;
            \Faoxima\Core\Database\Db::update("user", "roll_Status", $confrim, "id", $from_id);
        }

        if ($setting['Bot_Status'] == "botstatusoff" && !$isAdmin) {
            sendmessage($from_id, $datatextbot['text_bot_off'], null, 'html');
            return null;
        }

        if (!self::checkChannels($isAdmin, $rxStartLanding, $text, $datain, $user, $from_id,
            $callback_query_id, $message_id, $datatextbot, $textbotlang, $keyboard)) {
            return null;
        }

        $rxMiniAppLabel = trim((string)($datatextbot['text_miniapp_button'] ?? ''));
        if ($rxMiniAppLabel === '') $rxMiniAppLabel = '🚀 Open Mini App';
        if ($Chat_type === 'private' && empty($callback_query_id)
            && is_string($text) && trim($text) === $rxMiniAppLabel) {
            $rxMiniAppHost = rtrim((string)preg_replace('#^https?://#i', '', trim((string)($domainhosts ?? ''))), '/');
            $rxMiniAppUrl = $rxMiniAppHost !== '' ? 'https://' . $rxMiniAppHost . '/app/' : '';
            if ($rxMiniAppUrl === '' || filter_var($rxMiniAppUrl, FILTER_VALIDATE_URL) === false
                || (string)parse_url($rxMiniAppUrl, PHP_URL_HOST) === '') {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('onboarding_miniapp_not_configured'), null, 'html');
                return null;
            }
            $rxMiniAppKeyboard = json_encode(['inline_keyboard' => [[[
                'text' => $rxMiniAppLabel,
                'web_app' => ['url' => $rxMiniAppUrl],
            ]]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('onboarding_miniapp_open_hint'), $rxMiniAppKeyboard, 'html');
            return null;
        }

        if ($text == "/start" || $datain == "start" || $text == "start" || $rxStartLanding) {
            \Faoxima\Telegram\Conversation\HomeNavigation::reset(
                $pdo,
                $from_id,
                static function () use ($from_id): void {
                    if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                        \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $from_id);
                    } else {
                        \Faoxima\Core\Database\SelectCache::clearSelectCache('user');
                    }
                },
                static fn ($state, $userId) => \Faoxima\Telegram\Navigation\Steps::step($state, $userId, $pdo),
            );
            \Faoxima\Telegram\Conversation\HomeNavigation::deliver(
                $from_id,
                $callback_query_id ?? null,
                $datatextbot['text_start'],
                $keyboard,
                [\Faoxima\Telegram\Media\SectionMedia::class, 'rx_send_banner_message'],
                'telegram',
                function_exists('removeReplyKeyboardOnStartIfNeeded') ? 'removeReplyKeyboardOnStartIfNeeded' : null,
            );
            return null;
        }
        if ((!isset($connect) || !($connect instanceof mysqli)) && function_exists('getMysqliConnection')) {
            $connect = getMysqliConnection();
        }
        if (is_string($datain) && preg_match('/^cdpick_([0-9]{1,5})$/', $datain, $rxDurationPick)) {
            if (!\Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxBotCanPickDuration($from_id, $user, $rxDurationPick[1])) {
                return null;
            }
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
            }
            $text = $rxDurationPick[1];
            $datain = '';
        }
        if (
            method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_route')
            && in_array((string) ($user['step'] ?? ''), ['getTransferRecipient', 'getTransferAmount', 'getTransferConfirm'], true)
            && \Faoxima\Telegram\Presentation\Sections::rx_ui_route($datain ?? '', $text ?? '') !== null
        ) {
            \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxBotResetPendingTransfer($pdo, $from_id, $user);
        }
        if ($datain === 'confirmpaid') {
            if (!empty($callback_query_id) && function_exists('telegram')) {
                @telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => \Faoxima\Telegram\Support\MessageTemplates::plain('onboarding_payment_already_confirmed_alert'),
                    'show_alert' => false,
                    'cache_time' => 0,
                ]);
            }
            return null;
        }

        return true;
    }

    private static function checkChannels(
        bool $isAdmin,
        bool $rxStartLanding,
        $text,
        $datain,
        array &$user,
        $from_id,
        $callback_query_id,
        $message_id,
        array $datatextbot,
        array $textbotlang,
        $keyboard,
    ): bool {
        $isStartRequest = ($text == "/start" || $datain == "start" || $text == "start" || (is_string($text) && strpos($text, "/start ") === 0) || $rxStartLanding);
        $isConfirmChannel = ($datain == "confirmchannel");
        if (!$isAdmin) {
            $channels_id = \Faoxima\Core\Database\Db::select("channels", "link", null, null, "FETCH_COLUMN", ['cache' => false]);
            if (!empty($channels_id) && is_array($channels_id)) {
                $channels = null;
                $rxChannelCacheApcu = function_exists('apcu_fetch') && function_exists('apcu_store') && filter_var((string) ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN);
                $rxChannelCacheKey = 'faoxima:joinchannel:' . $from_id . ':' . md5(implode(',', $channels_id));
                if (!$isConfirmChannel && !$isStartRequest && $rxChannelCacheApcu) {
                    $rxChannelCacheHit = false;
                    $rxChannelCached = apcu_fetch($rxChannelCacheKey, $rxChannelCacheHit);
                    if ($rxChannelCacheHit && is_array($rxChannelCached)) {
                        $channels = $rxChannelCached;
                    }
                }
                $rxChannelCheckFailed = [];
                if ($channels === null) {
                    $channels = \Faoxima\Admin\Telegram\ChannelNavigation::channelForUser($channels_id, $from_id, $rxChannelCheckFailed);
                    if ($rxChannelCacheApcu && empty($rxChannelCheckFailed)) {
                        @apcu_store($rxChannelCacheKey, $channels, 30);
                    }
                }
                $rxChannelCheckFailedText = \Faoxima\Telegram\Support\MessageTemplates::plain('onboarding_channel_check_failed_alert');
                if ($isConfirmChannel && count($channels) == 0 && !empty($rxChannelCheckFailed)) {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => $rxChannelCheckFailedText,
                        'show_alert' => true,
                        'cache_time' => 0,
                    ]);
                    return false;
                }
                if ($isConfirmChannel) {
                    if (count($channels) == 0) {
                        \Faoxima\Core\Database\Db::update("user", "joinchannel", "active", "id", $from_id);
                        $user['joinchannel'] = "active";
                        deletemessage($from_id, $message_id);
                        \Faoxima\Telegram\Media\SectionMedia::rx_send_banner_message($from_id, 'start', $datatextbot['text_start'], $keyboard, 'html');
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'text' => $textbotlang['users']['channel']['confirmed'],
                            'show_alert' => false,
                            'cache_time' => 5,
                        ]);
                        $partsaffiliates = explode("_", (string)($user['Processing_value_four'] ?? ''));
                        if ($partsaffiliates[0] == "affiliates") {
                            $affiliatesid = $partsaffiliates[1] ?? '';
                            if (!\Faoxima\Core\Database\Db::userExists($affiliatesid) || $affiliatesid == $from_id || intval($user['affiliates']) != 0) {
                                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "none", "id", $from_id);
                                return false;
                            }
                            if (\Faoxima\Services\Referrals\Referrals::rxReferralRegister($from_id, $affiliatesid) === 'registered') {
                                \Faoxima\Services\Referrals\Referrals::rxReferralGiftNotify(\Faoxima\Services\Referrals\Referrals::rxReferralGiftPayout($from_id));
                            }
                            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "none", "id", $from_id);
                        }
                        return false;
                    }
                    $keyboardchannel = self::joinKeyboard($channels, $textbotlang['users']['channel']['confirmjoin']);
                    Editmessagetext($from_id, $message_id, $datatextbot['text_channel'], $keyboardchannel);
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => $textbotlang['users']['channel']['notconfirmed'],
                        'show_alert' => true,
                        'cache_time' => 0,
                    ]);
                    return false;
                }

                if (count($channels) != 0) {
                    if ($user['joinchannel'] == "active") {
                        \Faoxima\Core\Database\Db::update("user", "joinchannel", "0", "id", $from_id);
                        $user['joinchannel'] = "0";
                    }
                    if (is_string($text) && strpos($text, "/start ") === 0) {
                        $startParts = explode(" ", $text, 2);
                        if (!empty($startParts[1])) {
                            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "affiliates_" . trim($startParts[1]), "id", $from_id);
                        }
                    }
                    $keyboardchannel = self::joinKeyboard($channels, $textbotlang['users']['channel']['confirmjoin']);
                    sendmessage($from_id, $datatextbot['text_channel'], $keyboardchannel, 'html');
                    return false;
                } elseif (!empty($rxChannelCheckFailed)) {
                    if (!empty($callback_query_id)) {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time' => 0,
                        ]);
                    }
                    $rxChannelRetryKeyboard = json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]]]]);
                    sendmessage($from_id, $rxChannelCheckFailedText, $rxChannelRetryKeyboard, 'html');
                    return false;
                } else {
                    if ($user['joinchannel'] != "active") {
                        \Faoxima\Core\Database\Db::update("user", "joinchannel", "active", "id", $from_id);
                        $user['joinchannel'] = "active";
                    }
                }
            }
        }
        return true;
    }

    private static function joinKeyboard(array $channels, $confirmLabel): string|false
    {
        $rows = [];
        foreach ($channels as $channel) {
            $details = \Faoxima\Core\Database\Db::select('channels', '*', 'link', $channel, 'select', ['cache' => false]);
            if (empty($details['remark']) || empty($details['linkjoin'])) {
                continue;
            }
            $rows[] = [[
                'text' => (string) $details['remark'],
                'url' => $details['linkjoin'],
                '_rxcolor' => 'channel:' . $details['id'],
            ]];
        }
        $rows[] = [['text' => $confirmLabel, 'callback_data' => 'confirmchannel']];
        return json_encode(['inline_keyboard' => $rows]);
    }
}
