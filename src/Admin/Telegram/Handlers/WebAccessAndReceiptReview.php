<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $CartManage, $ManagePanel, $adminAction, $adminExecution, $backadmin, $backuser, $callback_query_id, $dataget, $datagetr, $errorreport, $from_id, $json_list_marzban_panel, $json_list_product_list_admin, $keyboardadmin, $list_card_remove, $message_id, $pdo, $setting, $shopkeyboard, $textbotlang, $update, $user, $webpanelMgrIpAddMatch, $webpanelMgrIpDelMatch, $webpanelMgrIpUnlimMatch.
 * Inherited or exported by reference: $datatextbot, $id_user, $info_product, $marzban_list_get, $randomString, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class WebAccessAndReceiptReview
{
    public static function handle(
        $CartManage,
        $ManagePanel,
        $adminAction,
        $adminExecution,
        $backadmin,
        $backuser,
        $callback_query_id,
        $dataget,
        $datagetr,
        &$datatextbot,
        $errorreport,
        $from_id,
        &$id_user,
        &$info_product,
        $json_list_marzban_panel,
        $json_list_product_list_admin,
        $keyboardadmin,
        $list_card_remove,
        &$marzban_list_get,
        $message_id,
        $pdo,
        &$randomString,
        $setting,
        $shopkeyboard,
        &$stmt,
        &$text,
        $textbotlang,
        $update,
        $user,
        $webpanelMgrIpAddMatch,
        $webpanelMgrIpDelMatch,
        $webpanelMgrIpUnlimMatch
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $targetAdminId = trim($webpanelMgrIpUnlimMatch[2]);
            if (!\Faoxima\Core\Database\Db::select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                return;
            }
            if ($webpanelMgrIpUnlimMatch[1] === 'on') {
                \Faoxima\Core\Database\Db::update("admin", "iplogin", "*", "id_admin", $targetAdminId);
            } else {
                \Faoxima\Core\Database\Db::update("admin", "iplogin", json_encode([]), "id_admin", $targetAdminId);
            }
            [$mgrIpList, $mgrIpUnlimited] = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::getAdminIpLoginState($targetAdminId);
            $mgrIpMsg = "🌐 <b>آیپی ورود اختصاصی</b>\n━━━━━━━━━━━━━━━━━━━━\n";
            $mgrIpMsg .= $mgrIpUnlimited ? "♾️ <b>حالت نامحدود فعال است.</b>\n" : (empty($mgrIpList) ? "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n" : "📋 " . count($mgrIpList) . " آیپی مجاز تنظیم شده است.\n");
            $mgrIpMsg .= "━━━━━━━━━━━━━━━━━━━━";
            $mgrIpKeyboardJson = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildWebpanelIpLoginKeyboard($targetAdminId, $mgrIpList, $mgrIpUnlimited);
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $mgrIpMsg, $mgrIpKeyboardJson);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $mgrIpMsg, $mgrIpKeyboardJson, 'HTML');
            }
            break;
        case 2:
            $targetAdminId = trim($webpanelMgrIpAddMatch[1]);
            if (!\Faoxima\Core\Database\Db::select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $targetAdminId, "id", $from_id);
            $ipAddBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ انصراف", 'callback_data' => "webpanel_mgr_ip_" . $targetAdminId]]]]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 آیپی جدید را ارسال کنید.\n<i>مثال: 1.2.3.4</i>", $ipAddBackKb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("webpanel_mgr_get_ip", $from_id);
            break;
        case 3:
            if (!isset($update['message']) && empty($text)) { return; }
            $newIp = trim($text);
            $targetAdminId = trim((string)($user['Processing_value'] ?? ''));
            if ($targetAdminId === '' || !\Faoxima\Core\Database\Db::select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (!filter_var($newIp, FILTER_VALIDATE_IP)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آیپی وارد شده معتبر نیست. لطفاً یک آیپی صحیح ارسال کنید.\nمثال: <code>1.2.3.4</code>", null, 'HTML');
                return;
            }
            [$curIpList, $curIpUnlimited] = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::getAdminIpLoginState($targetAdminId);
            if ($curIpUnlimited) {
                $curIpList = [];
            }
            if (in_array($newIp, $curIpList, true)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ این آیپی قبلاً در لیست وجود دارد.", null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $curIpList[] = $newIp;
            \Faoxima\Core\Database\Db::update("admin", "iplogin", json_encode(array_values($curIpList)), "id_admin", $targetAdminId);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آیپی <code>" . htmlspecialchars($newIp) . "</code> با موفقیت اضافه شد.", $keyboardadmin, 'HTML');
            break;
        case 4:
            $targetAdminId = trim($webpanelMgrIpDelMatch[1]);
            $delIndex = (int) $webpanelMgrIpDelMatch[2];
            if (!\Faoxima\Core\Database\Db::select("admin", "id_admin", "id_admin", $targetAdminId, "select")) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                return;
            }
            [$curIpList, $curIpUnlimited] = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::getAdminIpLoginState($targetAdminId);
            if ($curIpUnlimited || !isset($curIpList[$delIndex])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آیپی مورد نظر یافت نشد.", null, 'HTML');
                return;
            }
            array_splice($curIpList, $delIndex, 1);
            \Faoxima\Core\Database\Db::update("admin", "iplogin", json_encode(array_values($curIpList)), "id_admin", $targetAdminId);
            [$mgrIpList, $mgrIpUnlimited] = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::getAdminIpLoginState($targetAdminId);
            $mgrIpMsg = "🌐 <b>آیپی ورود اختصاصی</b>\n━━━━━━━━━━━━━━━━━━━━\n✅ آیپی حذف شد.\n━━━━━━━━━━━━━━━━━━━━";
            $mgrIpKeyboardJson = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildWebpanelIpLoginKeyboard($targetAdminId, $mgrIpList, $mgrIpUnlimited);
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $mgrIpMsg, $mgrIpKeyboardJson);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $mgrIpMsg, $mgrIpKeyboardJson, 'HTML');
            }
            break;
        case 5:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $iduser, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['towstep'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getusernameconfig', $from_id);
            break;
        case 6:
            $text = trim($text);
            if ($text === '' || preg_match('/\s/', $text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['Invalidusername'], $backuser, 'html');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $text, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['threestep'], $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getnamepanelconfig', $from_id);
            break;
        case 7:
            $panelRow = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : null;
            $canonicalLoc = is_array($panelRow) && !empty($panelRow['name_panel']) ? $panelRow['name_panel'] : $text;
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", $canonicalLoc, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['fourstep'], $json_list_product_list_admin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('stependforaddorder', $from_id);
            break;
        case 8:
            $sql = "SELECT * FROM product  WHERE name_product = :name_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
            $stmt->bindParam(':location', $user['Processing_value_tow'], PDO::PARAM_STR);
            $stmt->execute();
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value_tow'], "select");
            $DataUserOut = $ManagePanel->DataUser($user['Processing_value_tow'], $user['Processing_value_one']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این نام کاربری روی پنل انتخابی وجود ندارد. لطفا نام کاربری را بررسی و دوباره ارسال کنید یا ابتدا آن را روی پنل بسازید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $dupStmt = $pdo->prepare("SELECT 1 FROM invoice WHERE Service_location = :location AND username = CONVERT(:username USING utf8mb4) COLLATE utf8mb4_bin LIMIT 1");
            $dupStmt->bindParam(':location', $user['Processing_value_tow'], PDO::PARAM_STR);
            $dupStmt->bindParam(':username', $user['Processing_value_one'], PDO::PARAM_STR);
            $dupStmt->execute();
            if ($dupStmt->fetch(PDO::FETCH_ASSOC) !== false) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این سرویس از قبل داخل ربات ثبت شده است.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $DataUserOut['configs'] = $DataUserOut['links'];
            $date = time();
            $randomString = bin2hex(random_bytes(4));
            $notifctions = json_encode(array(
                'volume' => false,
                'time' => false,
            ));
            $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
            $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status,notifctions,ip_limit) VALUES (:id_user, :id_invoice, :username, :time_sell, :Service_location, :name_product, :price_product, :Volume, :Service_time, :Status,:notifctions,:ip_limit)");
            $Status = "active";
            $stmt->bindParam(':id_user', $user['Processing_value'], PDO::PARAM_STR);
            $stmt->bindParam(':id_invoice', $randomString, PDO::PARAM_STR);
            $stmt->bindParam(':username', $user['Processing_value_one'], PDO::PARAM_STR);
            $stmt->bindParam(':time_sell', $date, PDO::PARAM_STR);
            $stmt->bindParam(':Service_location', $user['Processing_value_tow'], PDO::PARAM_STR);
            $stmt->bindParam(':name_product', $info_product['name_product'], PDO::PARAM_STR);
            $stmt->bindParam(':price_product', $info_product['price_product'], PDO::PARAM_STR);
            $stmt->bindParam(':Volume', $info_product['Volume_constraint'], PDO::PARAM_STR);
            $stmt->bindParam(':Service_time', $info_product['Service_time'], PDO::PARAM_STR);
            $stmt->bindParam(':ip_limit', $invoiceIpLimit, PDO::PARAM_STR);
            $stmt->bindParam(':Status', $Status, PDO::PARAM_STR);
            $stmt->bindParam(':notifctions', $notifctions, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ سرویس روی پنل پیدا شد، اما ثبت آن در ربات ناموفق بود؛ احتمالا این سرویس قبلا در ربات ثبت شده است.", null, 'HTML');
                $texterros = "خطا در ثبت سفارش محلی برای سرویس موجود روی پنل (احتمالا تداخل نام کاربری)
<blockquote>نام کاربری: {$user['Processing_value_one']}</blockquote>
<blockquote>آیدی ادمین: $from_id</blockquote>
<blockquote>نام پنل: {$marzban_list_get['name_panel']}</blockquote>";
                if (strlen($setting['Channel_Report']) > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $errorreport,
                        'text' => $texterros,
                        'parse_mode' => "HTML"
                    ]);
                }
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? \Faoxima\Services\Delivery\ServiceDelivery::rxResolveConnectionLink($marzban_list_get, $DataUserOut['subscription_url'], $DataUserOut['file_ext'] ?? null) : "";
            $config = "";
            if ($marzban_list_get['config'] == "onconfig" && is_array($DataUserOut['configs'])) {
                foreach ($DataUserOut['configs'] as $link) {
                    $config .= "\n" . $link;
                }
            }
            $Shoppinginfo = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
                    ]
                ]
            ]);
            $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
            $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
            if (intval($info_product['Service_time']) == 0)
                $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
            if (intval($info_product['Volume_constraint']) == 0)
                $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
            $textcreatuser = str_replace('{username}', "<code>{$DataUserOut['username']}</code>", $datatextbot['textafterpay']);
            $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
            $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
            $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
            $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
            $textcreatuser = \Faoxima\Core\Database\Db::applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
            if (intval($info_product['Volume_constraint']) == 0) {
                $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
            }
            if ($marzban_list_get['type'] == "Manualsale") {
                $textcreatuser = str_replace('{password}', $DataUserOut['subscription_url'], $textcreatuser);
                \Faoxima\Core\Database\Db::update("invoice", "user_info", $DataUserOut['subscription_url'], "id_invoice", $randomString);
            }
            \Faoxima\Services\Delivery\ServiceDelivery::sendMessageService($marzban_list_get, $DataUserOut['configs'], $output_config_link, $DataUserOut['username'], $Shoppinginfo, $textcreatuser, $randomString, $user['Processing_value']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['fivestep'], $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 9:
            $PaySetting = \Faoxima\Core\Database\Db::select("shopSetting", "value", "Namevalue", "minbalancebuybulk", "select")['value'];
            $textmin = "📌 حداقل مبلغی که می خواهید کاربر  خرید انبوه کند را ارسال کنید.

مبلغ فعلی : $PaySetting";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textmin, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('minbalancebulk', $from_id);
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $shopkeyboard, 'HTML');
            \Faoxima\Core\Database\Db::update("shopSetting", "value", $text, "Namevalue", "minbalancebuybulk");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 شماره کارتی که می خواهید حذف کنید را ارسال نمایید.", $list_card_remove, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getcardremove', $from_id);
            break;
        case 12:
            $stmt = $pdo->prepare("DELETE FROM card_number WHERE cardnumber = :cardnumber");
            $stmt->bindParam(':cardnumber', $text, PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ شماره کارت با موفقیت حذف گردید.", $CartManage, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 13:

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
                        ['text' => "✅ بله، رد کن", 'callback_data' => "cfmreja_" . $id_user],
                        ['text' => "🔙 لغو", 'callback_data' => "cnclagentreq_" . $id_user],
                    ],
                ]
            ], JSON_UNESCAPED_UNICODE);
            $textConfirm = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
            $textConfirm .= "\n\n⚠️ آیا از <b>رد</b> این درخواست اطمینان دارید؟";
            Editmessagetext($from_id, $message_id, $textConfirm, $confirmKeyboard);
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "برای تایید نهایی روی «بله، رد کن» بزنید.",
                'show_alert' => false,
                'cache_time' => 0,
            ));
            break;
        }

        return true;
    }
}
