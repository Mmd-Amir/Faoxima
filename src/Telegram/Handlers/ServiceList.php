<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $ManagePanel, $_rx_nav_styles, $dataget, $datatextbot, $from_id, $keyboard, $list_marzban_panel_user, $message_id, $pdo, $setting, $telegramRoute, $textbotlang, $update, $user.
 * Inherited or exported by reference: $backuser, $data, $datain, $day, $marzban_list_get, $output, $page, $row, $status, $stmt, $text, $usernameconfig.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceList
{
    public static function handle(
        $ManagePanel,
        $_rx_nav_styles,
        &$backuser,
        &$data,
        $dataget,
        &$datain,
        $datatextbot,
        &$day,
        $from_id,
        $keyboard,
        $list_marzban_panel_user,
        &$marzban_list_get,
        $message_id,
        &$output,
        &$page,
        $pdo,
        &$row,
        $setting,
        &$status,
        &$stmt,
        $telegramRoute,
        &$text,
        $textbotlang,
        $update,
        $user,
        &$usernameconfig
    ) {
        switch ($telegramRoute) {
        case 'services':
            $rxServiceStatuses = ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'];
            $rxServiceStatusSql = "status IN ('" . implode("','", $rxServiceStatuses) . "')";
            $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND $rxServiceStatusSql");
            $stmt->bindParam(':id_user', $from_id);
            $stmt->execute();
            $invoices = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($invoices === false && $setting['NotUser'] == "offnotuser") {
                sendmessage($from_id, $textbotlang['users']['sell']['service_not_available'], null, 'html');
                return;
            }
            \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('services', $from_id, $datain, $message_id, ['myservicesbtn'], ['myservicesbtn']);

            $stmt = $pdo->prepare("SELECT name_product FROM invoice WHERE id_user = :id_user AND $rxServiceStatusSql GROUP BY name_product ORDER BY name_product ASC");
            $stmt->execute([':id_user' => $from_id]);
            $serviceProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $keyboardlists = [
                'inline_keyboard' => \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxBotServiceProductButtons($serviceProducts, $textbotlang['users']['customsellvolume']['title'] ?? 'سرویس دلخواه'),
            ];
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            $backuser = [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
            ];
            if ($setting['NotUser'] == "onnotuser") {
                $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
            }
            $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice', '_rxcolor' => 'subscription_list:search']];
            $keyboardlists['inline_keyboard'][] = $backuser;
            $keyboard_json = json_encode($keyboardlists);
            $rxProductListText = "🛍 سرویس‌های من\n\nبرای مشاهده نام‌های کاربری، محصول مورد نظر را انتخاب کنید.\n\n🔎 برای پیدا کردن سریع سرویس خود می‌توانید از دکمه جستجو سریع استفاده کنید.";
            if ($datain == "backorder" || $datain == "myservicesbtn") {
                Editmessagetext($from_id, $message_id, $rxProductListText, $keyboard_json);
            } else {
                sendmessage($from_id, $rxProductListText, $keyboard_json, 'html');
            }

            break;
        case 'services_page':
            $rxProductHash = $dataget[2];
            $rxRequestedPage = isset($dataget[3]) ? (int) $dataget[3] : 1;
            $rxServiceStatuses = ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'];
            $rxServiceStatusSql = "status IN ('" . implode("','", $rxServiceStatuses) . "')";
            $stmt = $pdo->prepare("SELECT name_product FROM invoice WHERE id_user = :id_user AND $rxServiceStatusSql GROUP BY name_product ORDER BY name_product ASC");
            $stmt->execute([':id_user' => $from_id]);
            $rxProductRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $rxSelectedProduct = null;
            $rxSelectedFound = false;
            foreach ($rxProductRows as $rxProductRow) {
                $rxProductValue = $rxProductRow['name_product'];
                $rxProductKey = md5($rxProductValue === null ? 'NULL' : 'P:' . $rxProductValue);
                if ($rxProductKey === $rxProductHash) {
                    $rxSelectedProduct = $rxProductValue;
                    $rxSelectedFound = true;
                    break;
                }
            }
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            $backuser = [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
            ];
            if (!$rxSelectedFound) {
                $rxUnavailableKeyboard = json_encode(['inline_keyboard' => [
                    [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'myservicesbtn']],
                    $backuser
                ]]);
                Editmessagetext($from_id, $message_id, $datatextbot['dyn_errors_service_not_found'] ?? "❌ سرویس مورد نظر پیدا نشد.", $rxUnavailableKeyboard);
                return;
            }
            $rxProductWhere = $rxSelectedProduct === null ? "name_product IS NULL" : "name_product = :name_product";
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = :id_user AND $rxProductWhere AND $rxServiceStatusSql");
            $rxCountParams = [':id_user' => $from_id];
            if ($rxSelectedProduct !== null) {
                $rxCountParams[':name_product'] = $rxSelectedProduct;
            }
            $stmt->execute($rxCountParams);
            $rxTotalServices = (int) $stmt->fetchColumn();
            if ($rxTotalServices === 0) {
                $rxUnavailableKeyboard = json_encode(['inline_keyboard' => [
                    [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'myservicesbtn']],
                    $backuser
                ]]);
                Editmessagetext($from_id, $message_id, $datatextbot['dyn_errors_service_not_found'] ?? "❌ سرویس مورد نظر پیدا نشد.", $rxUnavailableKeyboard);
                return;
            }
            $items_per_page = 5;
            $rxTotalPages = max(1, (int) ceil($rxTotalServices / $items_per_page));
            $page = max(1, min($rxRequestedPage, $rxTotalPages));
            $start_index = ($page - 1) * $items_per_page;
            $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND $rxProductWhere AND $rxServiceStatusSql ORDER BY time_sell DESC, id_invoice ASC LIMIT $start_index, $items_per_page");
            $rxRowsParams = [':id_user' => $from_id];
            if ($rxSelectedProduct !== null) {
                $rxRowsParams[':name_product'] = $rxSelectedProduct;
            }
            $stmt->execute($rxRowsParams);
            $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $keyboardlists = [
                'inline_keyboard' => [],
            ];
            foreach ($serviceRows as $row) {
                $data = "";
                if ($setting['statusnamecustom'] == 'onnamecustom' && trim((string) ($row['note'] ?? '')) !== '') {
                    $data = " | {$row['note']}";
                }
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => "✨" . (string) ($row['username'] ?? '') . $data . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListStatusSuffix($row) . "✨",
                        'callback_data' => "quickview_" . $row['id_invoice']
                    ],
                ];
            }
            $pagination_buttons = [];
            if ($page > 1) {
                $pagination_buttons[] = [
                    'text' => $textbotlang['users']['page']['previous'],
                    'callback_data' => 'previous_page_' . $rxProductHash . '_' . ($page - 1),
                    '_rxcolor' => 'subscription_list:previous'
                ];
            }
            if ($page < $rxTotalPages) {
                $pagination_buttons[] = [
                    'text' => $textbotlang['users']['page']['next'],
                    'callback_data' => 'next_page_' . $rxProductHash . '_' . ($page + 1),
                    '_rxcolor' => 'subscription_list:next'
                ];
            }
            if (!empty($pagination_buttons)) {
                $keyboardlists['inline_keyboard'][] = $pagination_buttons;
            }
            $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice', '_rxcolor' => 'subscription_list:search']];
            if ($setting['NotUser'] == "onnotuser") {
                $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
            }
            $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'myservicesbtn']];
            $keyboardlists['inline_keyboard'][] = $backuser;
            $keyboard_json = json_encode($keyboardlists);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);

            break;
        case 'lookup_prompt':
            sendmessage($from_id, $textbotlang['users']['stateus']['SendUsername'], $backuser, 'html');
            \Faoxima\Telegram\Navigation\Steps::step('getusernameinfo', $from_id);
            break;
        case 'lookup_name':
            if (!isset($update['message']) && empty($text)) { return; }
            if (empty($text))
                return;
            $usernameconfig = "";
            if (strlen($text) > 32) {
                if (!filter_var($text, FILTER_VALIDATE_URL)) {
                    sendmessage($from_id, $datatextbot['dyn_errors_invalid_subscription_link'] ?? "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
                    return;
                }
                $date = \Faoxima\Services\Delivery\ServiceDelivery::outputlunksub($text);
                if (!isset($date)) {
                    sendmessage($from_id, $datatextbot['dyn_errors_invalid_subscription_link'] ?? "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
                    return;
                }
                $date = json_decode($date, true);
                if (!isset($date['username'])) {
                    sendmessage($from_id, $datatextbot['dyn_errors_invalid_subscription_link'] ?? "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
                    return;
                }
                $usernameconfig = $date['username'];
            } else {
                $text = str_replace('_', '-', $text);
                if (!preg_match('/^[A-Za-z0-9-]{3,32}$/', $text)) {
                    sendmessage($from_id, $textbotlang['users']['stateus']['Invalidusername'], $backuser, 'html');
                    return;
                }
                $usernameconfig = $text;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $usernameconfig, "id", $from_id);
            sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_user, 'html');
            \Faoxima\Telegram\Navigation\Steps::step('getdata', $from_id);
            break;
        case 'lookup_panel':
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $dataget[1]);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
            $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $user['Processing_value']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                if ($DataUserOut['msg'] == "User not found") {
                    sendmessage($from_id, $textbotlang['users']['stateus']['notUsernameget'], $keyboard, 'html');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], $keyboard, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }

            $status = $DataUserOut['status'];
            $bExp = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
            $bDl = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
            $bUt = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
            if ($bExp > 0 && $bExp <= time()) {
                $status = 'expired';
            } elseif ($bDl > 0.0 && $bUt >= $bDl) {
                $status = 'limited';
            }
            $status_map = [
                'active' => $textbotlang['users']['stateus']['active'],
                'limited' => $textbotlang['users']['stateus']['limited'],
                'end_of_volume' => $textbotlang['users']['stateus']['limited'],
                'disabled' => $textbotlang['users']['stateus']['disabled'],
                'deactivev' => $textbotlang['users']['stateus']['disabled'],
                'expired' => $textbotlang['users']['stateus']['expired'],
                'end_of_time' => $textbotlang['users']['stateus']['expired'],
                'on_hold' => $textbotlang['users']['stateus']['on_hold'],
                'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
                'Unknown' => $textbotlang['users']['stateus']['Unknown']
            ];
            $status_var = $status_map[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

            $expirationDate = $DataUserOut['expire']
                ? jdate('Y/m/d', $DataUserOut['expire'])
                : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

            $LastTraffic = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

            $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
            $RemainingVolume = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($output) : "نامحدود";

            $usedTrafficGb = $DataUserOut['used_traffic'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

            $timeDiff = $DataUserOut['expire'] - time();
            $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

            $keyboardinfo = [
                'inline_keyboard' => [
                    [
                        ['text' => $DataUserOut['username'], 'callback_data' => "username", '_rxcolor' => 'subscription_field:username:value'],
                        ['text' => $textbotlang['users']['stateus']['username'], 'callback_data' => 'username', '_rxcolor' => 'subscription_field:username:label'],
                    ],
                    [
                        ['text' => $status_var, 'callback_data' => 'status_var', '_rxsk' => 'Status', '_rxcolor' => 'subscription_field:Status:value'],
                        ['text' => $textbotlang['users']['stateus']['stateus'], 'callback_data' => 'status_var', '_rxsk' => 'Status', '_rxcolor' => 'subscription_field:Status:label'],
                    ],
                    [
                        ['text' => $expirationDate, 'callback_data' => 'expirationDate', '_rxcolor' => 'subscription_field:expirationDate:value'],
                        ['text' => $textbotlang['users']['stateus']['expirationDate'], 'callback_data' => 'expirationDate', '_rxcolor' => 'subscription_field:expirationDate:label'],
                    ],
                    [],
                    [
                        ['text' => $day, 'callback_data' => 'روز', '_rxsk' => 'daysleft', '_rxcolor' => 'subscription_field:daysleft:value'],
                        ['text' => $textbotlang['users']['stateus']['daysleft'], 'callback_data' => 'day', '_rxsk' => 'daysleft', '_rxcolor' => 'subscription_field:daysleft:label'],
                    ],
                    [
                        ['text' => $LastTraffic, 'callback_data' => 'LastTraffic', '_rxcolor' => 'subscription_field:LastTraffic:value'],
                        ['text' => $textbotlang['users']['stateus']['LastTraffic'], 'callback_data' => 'LastTraffic', '_rxcolor' => 'subscription_field:LastTraffic:label'],
                    ],
                    [
                        ['text' => $usedTrafficGb, 'callback_data' => 'expirationDate', '_rxsk' => 'usedtraffic', '_rxcolor' => 'subscription_field:usedtraffic:value'],
                        ['text' => $textbotlang['users']['stateus']['usedTrafficGb'], 'callback_data' => 'expirationDate', '_rxsk' => 'usedtraffic', '_rxcolor' => 'subscription_field:usedtraffic:label'],
                    ],
                    [
                        ['text' => $RemainingVolume, 'callback_data' => 'RemainingVolume', '_rxcolor' => 'subscription_field:RemainingVolume:value'],
                        ['text' => $textbotlang['users']['stateus']['RemainingVolume'], 'callback_data' => 'RemainingVolume', '_rxcolor' => 'subscription_field:RemainingVolume:label'],
                    ]
                ]
            ];
            $marzbanstatusextra = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($marzban_list_get, 'extravolume') ? "onextra" : "offextra";
            if ($marzbanstatusextra == "onextra") {
                $keyboardinfo['inline_keyboard'][] = [
                    ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]],
                    ['text' => $textbotlang['users']['Extra_volume']['sellextra'], 'callback_data' => 'Extra_volumes_' . $DataUserOut['username'] . '_' . $dataget[1]],
                ];
            } else {
                $keyboardinfo['inline_keyboard'][] = [['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]]];
            }
            $keyboardinfo = json_encode($keyboardinfo);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['info'], $keyboardinfo);
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'html');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }

        return true;
    }
}
