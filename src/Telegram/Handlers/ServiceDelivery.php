<?php

namespace Faoxima\Telegram\Handlers;

use CURLFile;

/**
 * Handler stage. Inherited by value: $ManagePanel, $callback_query_id, $dataget, $datatextbot, $from_id, $message_id, $otherreport, $rxQrDelivery, $rxSubQrDelivery, $setting, $telegramRoute, $text, $textbotlang, $textsub.
 * Inherited or exported by reference: $configDelivery, $id_invoice, $marzban_list_get, $nameloc, $page, $randomString.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceDelivery
{
    public static function handle(
        $ManagePanel,
        $callback_query_id,
        &$configDelivery,
        $dataget,
        $datatextbot,
        $from_id,
        &$id_invoice,
        &$marzban_list_get,
        $message_id,
        &$nameloc,
        $otherreport,
        &$page,
        &$randomString,
        $rxQrDelivery,
        $rxSubQrDelivery,
        $setting,
        $telegramRoute,
        $text,
        $textbotlang,
        $textsub
    ) {
        switch ($telegramRoute) {
        case 'info_qr':

            if (method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') && \Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled()) {
                if (isset($callback_query_id)) {
                    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
                }
                return;
            }
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler infocard_qr on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'infocard_qr',
                    ]);
                }
                $nameloc = false;
            }
            if ($nameloc == false) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if (($marzban_list_get['type'] ?? '') === 'jovadpanel' && ($marzban_list_get['sublink'] ?? '') !== 'onsublink') {
                sendmessage($from_id, $datatextbot['dyn_errors_feature_unavailable_short'] ?? "❌ این قابلیت در دسترس نیست.", null, 'html');
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful" || empty($DataUserOut['subscription_url'])) {
                sendmessage($from_id, $datatextbot['dyn_errors_qr_unavailable'] ?? "❌ امکان دریافت QR Code در حال حاضر وجود ندارد.", null, 'html');
                return;
            }
            $subscriptionurl = $DataUserOut['subscription_url'];
            $randomString = bin2hex(random_bytes(3));
            $urlimage = "$from_id$randomString.png";
            $qrCode = \Faoxima\Telegram\Support\QrAndKeys::createqrcode($subscriptionurl);
            if ($qrCode === null) {
                sendmessage($from_id, $datatextbot['dyn_errors_qr_too_long'] ?? "❌ ساخت QR کد برای این لینک ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از دکمهٔ کپی/متن بالا استفاده کنید.", null, 'HTML');
                sendmessage($from_id, "<code>{$subscriptionurl}</code>", \Faoxima\Telegram\Support\Transport::rx_copy_button_keyboard($subscriptionurl, '📋 کپی لینک اشتراک', null, 'service:' . $nameloc['id_invoice'] . ':copy_subscription'), 'HTML');
                return;
            }
            file_put_contents($urlimage, $qrCode->getString());
            \Faoxima\Telegram\Support\QrAndKeys::addBackgroundImage($urlimage, $qrCode, 'images.jpg');
            $subQrPayload = [
                'chat_id' => $from_id,
                'photo' => new CURLFile($urlimage),
                'caption' => '<code>' . htmlspecialchars($subscriptionurl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>',
                'parse_mode' => "HTML",
            ];
            $subQrCopyKb = \Faoxima\Telegram\Support\Transport::rx_copy_button_keyboard($subscriptionurl, '📋 کپی لینک اشتراک', null, 'service:' . $nameloc['id_invoice'] . ':copy_subscription');
            if ($subQrCopyKb !== null) {
                $subQrPayload['reply_markup'] = $subQrCopyKb;
            }
            rxSendMediaWithCaption('sendphoto', $subQrPayload, null, $rxSubQrDelivery, true);
            unlink($urlimage);
            if (isset($callback_query_id)) {
                $rxSubQrAnswer = $rxSubQrDelivery['delivery_status'] === 'completed'
                    ? '✅ QR Code ارسال شد'
                    : \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($rxSubQrDelivery['delivery_status'], '❌ ارسال QR ناموفق بود');
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $rxSubQrAnswer,
                    'show_alert' => false,
                    'cache_time' => 0,
                ]);
            }
            break;
        case 'subscription':
            if (is_string($text) && $text !== '' && $text[0] == "/") {
                $id_invoice = explode(' ', $text)[1];
                $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $id_invoice, "select");
                if ($nameloc['id_user'] != $from_id) {
                    $nameloc = false;
                }
            } else {
                $id_invoice = $dataget[1];
                $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            }
            if ($nameloc == false)
                return;
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmMaybeShowStockInvoiceDetails') && \Faoxima\Services\Fulfillment\StockPresentation::nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { \Faoxima\Telegram\Navigation\Steps::step('home', $from_id); return; }
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStopIfServicePanelBlocked') && \Faoxima\Services\Fulfillment\StockPresentation::nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if (($marzban_list_get['type'] ?? '') === 'jovadpanel' && ($marzban_list_get['sublink'] ?? '') !== 'onsublink') {
                sendmessage($from_id, $datatextbot['dyn_errors_feature_unavailable_short'] ?? "❌ این قابلیت در دسترس نیست.", null, 'html');
                return;
            }
            if (($marzban_list_get['type'] ?? '') == "Manualsale") {
                $manualDelivery = \Faoxima\Services\Fulfillment\DeliveryState::manualsaleDelivery($nameloc['username']);
                if (trim((string) $manualDelivery['sub']) === '') {
                    sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
                    return;
                }
                $manualBackRow = [[
                    'text' => $textbotlang['users']['stateus']['backinfo'],
                    'callback_data' => "productcheckdata",
                ]];
                $bakinfos = \Faoxima\Telegram\Support\Transport::rx_copy_button_keyboard($manualDelivery['sub'], '📋 کپی لینک اشتراک', [$manualBackRow], 'service:' . $nameloc['id_invoice'] . ':copy_subscription');
                if ($bakinfos === null) {
                    $bakinfos = json_encode(['inline_keyboard' => [$manualBackRow]]);
                }
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $nameloc['username'], "id", $from_id);
                sendmessage($from_id, "
{$textbotlang['users']['stateus']['linksub']}

<code>{$manualDelivery['sub']}</code>", $bakinfos, 'HTML');
                return;
            }
            $Check_token = \Faoxima\Services\Panels\Drivers\Marzban::token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                $offlinePanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
                $offlineKeyboard = json_encode(['inline_keyboard' => [
                    [['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_renew'), 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_buy_new'), 'callback_data' => 'buy_service']],
                    [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
                ]]);
                sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
                return;
            }
            $subscriptionurl = $DataUserOut['subscription_url'];
            if ($marzban_list_get['type'] == "WGDashboard") {
                $textsub = "qrcode اشتراک شما";
            } else {
                $textsub = "
{$textbotlang['users']['stateus']['linksub']}

<code>$subscriptionurl</code>";
            }
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "productcheckdata"],
                    ]
                ]
            ]);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $nameloc['username'], "id", $from_id);
            $subscriptionurl = $DataUserOut['subscription_url'];
            $randomString = bin2hex(random_bytes(3));
            $subBackRow = [[
                'text' => $textbotlang['users']['stateus']['backinfo'],
                'callback_data' => "productcheckdata",
            ]];
            $subCopyKb = \Faoxima\Telegram\Support\Transport::rx_copy_button_keyboard($subscriptionurl, '📋 کپی لینک اشتراک', [$subBackRow], 'service:' . $nameloc['id_invoice'] . ':copy_subscription');
            if ($subCopyKb !== null) {
                $bakinfos = $subCopyKb;
            }

            if ($marzban_list_get['type'] == "WGDashboard") {
                sendmessage($from_id, $textsub, $bakinfos, 'HTML');
                $wgSubName = normalizeConfigFilename("{$marzban_list_get['inboundid']}-{$nameloc['username']}.conf", $marzban_list_get['namecustom'] ?? '', true);
                $wgSubResolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($marzban_list_get, 'wgdashboard', \Faoxima\Services\Delivery\FileNaming::rxFileNamingKeySubscription($marzban_list_get['inboundid'] ?? ''), $wgSubName, '');
                $wgSubPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rxconf-' . bin2hex(random_bytes(8)) . '.conf';
                file_put_contents($wgSubPath, $DataUserOut['subscription_url']);
                try {
                    sendDocument($from_id, $wgSubPath, $wgSubResolved['caption'], $wgSubResolved['filename'], $configDelivery);
                    if ($configDelivery['delivery_status'] !== 'completed') {
                        sendmessage($from_id, \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($configDelivery['delivery_status']), null, 'HTML');
                    }
                } finally {
                    @unlink($wgSubPath);
                }
            } elseif ((!method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') || !\Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled())
                && !(method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($marzban_list_get))
                && trim((string) $subscriptionurl) !== '') {
                $qrCode = \Faoxima\Telegram\Support\QrAndKeys::createqrcode($subscriptionurl);
                if ($qrCode === null) {
                    sendmessage($from_id, $textsub, $bakinfos, 'HTML');
                    sendmessage($from_id, $datatextbot['dyn_errors_qr_too_long'] ?? "❌ ساخت QR کد برای این لینک ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از متن بالا کپی کنید.", null, 'HTML');
                    return;
                }
                $urlimage = "{$from_id}{$randomString}.png";
                file_put_contents($urlimage, $qrCode->getString());
                \Faoxima\Telegram\Support\QrAndKeys::addBackgroundImage($urlimage, $qrCode, 'images.jpg');
                rxSendMediaWithCaption('sendphoto', [
                    'chat_id' => $from_id,
                    'photo' => new CURLFile($urlimage),
                    'caption' => $textsub,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $bakinfos,
                ], null, $rxQrDelivery, true);
                unlink($urlimage);
            } else {
                sendmessage($from_id, $textsub, $bakinfos, 'HTML');
            }
            break;
        case 'remove_auto':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler removeauto on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'removeauto',
                    ]);
                }
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
            \Faoxima\Core\Database\Db::update('invoice', 'status', 'removebyuser', 'id_invoice', $id_invoice);
            $tetremove = "ادمین عزیز یک کاربر سرویس خود را پس از پایان حجم یا زمان حدف کرده است
نام کاربری کانفیک : {$nameloc['username']}";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $tetremove,
                    'parse_mode' => "HTML"
                ]);
            }
            sendmessage($from_id, $datatextbot['dyn_errors_service_deleted'] ?? "📌 سرویس با موفقیت حذف شد", null, 'html');
            break;
        case 'config_page':
            $id_invoice = $dataget[1];
            $page = (int) $dataget[2];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler configpage on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'configpage',
                    ]);
                }
                $nameloc = false;
            }
            if ($nameloc == false) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful" || !is_array($DataUserOut['links'] ?? null)) {
                sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_dot'] ?? "❌ پنل در دسترس نیست.", null, 'html');
                return;
            }
            $_remnaPanelChk = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if (is_array($_remnaPanelChk) && ($_remnaPanelChk['type'] ?? '') === "remnawave" && method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_get_configs')) {
                $remnaConfigs = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_get_configs($nameloc['Service_location'], $nameloc['username']);
                if (($remnaConfigs['status'] ?? '') === 'successful' && !empty($remnaConfigs['links'])) {
                    $DataUserOut['links'] = $remnaConfigs['links'];
                }
            }
            if (count($DataUserOut['links']) === 0) {
                sendmessage($from_id, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboard_config_unavailable_text($DataUserOut), null, 'html');
                return;
            }
            $configKeyboardRows = 0;
            $configKeyboard = \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboard_config($DataUserOut['links'], $id_invoice, true, $page, $configKeyboardRows);
            if ($configKeyboardRows === 0 || !is_string($configKeyboard)) {
                sendmessage($from_id, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboard_config_unavailable_text($DataUserOut, 'invalid'), null, 'html');
                return;
            }
            \Faoxima\Telegram\Conversation\OnboardingHelpers::rx_config_deliver_list($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", $configKeyboard);
            break;
        }

        return true;
    }
}
