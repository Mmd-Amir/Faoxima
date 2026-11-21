<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use PDOException;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $datain, $delMatches, $editJoinMatches, $editRemMatches, $from_id, $pdo, $rxPreMatched, $text, $textbotlang, $update.
 * Inherited or exported by reference: $channelkeyboard, $kb, $stmt, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ChannelManagement
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        &$channelkeyboard,
        $datain,
        $delMatches,
        $editJoinMatches,
        $editRemMatches,
        $from_id,
        &$kb,
        $pdo,
        $rxPreMatched,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        &$user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $wizKb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_wizard_back_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_wizard_back_keyboard() : $backadmin;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['changechannel'], $wizKb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('addchannel', $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            $rxChannelChatId = method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_normalize_channel_chat_id') ? \Faoxima\Admin\Telegram\ChannelNavigation::rx_normalize_channel_chat_id($text) : trim((string) $text);
            if ($rxChannelChatId === '') {
                $wizKb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_wizard_back_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_wizard_back_keyboard() : $backadmin;
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['changechannel'], $wizKb, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "link", $rxChannelChatId);
            $wizKb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_wizard_back_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_wizard_back_keyboard() : $backadmin;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 یک نام برای دکمه عضویت چنل انتخاب نمایید.", $wizKb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getremark', $from_id);
            break;
        case 3:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "remark", $text);
            $wizKb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_wizard_back_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_wizard_back_keyboard() : $backadmin;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 لینک عضویت را ارسال کنید", $wizKb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlinkjoin', $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!filter_var($text, FILTER_VALIDATE_URL)) {
                $wizKb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_wizard_back_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_wizard_back_keyboard() : $backadmin;
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "آدرس عضویت صحیح نمی باشد", $wizKb, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            if (!is_array($userdata)) {
                $userdata = [];
            }

            $remark = isset($userdata['remark']) ? (string) $userdata['remark'] : '';
            $link = isset($userdata['link']) ? (string) $userdata['link'] : '';

            $insertChannel = function ($remarkValue) use ($pdo, $link, $text) {
                $stmt = $pdo->prepare("INSERT INTO channels (link, remark, linkjoin) VALUES (:link, :remark, :linkjoin)");
                $stmt->bindValue(':remark', $remarkValue, PDO::PARAM_STR);
                $stmt->bindValue(':link', $link, PDO::PARAM_STR);
                $stmt->bindValue(':linkjoin', $text, PDO::PARAM_STR);
                $stmt->execute();
            };

            try {
                $insertChannel($remark);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
                    \Faoxima\Core\Database\Db::ensureTableUtf8mb4('channels');
                    try {
                        $insertChannel($remark);
                    } catch (PDOException $retryException) {
                        if (strpos($retryException->getMessage(), 'Incorrect string value') === false) {
                            throw $retryException;
                        }

                        $sanitisedRemark = is_string($remark) ? @iconv('UTF-8', 'UTF-8//IGNORE', $remark) : '';
                        if ($sanitisedRemark === false) {
                            $sanitisedRemark = '';
                        }
                        $insertChannel($sanitisedRemark);
                    }
                } else {
                    throw $e;
                }
            }
            $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
            \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
            if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ کانال جوین اجباری با موفقیت ثبت گردید.", $channelkeyboard, 'HTML');
            break;
        case 5:
            $matchedChan = isset($rxPreMatched) && is_array($rxPreMatched)
                ? $rxPreMatched
                : (method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_find_channel_from_input') && ($pdo instanceof PDO)
                    ? \Faoxima\Admin\Telegram\ChannelNavigation::rx_find_channel_from_input($text, $datain ?? '', $pdo)
                    : null);

            if ($matchedChan && !empty($matchedChan['id'])) {
                $chanId = (int)$matchedChan['id'];
                if (method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_render_channel_manage')) {
                    \Faoxima\Admin\Telegram\ChannelNavigation::rx_render_channel_manage($chanId, $from_id);
                } else {
                    \Faoxima\Core\Database\Db::update("user", "Processing_value", (string)$chanId, "id", $from_id);
                    \Faoxima\Telegram\Navigation\Steps::step('channel_manage', $from_id);
                    if (isset($user) && is_array($user)) {
                        $user['step'] = 'channel_manage';
                        $user['Processing_value'] = (string)$chanId;
                    }
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel_manage');
                    }
                    $msg = "📋 <b>مدیریت کانال:</b> " . htmlspecialchars($matchedChan['remark'] ?? '') . "\n\n"
                        . "🏷 <b>عنوان دکمه:</b> <code>" . htmlspecialchars($matchedChan['remark'] ?? '') . "</code>\n"
                        . "🔗 <b>لینک عضویت:</b> " . htmlspecialchars($matchedChan['linkjoin'] ?? '') . "\n"
                        . "🆔 <b>آیدی کانال:</b> <code>" . htmlspecialchars($matchedChan['link'] ?? '') . "</code>\n\n"
                        . "یک گزینه را انتخاب کنید:";
                    $kb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_manage_keyboard')
                        ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_manage_keyboard($chanId, true)
                        : $channelkeyboard;
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $msg, $kb, 'HTML');
                }
            } else {
                $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
                \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
                if (isset($user) && is_array($user)) {
                    $user['step'] = 'channel';
                }
                if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                    \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کانال مورد نظر یافت نشد. لطفاً از لیست زیر انتخاب نمایید.", $channelkeyboard, 'HTML');
            }
            break;
        case 6:
            $chanId = isset($delMatches[1]) ? (int)$delMatches[1] : (int)$user['Processing_value'];
            $stmt = $pdo->prepare("DELETE FROM channels WHERE id = :id");
            $stmt->execute([':id' => $chanId]);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "none", "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
            if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
            }
            $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removedchannel'], $channelkeyboard, 'HTML');
            break;
        case 7:
            $chanId = isset($editRemMatches[1]) ? (int)$editRemMatches[1] : (int)$user['Processing_value'];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $chanId, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('ch_edit_remark', $from_id);
            $editBackKb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_edit_back_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_edit_back_keyboard($chanId) : $backadmin;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 عنوان جدید دکمه را ارسال کنید:", $editBackKb, 'HTML');
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            $chanId = (int)($user['Processing_value'] ?? 0);
            if ($chanId > 0) {
                $stmt = $pdo->prepare("UPDATE channels SET remark = :rem WHERE id = :id");
                $stmt->execute([':rem' => trim($text), ':id' => $chanId]);
            }
            if ($chanId > 0 && method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_render_channel_manage')) {
                \Faoxima\Admin\Telegram\ChannelNavigation::rx_render_channel_manage($chanId, $from_id, "✅ عنوان دکمه با موفقیت ویرایش شد.");
            } else {
                $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
                \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
                if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                    \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ عنوان دکمه با موفقیت ویرایش شد.", $channelkeyboard, 'HTML');
            }
            break;
        case 9:
            $chanId = isset($editJoinMatches[1]) ? (int)$editJoinMatches[1] : (int)$user['Processing_value'];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $chanId, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('ch_edit_linkjoin', $from_id);
            $editBackKb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_edit_back_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_edit_back_keyboard($chanId) : $backadmin;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 لینک جدید عضویت را ارسال کنید (مثال: https://t.me/...):", $editBackKb, 'HTML');
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            $chanId = (int)($user['Processing_value'] ?? 0);
            if (!filter_var($text, FILTER_VALIDATE_URL)) {
                $editBackKb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_edit_back_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_edit_back_keyboard($chanId) : $backadmin;
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آدرس عضویت صحیح نمی باشد. لطفاً یک لینک معتبر ارسال کنید.", $editBackKb, 'HTML');
                return;
            }
            if ($chanId > 0) {
                $stmt = $pdo->prepare("UPDATE channels SET linkjoin = :lj WHERE id = :id");
                $stmt->execute([':lj' => trim($text), ':id' => $chanId]);
            }
            if ($chanId > 0 && method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_render_channel_manage')) {
                \Faoxima\Admin\Telegram\ChannelNavigation::rx_render_channel_manage($chanId, $from_id, "✅ لینک عضویت با موفقیت ویرایش شد.");
            } else {
                $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
                \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
                if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                    \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ لینک عضویت با موفقیت ویرایش شد.", $channelkeyboard, 'HTML');
            }
            break;
        }

        return true;
    }
}
