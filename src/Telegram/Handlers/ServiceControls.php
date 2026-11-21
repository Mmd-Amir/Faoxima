<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $ManagePanel, $dataget, $datatextbot, $from_id, $message_id, $pdo, $rwm, $telegramRoute, $textbotlang.
 * Inherited or exported by reference: $dataoutput, $id_invoice, $marzban_list_get, $nameloc.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceControls
{
    public static function handle(
        $ManagePanel,
        $dataget,
        &$dataoutput,
        $datatextbot,
        $from_id,
        &$id_invoice,
        &$marzban_list_get,
        $message_id,
        &$nameloc,
        $pdo,
        $rwm,
        $telegramRoute,
        $textbotlang
    ) {
        switch ($telegramRoute) {
        case 'remna_usage':
            $rwId = $rwm[1];
            $rwInv = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $rwId, "select");
            if (!is_array($rwInv) || (string)($rwInv['id_user'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            $rwBw = method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_user_bandwidth') ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_user_bandwidth($rwInv['Service_location'], $rwInv['username']) : ['status' => 'Unsuccessful', 'text' => ''];
            if (($rwBw['status'] ?? '') !== 'successful') {
                sendmessage($from_id, $datatextbot['dyn_errors_usage_stats_failed'] ?? "❌ دریافت آمار مصرف ناموفق بود.", null, 'html');
                return;
            }
            $rwBack = json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$rwInv['id_invoice']}"]]]]);
            sendmessage($from_id, "📊 مصرف ۳۰ روز اخیر به‌تفکیک سرور:\n\n" . $rwBw['text'], $rwBack, 'HTML');
            return;
        case 'remna_squad':
            $rwId = $rwm[1];
            $rwSquad = $rwm[2];
            $rwInv = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $rwId, "select");
            if (!is_array($rwInv) || (string)($rwInv['id_user'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            $rwPanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rwInv['Service_location'], "select");
            if (!is_array($rwPanel) || ($rwPanel['remna_server_picker'] ?? 'off') !== 'on') {
                sendmessage($from_id, $datatextbot['dyn_errors_feature_unavailable_short'] ?? "❌ این قابلیت در دسترس نیست.", null, 'html');
                return;
            }
            $rwSet = method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_set_squads') ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_set_squads($rwInv['Service_location'], $rwInv['username'], [$rwSquad]) : ['status' => 'Unsuccessful'];
            $rwBack = json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$rwInv['id_invoice']}"]]]]);
            sendmessage($from_id, ($rwSet['status'] ?? '') === 'successful' ? "✅ سرور با موفقیت تغییر کرد." : "❌ تغییر سرور ناموفق بود.", $rwBack, 'HTML');
            return;
        case 'remna_picker':
            $rwId = $rwm[1];
            $rwInv = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $rwId, "select");
            if (!is_array($rwInv) || (string)($rwInv['id_user'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            $rwPanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rwInv['Service_location'], "select");
            if (!is_array($rwPanel) || ($rwPanel['remna_server_picker'] ?? 'off') !== 'on') {
                sendmessage($from_id, $datatextbot['dyn_errors_feature_unavailable_short'] ?? "❌ این قابلیت در دسترس نیست.", null, 'html');
                return;
            }
            $rwMgr = method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_manager_for') ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_manager_for($rwInv['Service_location']) : null;
            $rwSq = $rwMgr ? $rwMgr->getInternalSquads($pdo) : ['ok' => false];
            if (empty($rwSq['ok']) || !is_array($rwSq['data'])) {
                sendmessage($from_id, $datatextbot['dyn_errors_server_list_failed'] ?? "❌ دریافت لیست سرورها ناموفق بود.", null, 'html');
                return;
            }
            $rwList = is_array($rwSq['data']['internalSquads'] ?? null) ? $rwSq['data']['internalSquads'] : (isset($rwSq['data'][0]) ? $rwSq['data'] : []);
            $rwKb = ['inline_keyboard' => []];
            foreach ($rwList as $sq) {
                if (!is_array($sq) || empty($sq['uuid'])) {
                    continue;
                }
                $rwKb['inline_keyboard'][] = [['text' => "🌍 " . (string)($sq['name'] ?? $sq['uuid']), 'callback_data' => "remnasetsquad_{$rwInv['id_invoice']}_{$sq['uuid']}"]];
            }
            $rwKb['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$rwInv['id_invoice']}"]];
            Editmessagetext($from_id, $message_id, "🌍 یک سرور (Squad) انتخاب کنید:", json_encode($rwKb));
            return;
        case 'service_status':
            $rxChangeStatusInvoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $dataget[1], "select");
            if (is_array($rxChangeStatusInvoice) && !\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($rxChangeStatusInvoice['Service_location'], 'changeservice')) {
                sendmessage($from_id, $datatextbot['dyn_errors_feature_currently_unavailable_nodot'] ?? "❌ این قابلیت درحال حاضر در دسترس نیست", null, 'html');
                return;
            }
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changestatus on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changestatus',
                    ]);
                }
                return;
            }
            if ($nameloc['Status'] == "disablebyadmin") {
                sendmessage($from_id, $datatextbot['dyn_errors_feature_currently_unavailable_nodot'] ?? "❌ این قابلیت درحال حاضر در دسترس نیست", null, 'html');
                return;
            }
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmMaybeShowStockInvoiceDetails') && \Faoxima\Services\Fulfillment\StockPresentation::nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { \Faoxima\Telegram\Navigation\Steps::step('home', $from_id); return; }
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStopIfServicePanelBlocked') && \Faoxima\Services\Fulfillment\StockPresentation::nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;

            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            $changeStatusPanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if ($DataUserOut['status'] == "on_hold" || ($DataUserOut['status'] === 'active' && !$ManagePanel->HasLiveConnectionHistory(is_array($changeStatusPanel) ? $changeStatusPanel : null, $DataUserOut))) {
                sendmessage($from_id, "ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.", null, 'html');
                return;
            }
            if ($DataUserOut['status'] == "Unsuccessful") {
                $offlinePanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
                $offlineKeyboard = json_encode(['inline_keyboard' => [
                    [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
                    [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
                ]]);
                sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
                return;
            }
            if ($DataUserOut['status'] == "active") {
                $confirmdisableaccount = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ تایید و غیرفعال کانفیگ', 'callback_data' => "confirmaccountdisable_" . $id_invoice],
                        ],
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                        ]
                    ]
                ]);
                Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما خاموش و دیگر امکان اتصال به کانفیگ وجود ندارد.
