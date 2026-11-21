<?php

namespace Faoxima\Admin\Telegram\Handlers;

use FaoximaApiCredential;

/**
 * Handler stage. Inherited by value: $Methodextend, $adminAction, $adminExecution, $adminnumber, $domainhosts, $from_id, $keyboardadmin, $message_id, $pdo, $text, $textbotlang, $update, $user, $webpanelMgrCredsMatch, $webpanelMgrIpMatch, $webpanelMgrResetMatch, $webpanelMgrUnameMatch, $webpanelMgrViewMatch.
 * Inherited or exported by reference: $token.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RenewalOptionsAndWebManagers
{
    public static function handle(
        $Methodextend,
        $adminAction,
        $adminExecution,
        $adminnumber,
        $domainhosts,
        $from_id,
        $keyboardadmin,
        $message_id,
        $pdo,
        $text,
        $textbotlang,
        &$token,
        $update,
        $user,
        $webpanelMgrCredsMatch,
        $webpanelMgrIpMatch,
        $webpanelMgrResetMatch,
        $webpanelMgrUnameMatch,
        $webpanelMgrViewMatch
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $rxExtendMethodGuide = "🔋 روش تمدید را انتخاب کنید.

"
                . "♻️ «ریست حجم و زمان»: مصرف صفر می‌شود و حجم و زمان باقی‌مانده حذف می‌شود؛ حجم کل = فقط حجم خرید؛ انقضا = زمان اجرا + روزهای خرید.

"
                . "➕ «اضافه شدن زمان و حجم به ماه بعد»: مصرف حفظ می‌شود؛ حجم باقی‌مانده + حجم خرید قابل استفاده است (حجم کل = مصرف + باقی‌مانده + خرید)؛ انقضا = انقضای فعلی + روزهای خرید.

"
                . "🔁 «ریست زمان و اضافه کردن حجم قبلی»: مصرف حفظ می‌شود؛ حجم باقی‌مانده + حجم خرید؛ زمان باقی‌مانده حذف و انقضا = زمان اجرا + روزهای خرید.

"
                . "🔂 «ریست شدن حجم و اضافه شدن زمان»: مصرف صفر و حجم باقی‌مانده حذف می‌شود؛ حجم کل = فقط حجم خرید؛ انقضا = انقضای فعلی + روزهای خرید.

"
                . "🔄 «اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده»: مصرف صفر می‌شود اما حجم باقی‌مانده حذف نمی‌شود؛ حجم کل = حجم باقی‌مانده + حجم خرید؛ انقضا = انقضای فعلی + روزهای خرید.

"
                . "📌 «رزرو اشتراک»: سرویس فعلی تغییر نمی‌کند؛ ابتدا مدیر یکی از چهار روش تمدید را انتخاب می‌کند و سپس یک یا چند شرط فعال‌سازی (اتمام حجم، پایان زمان یا تاریخ مشخص) را تعیین می‌کند. رزرو با رسیدن اولین شرط انتخاب‌شده فعال می‌شود. مشتری فقط خرید را تأیید می‌کند و امکان تغییر تنظیمات را ندارد؛ حجم و زمان باقی‌مانده در لحظه فعال‌سازی محاسبه می‌شود.

"
                . "ℹ️ مصرف بیش از سقف قبلی هیچ‌گاه از حجم خریداری‌شده کم نمی‌شود. برای سرویس منقضی‌شده، روزهای خرید از زمان اجرا محاسبه و سرویس دوباره فعال می‌شود. سرویسی که توسط مدیر غیرفعال شده تمدید خودکار نمی‌شود.";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxExtendMethodGuide, $Methodextend, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('updateextendmethod', $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            $aarayvalid = array(
                'ریست حجم و زمان',
                'اضافه شدن زمان و حجم به ماه بعد',
                'ریست زمان و اضافه کردن حجم قبلی',
                'ریست شدن حجم و اضافه شدن زمان',
                'اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده',
                'رزرو اشتراک'
            );
            if (!in_array($text, $aarayvalid)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ روش تمدید نامعتبر می باشد از لیست زیر روش تمدید درست را انتخاب کنید", null, 'HTML');
                return;
            }
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($text == 'رزرو اشتراک') {
                \Faoxima\Services\Renewal\ReservationStore::ensureSchema($pdo);
                $rxReservationPanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select");
                if (!is_array($rxReservationPanel) || ($rxReservationPanel['type'] ?? '') === 'Manualsale') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reason_reservation_unsupported'), null, 'HTML');
                    return;
                }
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $panelName, "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                $rxReservationAdminView = \Faoxima\Services\Renewal\Reservations::rxReservationAdminView($rxReservationPanel, array_merge(\Faoxima\Services\Renewal\Reservations::rxReservationAdminState(\Faoxima\Services\Renewal\ReservationCriteria::reservationPanelSettings($rxReservationPanel)), ['selected' => false]));
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxReservationAdminView['text'], $rxReservationAdminView['keyboard'], 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "Methodextend", $text, "name_panel", $panelName);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $panelName, "id", $from_id);
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select");
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['Algortimeextend']['SaveData']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 3:
            $secret_key = \Faoxima\Core\Database\Db::select("admin", "*", "id_admin", $from_id, "select");
            $secret_key = base64_encode($secret_key['password']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "<code>$secret_key</code>", null, 'HTML');
            break;
        case 4:
            $token = bin2hex(random_bytes(16));
            require_once REFACTORED_LEGACY_ROOT . '/lib/ApiCredential.php';
            FaoximaApiCredential::write($token);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "توکن api شما : <code>$token</code>", null, 'HTML');
            sendDocument($from_id, 'api/documents.txt', "📌 داکیومنت api ربات
نکات :
۱ - در صورتی که به endpoint خاصی نیاز داشتید به اکانت پشتیبانی پیام دهید تا بررسی شود.");
            break;
        case 5:
            $webpanelListAdmins = \Faoxima\Core\Database\Db::select("admin", "*", null, null, "fetchAll");
            $webpanelListKeyboard = ['inline_keyboard' => []];
            foreach ($webpanelListAdmins as $webpanelListRow) {
                $webpanelListId = isset($webpanelListRow['id_admin']) ? trim($webpanelListRow['id_admin']) : '';
                if ($webpanelListId === '') {
                    continue;
                }
                $webpanelListUsername = trim((string)($webpanelListRow['username'] ?? '')) !== '' ? trim((string)($webpanelListRow['username'] ?? '')) : $webpanelListId;
                $webpanelListKeyboard['inline_keyboard'][] = [
                    ['text' => "👤 {$webpanelListUsername} — {$webpanelListId}", 'callback_data' => "webpanel_mgr_view_" . $webpanelListId],
                ];
            }
            $webpanelListKeyboard['inline_keyboard'][] = [
                ['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => "feat_backmenu"],
                ['text' => "🏠 منوی مدیریت", 'callback_data' => "adm_hub_main"],
            ];
            $webpanelListKeyboardJson = json_encode($webpanelListKeyboard);
            $webpanelListMsg = "✅ <b>پنل تحت وب</b>\n";
            $webpanelListMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $webpanelListMsg .= "برای مدیریت حساب پنل هر ادمین (نام کاربری، رمز عبور، آیپی ورود)، روی نام او بزنید.";
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $webpanelListMsg, $webpanelListKeyboardJson);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $webpanelListMsg, $webpanelListKeyboardJson, 'HTML');
            }
            break;
        case 6:
            $viewAdminId = trim($webpanelMgrViewMatch[1]);
            $viewAdminRow = \Faoxima\Core\Database\Db::select("admin", "*", "id_admin", $viewAdminId, "select");
            if (!$viewAdminRow) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                return;
            }
            [$viewIpList, $viewIpUnlimited] = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::getAdminIpLoginState($viewAdminId);
            $viewIsMain = trim($viewAdminId) === trim((string) $adminnumber);
            $viewIpStatus = $viewIpUnlimited ? "♾️ نامحدود" : (empty($viewIpList) ? "🔒 مسدود (هیچ آیپی تنظیم نشده)" : "📋 " . count($viewIpList) . " آیپی مجاز");
            $viewMsg = "👤 <b>مدیریت حساب پنل تحت وب</b>\n";
            $viewMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $viewMsg .= "🪪 شناسه عددی: <code>{$viewAdminId}</code>\n";
            $viewMsg .= "👤 نام کاربری: <code>" . htmlspecialchars((string)($viewAdminRow['username'] ?? '')) . "</code>\n";
            $viewMsg .= "🛡 نقش: " . htmlspecialchars((string)($viewAdminRow['rule'] ?? '')) . ($viewIsMain ? " (ادمین اصلی)" : "") . "\n";
            $viewMsg .= "🌐 آیپی ورود: {$viewIpStatus}\n";
            $viewMsg .= "━━━━━━━━━━━━━━━━━━━━";
            $viewKeyboard = ['inline_keyboard' => []];
            $viewKeyboard['inline_keyboard'][] = [
                ['text' => "🔐 اطلاعات ورود پنل", 'callback_data' => "webpanel_mgr_creds_" . $viewAdminId],
            ];
            $viewKeyboard['inline_keyboard'][] = [
                ['text' => "✏️ تغییر نام کاربری", 'callback_data' => "webpanel_mgr_uname_" . $viewAdminId],
            ];
            $viewKeyboard['inline_keyboard'][] = [
                ['text' => "🔑 ریست رمز عبور", 'callback_data' => "webpanel_mgr_resetpass_" . $viewAdminId],
            ];
            $viewKeyboard['inline_keyboard'][] = [
                ['text' => "🌐 تنظیم آیپی ورود اختصاصی", 'callback_data' => "webpanel_mgr_ip_" . $viewAdminId],
            ];
            $viewKeyboard['inline_keyboard'][] = [
                ['text' => "◀️ بازگشت به لیست ادمین‌ها", 'callback_data' => "webpanel_mgr_list"],
            ];
            $viewKeyboardJson = json_encode($viewKeyboard);
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $viewMsg, $viewKeyboardJson);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $viewMsg, $viewKeyboardJson, 'HTML');
            }
            break;
        case 7:
            $credsAdminId = trim($webpanelMgrCredsMatch[1]);
            $credsAdminRow = \Faoxima\Core\Database\Db::select("admin", "*", "id_admin", $credsAdminId, "select");
            if (!$credsAdminRow) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                return;
            }
            $credsPassword = $credsAdminRow['password'] ?? null;
            if ($credsPassword === null || $credsPassword === '') {
                $credsPassword = bin2hex(random_bytes(6));
                \Faoxima\Core\Database\Db::update("admin", "password", $credsPassword, "id_admin", $credsAdminId);
                \Faoxima\Core\Database\Db::update("admin", "password_hash", password_hash($credsPassword, PASSWORD_DEFAULT), "id_admin", $credsAdminId);
            }
            $credsUsername = trim((string)($credsAdminRow['username'] ?? ''));
            if ($credsUsername === '') {
                $credsUsername = $credsAdminId;
                \Faoxima\Core\Database\Db::update("admin", "username", $credsUsername, "id_admin", $credsAdminId);
            }
            $credsBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ بازگشت", 'callback_data' => "webpanel_mgr_view_" . $credsAdminId]]]]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔐 <b>اطلاعات ورود پنل تحت وب</b>

🔗آدرس ورود : https://$domainhosts/panel
👤نام کاربری :  <code>" . htmlspecialchars($credsUsername) . "</code>
🔑رمز عبور :  <code>{$credsPassword}</code>", $credsBackKb, 'HTML');
            break;
        case 8:
            $targetAdminId = trim($webpanelMgrUnameMatch[1]);
            if (!\Faoxima\Core\Database\Db::select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $targetAdminId, "id", $from_id);
            $unameBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ انصراف", 'callback_data' => "webpanel_mgr_view_" . $targetAdminId]]]]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام کاربری جدید را ارسال کنید.", $unameBackKb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("webpanel_mgr_get_uname", $from_id);
            break;
        case 9:
            if (!isset($update['message']) && empty($text)) { return; }
            $newUsername = trim($text);
            $targetAdminId = trim((string)($user['Processing_value'] ?? ''));
            if ($newUsername === '' || $targetAdminId === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام کاربری نامعتبر است.", null, 'HTML');
                return;
            }
            if (!\Faoxima\Core\Database\Db::select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("admin", "username", $newUsername, "id_admin", $targetAdminId);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ نام کاربری با موفقیت به «" . htmlspecialchars($newUsername) . "» تغییر یافت.", $keyboardadmin, 'HTML');
            break;
        case 10:
            $targetAdminId = trim($webpanelMgrResetMatch[1]);
            if (!\Faoxima\Core\Database\Db::select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                return;
            }
            $newAdminPassword = bin2hex(random_bytes(6));
            \Faoxima\Core\Database\Db::update("admin", "password", $newAdminPassword, "id_admin", $targetAdminId);
            \Faoxima\Core\Database\Db::update("admin", "password_hash", password_hash($newAdminPassword, PASSWORD_DEFAULT), "id_admin", $targetAdminId);
            $resetBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ بازگشت", 'callback_data' => "webpanel_mgr_view_" . $targetAdminId]]]]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ رمز عبور با موفقیت ریست شد.\n\n🔑 رمز عبور جدید: <code>{$newAdminPassword}</code>", $resetBackKb, 'HTML');
            if ($targetAdminId !== $from_id) {
                @sendmessage($targetAdminId, "🔑 رمز عبور پنل تحت وب شما توسط یک ادمین دیگر تغییر یافت.\n\nرمز عبور جدید: <code>{$newAdminPassword}</code>", null, 'HTML');
            }
            break;
        case 11:
            $targetAdminId = trim($webpanelMgrIpMatch[1]);
            if (!\Faoxima\Core\Database\Db::select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                return;
            }
            [$mgrIpList, $mgrIpUnlimited] = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::getAdminIpLoginState($targetAdminId);
            $mgrIpMsg = "🌐 <b>آیپی ورود اختصاصی</b>\n";
            $mgrIpMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
            if ($mgrIpUnlimited) {
                $mgrIpMsg .= "♾️ <b>حالت نامحدود فعال است.</b>\nورود شما از هر آیپی‌ای آزاد است.\n";
            } elseif (empty($mgrIpList)) {
                $mgrIpMsg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\nدر این حالت ورود شما از پنل وب مسدود است.\n";
            } else {
                $mgrIpMsg .= "📋 آیپی‌های مجاز ورود:\n";
                foreach ($mgrIpList as $i => $ip) {
                    $mgrIpMsg .= ($i + 1) . ". <code>" . htmlspecialchars($ip) . "</code>\n";
                }
            }
            $mgrIpMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $mgrIpMsg .= "➕ برای افزودن آیپی جدید، دکمه <b>افزودن آیپی</b> را بزنید.";
            $mgrIpKeyboardJson = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildWebpanelIpLoginKeyboard($targetAdminId, $mgrIpList, $mgrIpUnlimited);
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $mgrIpMsg, $mgrIpKeyboardJson);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $mgrIpMsg, $mgrIpKeyboardJson, 'HTML');
            }
            break;
        }

        return true;
    }
}
