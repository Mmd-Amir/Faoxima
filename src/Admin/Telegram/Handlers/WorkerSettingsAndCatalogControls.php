<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use PDOException;

/**
 * Handler stage. Inherited by value: $CartManage, $adminAction, $adminExecution, $admin_ids, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $helpedit, $json_list_helpkey, $keyboardadmin, $message_id, $pdo, $photo, $photoid, $setting, $setting_panel, $text, $textbotlang, $update, $video, $videoid.
 * Inherited or exported by reference: $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class WorkerSettingsAndCatalogControls
{
    public static function handle(
        $CartManage,
        $adminAction,
        $adminExecution,
        $admin_ids,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
        $helpedit,
        $json_list_helpkey,
        $keyboardadmin,
        $message_id,
        $pdo,
        $photo,
        $photoid,
        $setting,
        $setting_panel,
        $text,
        $textbotlang,
        $update,
        &$user,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['setdayremove'] . $setting['removedayc'] . "روز", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getdaycron", $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("setting", "removedayc", $text);
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getnameforedite", $from_id);
            break;
        case 4:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $helpedit, 'HTML');
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $text, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step("help_edit", $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "نام جدید آموزش را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('changenamehelp', $from_id);
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            if (strlen($text) >= 150) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
                return;
            }
            try {
                $rxHelpStmt = $pdo->prepare("SELECT id FROM help WHERE name_os = ? ORDER BY id LIMIT 1");
                $rxHelpStmt->execute([(string) $user['Processing_value']]);
                $rxHelpId = $rxHelpStmt->fetchColumn();
                if ($rxHelpId === false) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آموزش انتخاب‌شده یافت نشد. لطفاً دوباره آموزش را انتخاب کنید.", $backadmin, 'HTML');
                    return;
                }
                $rxHelpDupStmt = $pdo->prepare("SELECT 1 FROM help WHERE name_os = ? AND id <> ? LIMIT 1");
                $rxHelpDupStmt->execute([$text, $rxHelpId]);
                if ($rxHelpDupStmt->fetchColumn() !== false) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آموزشی با این نام قبلاً ثبت شده است. نام دیگری ارسال کنید.", $backadmin, 'HTML');
                    return;
                }
                $rxHelpRenameStmt = $pdo->prepare("UPDATE help SET name_os = ? WHERE id = ?");
                $rxHelpRenameStmt->execute([$text, $rxHelpId]);
            } catch (PDOException $e) {
                error_log('help rename failed: ' . $e->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ بروزرسانی نام آموزش ناموفق بود. دوباره تلاش کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\SelectCache::clearSelectCache('help');
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $text, "id", $from_id);
            $user['Processing_value'] = $text;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ نام آموزش بروزرسانی شد", $helpedit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('help_edit', $from_id);
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "دسته بندی جدید خود را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('changecategoryhelp', $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            if (strlen($text) >= 150) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
                return;
            }
            if (!self::saveHelpEdit($pdo, 'category = ?', [$text], $user, $from_id, $backadmin, $json_list_helpkey)) {
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ نام دسته آموزش بروزرسانی شد", $helpedit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('help_edit', $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "توضیحات جدید را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('changedeshelp', $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!self::saveHelpEdit($pdo, 'Description_os = ?', [$text], $user, $from_id, $backadmin, $json_list_helpkey)) {
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ توضیحات  آموزش بروزرسانی شد", $helpedit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('help_edit', $from_id);
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "تصویر یا فیلم جدید را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('changemedia', $from_id);
            break;
        case 12:
            if (!isset($update['message']) && empty($text)) { return; }
            if ($photo && !empty($photoid)) {
                $media = [$photoid, 'photo'];
            } elseif ($video && !empty($videoid)) {
                $media = [$videoid, 'video'];
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفاً یک تصویر یا ویدیو برای آموزش ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            if (!self::saveHelpEdit($pdo, 'Media_os = ?, type_Media_os = ?', $media, $user, $from_id, $backadmin, $json_list_helpkey)) {
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ رسانهٔ آموزش به‌روزرسانی شد.", $helpedit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('help_edit', $from_id);
            break;
        case 13:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 متن دکمه لینک دانلود برنامه را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('changeapptitlehelp', $from_id);
            break;
        case 14:
            if (!isset($update['message']) && empty($text)) { return; }
            if (strlen($text) > 200) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام باید کمتر از ۲۰۰ کاراکتر باشد.", $backadmin, 'HTML');
                return;
            }
            if (!self::saveHelpEdit($pdo, 'app_title = ?', [$text], $user, $from_id, $backadmin, $json_list_helpkey)) {
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 لینک دانلود اپ را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('changeapplinkhelp', $from_id);
            break;
        case 15:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!filter_var($text, FILTER_VALIDATE_URL)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
                return;
            }
            if (!self::saveHelpEdit($pdo, 'app_link = ?', [$text], $user, $from_id, $backadmin, $json_list_helpkey)) {
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ لینک برنامه با موفقیت بروزرسانی گردید.", $helpedit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('help_edit', $from_id);
            break;
        case 16:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "برای تمامی کاربران غیرفعال گردید یا کاربران جدید؟
    کاربران جدید 0
    همه کاربران 1
    2 کاربران بجز نمایندگان", null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('showcardallusers', $from_id);
            break;
        case 17:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['disableshowcardstatus'], null, 'HTML');
            if (intval($text) == "1") {
                \Faoxima\Core\Database\Db::update("user", "cardpayment", "0");
                \Faoxima\Core\Database\Db::update("setting", "showcard", "0");
            } elseif (intval($text) == 2) {
                \Faoxima\Core\Database\Db::update("user", "cardpayment", "0", "agent", "f");
                \Faoxima\Core\Database\Db::update("setting", "showcard", "0");
            } else {
                \Faoxima\Core\Database\Db::update("setting", "showcard", "0");
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            if (isset($CartManage)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت', $CartManage, 'HTML');
            }
            break;
        case 18:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['activeshowcardstatus'], null, 'HTML');
            \Faoxima\Core\Database\Db::update("user", "cardpayment", "1");
            \Faoxima\Core\Database\Db::update("setting", "showcard", "1");
            break;
        case 19:
            $rxReservationAnswer = function ($message = '', $alert = false) use ($callback_query_id) {
                if (!empty($callback_query_id)) {
                    telegram('answerCallbackQuery', array_filter([
                        'callback_query_id' => $callback_query_id,
                        'text' => $message !== '' ? strip_tags($message) : null,
                        'show_alert' => $alert ?: null,
                    ], function ($value) {
                        return $value !== null;
                    }));
                }
            };
            if (!in_array($from_id, $admin_ids) || ($adminrulecheck['rule'] ?? '') != "administrator") {
                $rxReservationAnswer('⛔️', true);
                return;
            }
            $rxReservationParsed = \Faoxima\Services\Renewal\Reservations::rxReservationAdminParse($datain);
            $rxReservationPanel = is_array($rxReservationParsed) ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $rxReservationParsed['code'], "select") : false;
            if (!is_array($rxReservationParsed) || !is_array($rxReservationPanel) || ($rxReservationPanel['type'] ?? '') === 'Manualsale') {
                $rxReservationAnswer(\Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_outdated'), true);
                return;
            }
            $rxReservationState = $rxReservationParsed['state'];
            $rxReservationAction = $rxReservationParsed['action'];
            if ($rxReservationAction === 'p') {
                $rxReservationState['selected'] = true;
            } elseif (empty($rxReservationState['selected']) && !in_array($rxReservationAction, ['x', 'b'], true)) {
                $rxReservationAnswer(\Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_outdated'), true);
                return;
            }
            if ($rxReservationAction === 'x') {
                $rxReservationAnswer();
                return;
            }
            if ($rxReservationAction === 'b') {
                $rxReservationAnswer(\Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_cancelled'));
                Editmessagetext($from_id, $message_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_cancelled'), json_encode(['inline_keyboard' => []]));
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $rxReservationPanel['name_panel'], "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Services\Panels\PanelSetup::outtypepanel($rxReservationPanel['type'], $textbotlang['Admin']['Back-menu']);
                return;
            }
            $rxReservationDateOn = (((int) $rxReservationState['mask'] >> 2) & 1) === 1;
            if ($rxReservationAction === 'e' || ($rxReservationAction === 'c' && $rxReservationParsed['arg'] === 2 && !$rxReservationDateOn)) {
                $rxReservationAnswer();
                $rxReservationState['mask'] = ((int) $rxReservationState['mask'] | 4) & 7;
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $rxReservationPanel['name_panel'], "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step(\Faoxima\Services\Renewal\Reservations::rxReservationAdminStepEncode((string) $rxReservationPanel['code_panel'], $rxReservationState), $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_date_prompt', ['tz' => date_default_timezone_get()]), $backadmin ?? null, 'HTML');
                return;
            }
            if ($rxReservationAction === 's') {
                $rxReservationError = \Faoxima\Services\Renewal\Reservations::rxReservationAdminValidate($rxReservationState, time());
                if ($rxReservationError !== null) {
                    $rxReservationAnswer($rxReservationError, true);
                    $rxReservationAdminView = \Faoxima\Services\Renewal\Reservations::rxReservationAdminView($rxReservationPanel, $rxReservationState, $rxReservationError);
                    Editmessagetext($from_id, $message_id, $rxReservationAdminView['text'], $rxReservationAdminView['keyboard'], 'HTML');
                    return;
                }
                \Faoxima\Services\Renewal\ReservationStore::ensureSchema($pdo);
                \Faoxima\Core\Database\Db::update("marzban_panel", "reservation_settings", \Faoxima\Services\Renewal\ReservationCriteria::reservationSettingsJson(\Faoxima\Services\Renewal\Reservations::rxReservationAdminSettingsFromState($rxReservationState)), "code_panel", $rxReservationPanel['code_panel']);
                \Faoxima\Core\Database\Db::update("marzban_panel", "Methodextend", 'رزرو اشتراک', "code_panel", $rxReservationPanel['code_panel']);
                $rxReservationAnswer(\Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_saved'));
                Editmessagetext($from_id, $message_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_saved') . "\n" . \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_summary', ['text' => \Faoxima\Services\Renewal\Reservations::rxReservationActivationText(\Faoxima\Services\Renewal\Reservations::rxReservationAdminSettingsFromState($rxReservationState)['conditions'], $rxReservationState['at'])]), json_encode(['inline_keyboard' => []]));
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $rxReservationPanel['name_panel'], "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Services\Panels\PanelSetup::outtypepanel($rxReservationPanel['type'], $textbotlang['Admin']['Algortimeextend']['SaveData']);
                return;
            }
            $rxReservationState = \Faoxima\Services\Renewal\Reservations::rxReservationAdminApply($rxReservationState, $rxReservationAction, $rxReservationParsed['arg']);
            $rxReservationAnswer();
            $rxReservationAdminView = \Faoxima\Services\Renewal\Reservations::rxReservationAdminView($rxReservationPanel, $rxReservationState);
            Editmessagetext($from_id, $message_id, $rxReservationAdminView['text'], $rxReservationAdminView['keyboard'], 'HTML');
            break;
        case 20:
            if (!isset($update['message']) && empty($text)) { return; }
            $rxReservationDraft = \Faoxima\Services\Renewal\Reservations::rxReservationAdminStepDecode((string) $user['step']);
            $rxReservationPanel = is_array($rxReservationDraft) ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $rxReservationDraft['code'], "select") : false;
            if (!in_array($from_id, $admin_ids) || ($adminrulecheck['rule'] ?? '') != "administrator" || !is_array($rxReservationDraft) || !is_array($rxReservationPanel)) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_outdated'), $keyboardadmin ?? null, 'HTML');
                return;
            }
            $rxReservationAt = \Faoxima\Services\Renewal\ReservationCriteria::reservationParseDate($text, time());
            if ($rxReservationAt === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_date_invalid'), null, 'HTML');
                return;
            }
            $rxReservationState = $rxReservationDraft['state'];
            $rxReservationState['selected'] = true;
            $rxReservationState['mask'] = ((int) $rxReservationState['mask'] | 4) & 7;
            $rxReservationState['at'] = $rxReservationAt;
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            $rxReservationAdminView = \Faoxima\Services\Renewal\Reservations::rxReservationAdminView($rxReservationPanel, $rxReservationState, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_date_saved', ['date' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatExpire($rxReservationAt)]));
            sendmessage($from_id, $rxReservationAdminView['text'], $rxReservationAdminView['keyboard'], 'HTML');
            break;
        }

        return true;
    }

    private static function saveHelpEdit(PDO $pdo, string $assignments, array $values, array $user, $adminId, $backKeyboard, $helpList): bool
    {
        $name = (string) ($user['Processing_value'] ?? '');
        try {
            if ($name !== '') {
                $stmt = $pdo->prepare("UPDATE help SET {$assignments} WHERE name_os = ?");
                $stmt->execute([...$values, $name]);
                if ($stmt->rowCount() > 0) {
                    \Faoxima\Core\Database\SelectCache::clearSelectCache('help');
                    return true;
                }
                // MySQL can report zero affected rows when the submitted value is unchanged.
                $exists = $pdo->prepare('SELECT 1 FROM help WHERE name_os = ? LIMIT 1');
                $exists->execute([$name]);
                if ($exists->fetchColumn() !== false) {
                    return true;
                }
            }
        } catch (PDOException $e) {
            error_log('[admin help edit] ' . $e->getMessage());
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($adminId, "❌ ذخیرهٔ تغییرات آموزش ممکن نشد. لطفاً دوباره تلاش کنید.", $backKeyboard, 'HTML');
            return false;
        }

        \Faoxima\Telegram\Navigation\Steps::step('getnameforedite', $adminId);
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($adminId, "❌ آموزش انتخاب‌شده پیدا نشد. لطفاً دوباره یک آموزش انتخاب کنید.", $helpList, 'HTML');
        return false;
    }
}
