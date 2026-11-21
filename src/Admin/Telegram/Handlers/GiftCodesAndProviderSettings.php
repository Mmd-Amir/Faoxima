<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $CartManage, $MethodUsername, $MethodUsernameRemna, $_clpg_m, $adminAction, $adminExecution, $adminrulecheck, $backadmin, $from_id, $getNameCustomKb, $keyboardadmin, $keyboardprotocollist, $optionJovad, $optionMarzban, $pdo, $protocoldata, $shopkeyboard, $textbotlang, $update, $user.
 * Inherited or exported by reference: $status, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class GiftCodesAndProviderSettings
{
    public static function handle(
        $CartManage,
        $MethodUsername,
        $MethodUsernameRemna,
        $_clpg_m,
        $adminAction,
        $adminExecution,
        $adminrulecheck,
        $backadmin,
        $from_id,
        $getNameCustomKb,
        $keyboardadmin,
        $keyboardprotocollist,
        $optionJovad,
        $optionMarzban,
        $pdo,
        $protocoldata,
        $shopkeyboard,
        &$status,
        &$stmt,
        &$text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 2:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 4:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 6:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['RemoveProtocol'], $keyboardprotocollist, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('removeprotocol', $from_id);
            break;
        case 8:
            if (!in_array($text, $protocoldata)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['invalidProtocol'], null, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['RemovedProtocol'], $optionMarzban, 'HTML');
            $stmt = $pdo->prepare("DELETE FROM protocol WHERE NameProtocol = :protocol");
            $stmt->bindParam(':protocol', $text, PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 9:
            $text_username = "⭕️ ساخت نام کاربری برای اکانت ها را از دکمه زیر انتخاب نمایید.

⚠️ در صورتی که کاربری نام کاربری نداشته باشه کلمه انتخابی توسط شما ثبت خواهد شد جای نام کاربری اعمال خواهد شد.

⚠️ در صورتی که نام کاربری وجود داشته باشه یک عدد رندوم به نام کاربری اضافه خواهد شد";
            $rxMuPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string) ($user['Processing_value'] ?? '');
            $rxMuPanelRow = $rxMuPanelName !== '' ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxMuPanelName, "select") : false;
            $rxMuKeyboard = (is_array($rxMuPanelRow) && ($rxMuPanelRow['type'] ?? '') === 'remnawave') ? $MethodUsernameRemna : $MethodUsername;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_username, $rxMuKeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('updatemethodusername', $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $allowedMethods = [
                "آیدی عددی + حروف و عدد رندوم",
                "نام کاربری + حروف و عدد رندوم",
                "نام کاربری دلخواه + عدد رندوم",
                "متن دلخواه کاربر + رندوم",
                "متن دلخواه + عدد رندوم",
                "متن دلخواه + عدد ترتیبی",
                "نام کاربری + عدد به ترتیب",
                "آیدی عددی+عدد ترتیبی",
                "متن دلخواه نماینده + عدد ترتیبی",
                "نام کاربری دلخواه",
                "آیدی عددی",
            ];
            if (!in_array($text, $allowedMethods, true)) {
                $rxMuReshowRow = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select");
                $rxMuReshowKb = (is_array($rxMuReshowRow) && ($rxMuReshowRow['type'] ?? '') === 'remnawave') ? $MethodUsernameRemna : $MethodUsername;
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ گزینه نامعتبر است. لطفاً یکی از دکمه‌های زیر را انتخاب کنید.", $rxMuReshowKb, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "MethodUsername", $text, "name_panel", $panelName);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $panelName, "id", $from_id);
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select");
            if ($text == "متن دلخواه + عدد رندوم" || $text == "متن دلخواه + عدد ترتیبی" || $text == "متن دلخواه نماینده + عدد ترتیبی") {
                \Faoxima\Telegram\Navigation\Steps::step('getnamecustom', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['customnamesend'], $getNameCustomKb, 'HTML');
                return;
            }
            if ($text == "نام کاربری + حروف و عدد رندوم" || $text == "نام کاربری + عدد به ترتیب") {
                \Faoxima\Telegram\Navigation\Steps::step('getnamecustom', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در صورتی که کاربر نام کاربری نداشت چه اسمی ثبت شود؟", $getNameCustomKb, 'HTML');
                return;
            }
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['AlgortimeUsername']['SaveData']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 11:
            if (!isset($update['message']) && empty($text)) { return; }
            if ($text === '🎲 ساخت رندوم خودکار') {
                $text = strtolower('usr' . bin2hex(random_bytes(2)));
            }
            $text = str_replace('_', '-', $text);
            if (!preg_match('/^[A-Za-z0-9-]{2,32}$/', $text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidname'], $getNameCustomKb, 'html');
                return;
            }
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "namecustom", $text, "name_panel", $panelName);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $panelName, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select");
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['savedname']);
            break;
        case 12:
            $rxJovadChPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string) ($user['Processing_value'] ?? '');
            $rxJovadChPanel = $rxJovadChPanelName !== '' ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxJovadChPanelName, "select") : false;
            if (!is_array($rxJovadChPanel) || ($rxJovadChPanel['type'] ?? '') !== 'jovadpanel') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'] ?? "❌ پنل مورد نظر یافت نشد.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $rxJovadChPanelName, "id", $from_id);
            $rxJovadChCurrent = trim((string) ($rxJovadChPanel['jovad_channels'] ?? ''));
            $rxJovadChText = "📡 کانال‌های پیش‌فرض پنل\n\n"
                . "🔢 مقدار فعلی: " . ($rxJovadChCurrent !== '' ? $rxJovadChCurrent : 'تنظیم نشده (از پیش‌فرض API استفاده می‌شود)') . "\n\n"
                . "این مقدار فقط وقتی اعمال می‌شود که محصول کانال اختصاصی نداشته باشد.\n"
                . "کانال‌های مجاز: xray، wireguard، openvpn، dns\n"
                . "نمونه: xray,wireguard,dns\n\n"
                . "برای حذف مقدار و بازگشت به پیش‌فرض API عبارت «پیش‌فرض» را ارسال کنید.";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxJovadChText, $optionJovad, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('jovad_channels', $from_id);
            break;
        case 13:
            $rxJovadChPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string) ($user['Processing_value'] ?? '');
            $rxJovadChPanel = $rxJovadChPanelName !== '' ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxJovadChPanelName, "select", ['cache' => false]) : false;
            if (!is_array($rxJovadChPanel) || ($rxJovadChPanel['type'] ?? '') !== 'jovadpanel') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'] ?? "❌ پنل مورد نظر یافت نشد.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $rxJovadChInput = trim((string) $text);
            if (in_array($rxJovadChInput, ['پیش‌فرض', 'پیش فرض', 'default', 'clear', '-'], true)) {
                \Faoxima\Core\Database\Db::update("marzban_panel", "jovad_channels", '', "name_panel", $rxJovadChPanelName);
                \Faoxima\Services\Panels\PanelSetup::outtypepanel('jovadpanel', "✅ کانال‌های پیش‌فرض پنل حذف شد؛ سفارش‌های جدید از پیش‌فرض API استفاده می‌کنند.");
                return;
            }
            $rxJovadChParsed = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelParseChannels($rxJovadChInput);
            if (empty($rxJovadChParsed['channels']) || !empty($rxJovadChParsed['invalid'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کانال معتبر انتخاب کنید: xray، wireguard، openvpn یا dns؛ چند کانال را با کامای انگلیسی جدا کنید.", $optionJovad, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "jovad_channels", implode(',', $rxJovadChParsed['channels']), "name_panel", $rxJovadChPanelName);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel('jovadpanel', "✅ کانال‌های پیش‌فرض پنل ذخیره شد: " . implode(',', $rxJovadChParsed['channels']) . "\nاین مقدار فقط برای سفارش‌های جدید اعمال می‌شود.");
            break;
        case 14:
            $rxJovadDevPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string) ($user['Processing_value'] ?? '');
            $rxJovadDevPanel = $rxJovadDevPanelName !== '' ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxJovadDevPanelName, "select") : false;
            if (!is_array($rxJovadDevPanel) || ($rxJovadDevPanel['type'] ?? '') !== 'jovadpanel') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'] ?? "❌ پنل مورد نظر یافت نشد.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $rxJovadDevPanelName, "id", $from_id);
            $rxJovadDevStatus = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelUnlimitedStatus($rxJovadDevPanel);
            $rxJovadDevCurrent = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelUnlimitedDevices($rxJovadDevPanel);
            $rxJovadDevText = "👥 تعداد دستگاه اشتراک نامحدود\n\n"
                . "🔢 مقدار فعلی: " . ($rxJovadDevCurrent > 0 ? (string) $rxJovadDevCurrent : 'تنظیم نشده (از پیش‌فرض پنل استفاده می‌شود)') . "\n\n";
            if ($rxJovadDevStatus['status'] !== true) {
                $rxJovadDevText .= "⚠️ دریافت پلن‌های مجاز از پنل ناموفق بود: " . (string) $rxJovadDevStatus['msg'] . "\n\n";
            } else {
                $rxJovadDevTiers = [];
                foreach ($rxJovadDevStatus['options'] as $rxJovadDevOption) {
                    $rxJovadDevTiers[] = (int) $rxJovadDevOption['devices'];
                }
                $rxJovadDevText .= (empty($rxJovadDevTiers) ? "⚠️ پنل هیچ پلن دستگاهی اعلام نکرده است." : "🎚 پلن‌های مجاز پنل: " . implode('، ', $rxJovadDevTiers)) . "\n";
                if ($rxJovadDevStatus['default_devices'] > 0) {
                    $rxJovadDevText .= "👥 پیش‌فرض پنل: {$rxJovadDevStatus['default_devices']}\n";
                }
                $rxJovadDevText .= "\n";
            }
            $rxJovadDevText .= "📌 تعداد دستگاه را به صورت عدد ارسال کنید. این مقدار فقط روی سفارش‌های جدید اثر دارد.";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxJovadDevText, $optionJovad, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('jovad_devices', $from_id);
            break;
        case 15:
            $rxJovadDevPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string) ($user['Processing_value'] ?? '');
            $rxJovadDevPanel = $rxJovadDevPanelName !== '' ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxJovadDevPanelName, "select", ['cache' => false]) : false;
            if (!is_array($rxJovadDevPanel) || ($rxJovadDevPanel['type'] ?? '') !== 'jovadpanel') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'] ?? "❌ پنل مورد نظر یافت نشد.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $rxJovadDevValue = trim((string) $text);
            if (!preg_match('/^\d{1,3}$/', $rxJovadDevValue)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ تعداد دستگاه باید یک عدد صحیح باشد. مقدار را دوباره ارسال کنید.", $optionJovad, 'HTML');
                return;
            }
            $rxJovadDevTier = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelUnlimitedTier($rxJovadDevPanel, (int) $rxJovadDevValue, 0);
            if ($rxJovadDevTier['status'] !== true) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ " . (string) $rxJovadDevTier['msg'], $optionJovad, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "jovad_devices", (string) (int) $rxJovadDevValue, "name_panel", $rxJovadDevPanelName);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel('jovadpanel', "✅ تعداد دستگاه اشتراک نامحدود روی " . (int) $rxJovadDevValue . " ذخیره شد و فقط برای سفارش‌های جدید اعمال می‌شود.");
            break;
        case 16:
            \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cart_settings', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $CartManage, 'HTML');
            break;
        case 17:
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $_cl_page = isset($_clpg_m[1]) ? max(1, (int)$_clpg_m[1]) : 1;
            $_cl_per = 5;
            $_cl_off = ($_cl_page - 1) * $_cl_per;
            $_cl_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM card_number");
            $_cl_count_stmt->execute();
            $_cl_total = (int)$_cl_count_stmt->fetchColumn();
            $_cl_pages = max(1, (int)ceil($_cl_total / $_cl_per));
            $stmt = $pdo->prepare("SELECT id, cardnumber, namecard, is_active, is_visible FROM card_number ORDER BY created_at DESC LIMIT {$_cl_per} OFFSET {$_cl_off}");
            $stmt->execute();
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($_cl_total === 0) {
                $_emk = [
                    [['text' => "➕ افزودن شماره کارت", 'callback_data' => 'card_add_new']],
                ];
                if ($adminrulecheck['rule'] == "administrator") {
                    $_emk[] = [['text' => "⚙️ روش نمایش به کاربر", 'callback_data' => 'card_display_mode_menu']];
                }
                $_emk[] = [['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back']];
                $inline_kb = json_encode(['inline_keyboard' => $_emk], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📭 هیچ شماره کارتی موجود نیست\n\n➕ برای افزودن کارت جدید اینجا کلیک کنید:", $inline_kb, 'HTML');
            } else {
                $inline_kb = ['inline_keyboard' => []];
                foreach ($cards as $card) {
                    $status = ($card['is_active'] == 1) ? '✅' : '❌';
                    $display_num = substr($card['cardnumber'], 0, 4) . "****" . substr($card['cardnumber'], -4);
                    $inline_kb['inline_keyboard'][] = [['text' => "$status {$card['namecard']} ($display_num)", 'callback_data' => 'card_select_' . $card['id']]];
                }
                $_cl_pg_row = [];
                if ($_cl_page > 1) {
                    $_cl_pg_row[] = ['text' => "◀️ قبل", 'callback_data' => 'card_list_page_' . ($_cl_page - 1)];
                }
                if ($_cl_page < $_cl_pages) {
                    $_cl_pg_row[] = ['text' => "▶️ بعد", 'callback_data' => 'card_list_page_' . ($_cl_page + 1)];
                }
                if (!empty($_cl_pg_row)) {
                    $inline_kb['inline_keyboard'][] = $_cl_pg_row;
                }
                $inline_kb['inline_keyboard'][] = [['text' => "➕ افزودن شماره کارت جدید", 'callback_data' => 'card_add_new']];
                if ($adminrulecheck['rule'] == "administrator") {
                    $inline_kb['inline_keyboard'][] = [['text' => "⚙️ روش نمایش به کاربر", 'callback_data' => 'card_display_mode_menu']];
                }
                $inline_kb['inline_keyboard'][] = [['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back']];
                $_cl_pg_label = $_cl_pages > 1 ? "\n\n📄 صفحه {$_cl_page} از {$_cl_pages}" : "";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📋 کارت‌های ثبت‌شده:\n\n(برای مدیریت یک کارت روی آن کلیک کنید){$_cl_pg_label}", json_encode($inline_kb, JSON_UNESCAPED_UNICODE), 'HTML');
            }
            break;
        }

        return true;
    }
}
