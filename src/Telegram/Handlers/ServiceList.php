<?php

namespace Faoxima\Telegram\Handlers;

use Faoxima\Orders\Pricing\ExchangePricing;
use Faoxima\Telegram\Support\ServiceProductIdentity;
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
        case 'services_page':
            return self::browse(
                $pdo, $from_id, $message_id, $datain, $telegramRoute, $dataget[2] ?? null,
                $setting, $datatextbot, $textbotlang, $_rx_nav_styles
            );
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

    private static function browse($pdo, $fromId, $messageId, &$callback, $route, $legacyProductKey, $setting, $texts, $language, $navigationStyles)
    {
        $isEntry = $route === 'services';
        $isLegacy = !$isEntry && !str_starts_with((string) $callback, 'ms:');
        $isHome = $isEntry || $isLegacy || $callback === 'ms:home';
        $panelKey = $productKey = null;
        $requestedPage = 1;
        if (!$isHome && preg_match('/^ms:([a-f0-9]{20})(?::([a-f0-9]{20}|trial):([0-9]{1,10}))?$/D', $callback, $selection)) {
            $panelKey = $selection[1];
            $productKey = $selection[2] ?? null;
            $requestedPage = (int) ($selection[3] ?? 1);
        } elseif (!$isHome && preg_match('/^ms:custom:([0-9]{1,10})$/D', $callback, $selection)) {
            $productKey = 'custom';
            $requestedPage = (int) $selection[1];
        }

        $customLabel = $language['users']['customsellvolume']['title'] ?? 'سرویس دلخواه';
        $customNames = ['', trim($customLabel), '🛍 حجم دلخواه', '⚙️ سرویس دلخواه', 'سرویس سفارشی'];
        $panels = $products = $serviceRows = $panelServiceRows = [];
        $previousProductGroup = null;
        $hasCustom = false;
        $legacyFound = !$isLegacy;
        if ($isHome || $panelKey !== null || $productKey === 'custom') {
            $snapshotColumn = ExchangePricing::fxTableHasColumn('invoice', 'pricing_snapshot') ? ', pricing_snapshot' : '';
            $stmt = $pdo->prepare("SELECT id_invoice, username, note, Status, Service_location, name_product{$snapshotColumn}
                FROM invoice WHERE id_user = :id_user
                AND Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin')
                ORDER BY time_sell DESC, id_invoice ASC");
            $stmt->execute([':id_user' => $fromId]);
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $identity = $invoices !== [] ? new ServiceProductIdentity($pdo, (string) $fromId) : null;
            foreach ($invoices as $invoice) {
                $storedName = $invoice['name_product'];
                if (!$legacyFound) {
                    $legacyFound = md5($storedName === null ? 'NULL' : 'P:' . $storedName) === $legacyProductKey;
                }
                $name = trim((string) $storedName);
                $product = $identity->productForInvoice($invoice);
                if ($product === null && (in_array($name, $customNames, true) || \Faoxima\Services\Renewal\Plans::renewalIsCustomCode($name))) {
                    $hasCustom = true;
                    if ($productKey === 'custom') {
                        $serviceRows[] = $invoice;
                    }
                    continue;
                }

                $panel = (string) $invoice['Service_location'];
                $key = substr(hash('sha256', $panel), 0, 20);
                $panels[$key] = trim($panel) !== '' ? $panel : 'پنل نامشخص';
                if ($key !== $panelKey) {
                    continue;
                }

                $isTrial = $product === null && $name === RX_TEST_PRODUCT_NAME;
                $label = $product !== null && $product['name'] !== '' ? $product['name'] : $name;
                $group = $isTrial ? 'trial' : substr(hash('sha256', 'name:' . $label), 0, 20);
                if ($productKey === null) {
                    $products[$group] = $label;
                } else {
                    $panelServiceRows[$group][] = $invoice;
                    if (!$isTrial && $previousProductGroup === null) {
                        $previousIdentity = $product !== null ? 'product:' . $product['id'] : 'invoice:' . $invoice['id_invoice'];
                        if (substr(hash('sha256', $previousIdentity), 0, 20) === $productKey
                            || ($name !== $label && substr(hash('sha256', 'name:' . $name), 0, 20) === $productKey)) {
                            $previousProductGroup = $group;
                        }
                    }
                }
            }
            if ($panelKey !== null && $productKey !== null) {
                if (!isset($panelServiceRows[$productKey]) && $previousProductGroup !== null) {
                    $productKey = $previousProductGroup;
                }
                $serviceRows = $panelServiceRows[$productKey] ?? [];
            }
        }

        $backToMain = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(
            ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back_main'), 'callback_data' => 'backuser'],
            'backuser',
            is_array($navigationStyles) ? $navigationStyles : []
        );
        $search = ['text' => $language['users']['search']['title'], 'callback_data' => 'searchservice', '_rxcolor' => 'subscription_list:search'];
        $lookup = ['text' => $language['users']['page']['notusernameme'], 'callback_data' => 'notusernameme'];
        $back = [
            'text' => $language['users']['stateus']['backlist'],
            'callback_data' => $panelKey !== null && $productKey !== null && isset($panels[$panelKey]) ? "ms:$panelKey" : 'ms:home',
        ];

        if ($isHome && $legacyFound) {
            $hasServices = $panels !== [] || $hasCustom;
            $message = $language['users']['sell']['service_not_available'];
            if ($isEntry && !$hasServices && $setting['NotUser'] === 'offnotuser') {
                sendmessage($fromId, $message, null, 'html');
                return;
            }
            if ($isEntry) {
                \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('services', $fromId, $callback, $messageId, ['myservicesbtn'], ['myservicesbtn']);
            }
            $buttons = [];
            asort($panels, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($panels as $key => $name) {
                $buttons[] = [['text' => $name, 'callback_data' => "ms:$key"]];
            }
            if ($hasCustom) {
                $buttons[] = [['text' => $customLabel, 'callback_data' => 'ms:custom:1']];
            }
            if ($setting['NotUser'] === 'onnotuser') {
                $buttons[] = [$lookup];
            }
            $buttons[] = [$search];
            if ($hasServices || $setting['NotUser'] === 'onnotuser') {
                $message = "🛍 سرویس‌های من\n\nبرای مشاهده سرویس‌ها، پنل یا سرویس دلخواه را انتخاب کنید.\n\n🔎 برای پیدا کردن سریع سرویس خود می‌توانید از دکمه جستجو سریع استفاده کنید.";
            }
        } elseif ($products !== []) {
            $buttons = \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxBotServiceProductButtons($products, "ms:$panelKey");
            $buttons[] = [$back];
            $panelLabel = htmlspecialchars($panels[$panelKey], ENT_QUOTES, 'UTF-8');
            $message = "🛍 سرویس‌های من — $panelLabel\n\nبرای مشاهده نام‌های کاربری، محصول مورد نظر را انتخاب کنید.";
        } elseif ($serviceRows !== []) {
            $callbackPrefix = 'ms:' . ($productKey === 'custom' ? 'custom' : "$panelKey:$productKey");
            $buttons = \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxBotServiceUsernameButtons(
                $serviceRows, $requestedPage, $callbackPrefix, $setting['statusnamecustom'] === 'onnamecustom', $language['users']['page']
            );
            $buttons[] = [$search];
            if ($setting['NotUser'] === 'onnotuser') {
                $buttons[] = [$lookup];
            }
            $buttons[] = [$back];
            $message = $language['users']['sell']['service_sell'];
        } else {
            $message = $texts['dyn_errors_service_not_found'] ?? "❌ سرویس مورد نظر پیدا نشد.";
            $keyboard = json_encode(['inline_keyboard' => [[$back], [$backToMain]]]);
            Editmessagetext($fromId, $messageId, $message, $keyboard);
            return;
        }

        $buttons[] = [$backToMain];
        $keyboard = json_encode(['inline_keyboard' => $buttons]);
        if (!$isEntry || in_array($callback, ['backorder', 'myservicesbtn'], true)) {
            Editmessagetext($fromId, $messageId, $message, $keyboard);
        } else {
            sendmessage($fromId, $message, $keyboard, 'html');
        }
        return true;
    }
}