⚠️ در صورتی که میخواهید مجدد کانفیگ فعال شود باید از بخش مدیریت سرویس دکمه <u>💡 روشن کردن اکانت</u> را کلیک کنید", $confirmdisableaccount);
            } else {
                $confirmdisableaccount = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ تایید و فعال کانفیگ', 'callback_data' => "confirmaccountdisable_" . $id_invoice],
                        ],
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                        ]
                    ]
                ]);
                Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما روشن خواهد شد. و می توانید به کانفیگ خود متصل شوید
⚠️ در صورتی که میخواهید مجدد کانفیگ غیرفعال شود باید از بخش مدیریت سرویس دکمه <u>❌ خاموش کردن اکانت</u>را کلیک کنید", $confirmdisableaccount);
            }
            break;
        case 'disable_service':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmaccountdisable on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmaccountdisable',
                    ]);
                }
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            $currentStatusData = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if (is_array($currentStatusData) && $currentStatusData['status'] === 'active' && !$ManagePanel->HasLiveConnectionHistory(is_array($marzban_list_get) ? $marzban_list_get : null, $currentStatusData)) {
                Editmessagetext($from_id, $message_id, "ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.", $bakinfos);
                return;
            }
            $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
            if ($dataoutput['status'] == "Unsuccessful") {
                if (($dataoutput['code'] ?? '') === 'not_connected') {
                    Editmessagetext($from_id, $message_id, $dataoutput['msg'], $bakinfos);
                    return;
                }
                Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "active") {
                \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
                Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
            } else {
                \Faoxima\Core\Database\Db::update("invoice", "Status", "disabled", "id_invoice", $nameloc['id_invoice']);
                Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
            }
            break;
        }

        return true;
    }
}
