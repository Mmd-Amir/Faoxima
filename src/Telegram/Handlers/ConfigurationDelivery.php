<?php

namespace Faoxima\Telegram\Handlers;

use CURLFile;

/**
 * Handler stage. Inherited by value: $ManagePanel, $dataget, $datatextbot, $from_id, $manualDeliveryResult, $message_id, $rxCfgQrDelivery, $telegramRoute, $text, $textbotlang.
 * Inherited or exported by reference: $configDelivery, $id_invoice, $nameloc.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ConfigurationDelivery
{
    public static function handle(
        $ManagePanel,
        &$configDelivery,
        $dataget,
        $datatextbot,
        $from_id,
        &$id_invoice,
        $manualDeliveryResult,
        $message_id,
        &$nameloc,
        $rxCfgQrDelivery,
        $telegramRoute,
        $text,
        $textbotlang
    ) {
        switch ($telegramRoute) {
        case 'config_send':
            $textCommand = is_string($text) ? $text : '';
            if ($textCommand !== '' && $textCommand[0] === "/") {
                $parts = explode(' ', $textCommand, 2);
                $id_invoice = $parts[1] ?? null;
                if ($id_invoice === null) {
                    sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                    return;
                }
                $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $id_invoice, "select");
                if ($nameloc['id_user'] != $from_id) {
                    $nameloc = false;
                }
            } else {
                $id_invoice = $dataget[1];
                $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

                if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                    if (function_exists('rx_log_event')) {
                        rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler config on non-owned invoice', [
                            'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'config',
                        ]);
                    }
                    $nameloc = false;
                }
            }
            if ($nameloc == false) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmMaybeShowStockInvoiceDetails') && \Faoxima\Services\Fulfillment\StockPresentation::nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { \Faoxima\Telegram\Navigation\Steps::step('home', $from_id); return; }
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStopIfServicePanelBlocked') && \Faoxima\Services\Fulfillment\StockPresentation::nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
            $_configPanelChk = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if (is_array($_configPanelChk) && ($_configPanelChk['type'] ?? '') === "Manualsale") {
                $manualDelivery = \Faoxima\Services\Fulfillment\DeliveryState::manualsaleDelivery($nameloc['username']);
                $manualFileItems = \Faoxima\Services\Delivery\ServiceDelivery::rxManualsaleFileItems($manualDelivery['items'] ?? []);
                if (!empty($manualFileItems)) {
                    $manualBackKb = json_encode(['inline_keyboard' => [[
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$nameloc['id_invoice']}"],
                    ]]], JSON_UNESCAPED_UNICODE);
                    \Faoxima\Services\Delivery\ServiceDelivery::rxManualsaleSendFileItems($from_id, $nameloc['username'], $manualFileItems, '', $manualBackKb, 0, 0, $manualDeliveryResult);
                    $deliveryStatus = $manualDeliveryResult['delivery_status'];
                    if ($deliveryStatus !== 'completed') {
                        $notice = \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($deliveryStatus, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.");
                        sendmessage($from_id, $notice, $manualDeliveryResult['keyboard_state'] === 'failed' ? $manualBackKb : null, 'HTML');
                    }
                    return;
                }
                $manualConfigs = array_values(array_filter($manualDelivery['configs'], function ($item) {
                    return is_string($item) && trim($item) !== '';
                }));
                if (empty($manualConfigs)) {
                    sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
                    return;
                }
                \Faoxima\Telegram\Conversation\OnboardingHelpers::rx_config_deliver_list($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboard_config($manualConfigs, $nameloc['id_invoice']));
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                $offlinePanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
                $offlineKeyboard = json_encode(['inline_keyboard' => [
                    [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
                    [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
                ]]);
                sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
                return;
            }
            if (!is_array($DataUserOut['links'])) {
                sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
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
            $configKeyboard = \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboard_config($DataUserOut['links'], $nameloc['id_invoice'], true, 0, $configKeyboardRows);
            if ($configKeyboardRows === 0 || !is_string($configKeyboard)) {
                sendmessage($from_id, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboard_config_unavailable_text($DataUserOut, 'invalid'), null, 'html');
                return;
            }
            \Faoxima\Telegram\Conversation\OnboardingHelpers::rx_config_deliver_list($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", $configKeyboard);
            break;
        case 'config_item':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler configget on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'configget',
                    ]);
                }
                $nameloc = false;
            }
            if ($nameloc == false) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            $_configgetPanelChk = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if (is_array($_configgetPanelChk) && ($_configgetPanelChk['type'] ?? '') === "Manualsale") {
                $manualDelivery = \Faoxima\Services\Fulfillment\DeliveryState::manualsaleDelivery($nameloc['username']);
                $manualFileItems = \Faoxima\Services\Delivery\ServiceDelivery::rxManualsaleFileItems($manualDelivery['items'] ?? []);
                if (!empty($manualFileItems)) {
                    \Faoxima\Services\Delivery\ServiceDelivery::rxManualsaleSendFileItems($from_id, $nameloc['username'], $manualFileItems, '', null, 0, 0, $manualDeliveryResult);
                    $deliveryStatus = $manualDeliveryResult['delivery_status'];
                    if ($deliveryStatus !== 'completed') {
                        $notice = \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($deliveryStatus, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.");
                        sendmessage($from_id, $notice, null, 'HTML');
                    }
                    return;
                }
                $manualConfigs = array_values(array_filter($manualDelivery['configs'], function ($item) {
                    return is_string($item) && trim($item) !== '';
                }));
                if (empty($manualConfigs)) {
                    sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
                    return;
                }
                if ($dataget[2] == "1520") {
                    for ($i = 0; $i < count($manualConfigs); ++$i) {
                        sendmessage($from_id, "<code>" . htmlspecialchars($manualConfigs[$i], ENT_QUOTES, 'UTF-8') . "</code>", \Faoxima\Telegram\Support\Transport::rx_copy_button_keyboard($manualConfigs[$i], '📋 کپی کانفیگ', null, 'config_copy:' . substr(sha1($manualConfigs[$i]), 0, 16)), 'HTML');
                    }
                    return;
                }
                if (!isset($manualConfigs[$dataget[2]])) {
                    sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
                    return;
                }
                sendmessage($from_id, "<code>" . htmlspecialchars($manualConfigs[$dataget[2]], ENT_QUOTES, 'UTF-8') . "</code>", \Faoxima\Telegram\Support\Transport::rx_copy_button_keyboard($manualConfigs[$dataget[2]], '📋 کپی کانفیگ', null, 'config_copy:' . substr(sha1($manualConfigs[$dataget[2]]), 0, 16)), 'HTML');
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "productcheckdata"],
                    ]
                ]
            ]);
            if ($DataUserOut['status'] == "Unsuccessful") {
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
            if (!isset($DataUserOut['links']) || !is_array($DataUserOut['links']) || count($DataUserOut['links']) === 0) {
                sendmessage($from_id, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboard_config_unavailable_text($DataUserOut), null, 'html');
                return;
            }
            $rwCgSel = $DataUserOut['links'][$dataget[2]] ?? '';
            if ($dataget[2] != "1520" && !isset($DataUserOut['links'][$dataget[2]])) {
                sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
                return;
            }
            $config = "";
            $rxAllWgKeyCounts = \Faoxima\Services\Delivery\FileNaming::rxFileNamingEnabled() ? \Faoxima\Services\Delivery\FileNaming::rxFileNamingLinkKeyCounts($DataUserOut['links'], $_configgetPanelChk) : [];
            if ($dataget[2] == "1520") {
                $rxDeliveredConfigHashes = [];
                for ($i = 0; $i < count($DataUserOut['links']); ++$i) {
                    if ($i === 0 && !empty($DataUserOut['single_config_file']) && is_array($DataUserOut['single_config_file'])) {
                        $allSingleFile = $DataUserOut['single_config_file'];
                        $allSingleName = normalizeConfigFilename($allSingleFile['filename'], '', true);
                        $allSingleKey = \Faoxima\Services\Delivery\FileNaming::rxFileNamingScopedKey(\Faoxima\Services\Delivery\FileNaming::rxFileNamingKeyFile($allSingleFile['filename']), $allSingleFile['protocol'] ?? 'wireguard');
                        $allSingleResolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($_configgetPanelChk, 'xui_file', $allSingleKey, $allSingleName, '');
                        $allSingleTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rxconf-' . bin2hex(random_bytes(8)) . '.conf';
                        try {
                            if (file_put_contents($allSingleTemp, (string) $allSingleFile['value']) === false) {
                                sendmessage($from_id, '❌ خطا در آماده‌سازی فایل کانفیگ.', null, 'HTML');
                                return;
                            }
                            sendDocument($from_id, $allSingleTemp, $allSingleResolved['caption'], $allSingleResolved['filename'], $configDelivery);
                            if ($configDelivery['delivery_status'] !== 'completed') {
                                sendmessage($from_id, \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($configDelivery['delivery_status']), null, 'HTML');
                                if ($configDelivery['media_state'] !== 'confirmed') {
                                    return;
                                }
                            }
                            $rxDeliveredConfigHashes[hash('sha256', (string) $allSingleFile['value'])] = true;
                        } finally {
                            @unlink($allSingleTemp);
                        }
                        continue;
                    }
                    $allConfigItem = (string) $DataUserOut['links'][$i];
                    if (method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadIsProtocolMarker') && \Faoxima\Services\Panels\Drivers\JovadPanel::jovadIsProtocolMarker($allConfigItem)) {
                        $jovadAllResult = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelDeliverMarkerFiles($from_id, $_configgetPanelChk, $allConfigItem, null);
                        if (empty($jovadAllResult['ok'])) {
                            sendmessage($from_id, htmlspecialchars((string) ($jovadAllResult['msg'] ?? 'خطا در دریافت فایل کانفیگ؛ دوباره تلاش کنید.'), ENT_QUOTES, 'UTF-8'), null, 'html');
                            return;
                        }
                        continue;
                    }
                    if (method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_wg_conf_from_link')) {
                        $allWgConf = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_from_link($allConfigItem);
                        if (is_array($allWgConf)) {
                            $allWgHash = hash('sha256', (string) $allWgConf['conf']);
                            if (isset($rxDeliveredConfigHashes[$allWgHash])) {
                                continue;
                            }
                            $allWgName = normalizeConfigFilename(\Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_filename($allConfigItem, $allWgConf['protocol'], $i), '', true);
                            $allWgDescriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingEnabled() ? \Faoxima\Services\Delivery\FileNaming::rxFileNamingDescriptorFromLink($allConfigItem) : null;
                            $allWgKey = is_array($allWgDescriptor) ? (string) ($allWgDescriptor['key'] ?? '') : '';
                            $allWgAmbiguous = ($allWgKey !== '' && (($rxAllWgKeyCounts[$allWgKey] ?? 0) > 1));
                            $allWgResolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($_configgetPanelChk, 'xui_wg', $allWgKey, $allWgName, '', $allWgAmbiguous);
                            $allWgName = $allWgResolved['filename'];
                            $allWgDest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rxconf-' . bin2hex(random_bytes(8)) . '.conf';
                            try {
                                if (file_put_contents($allWgDest, $allWgConf['conf']) === false) {
                                    sendmessage($from_id, '❌ خطا در آماده‌سازی فایل کانفیگ.', null, 'HTML');
                                    return;
                                }
                                sendDocument($from_id, $allWgDest, $allWgResolved['caption'], $allWgName, $configDelivery);
                                if ($configDelivery['delivery_status'] !== 'completed') {
                                    sendmessage($from_id, \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($configDelivery['delivery_status']), null, 'HTML');
                                    if ($configDelivery['media_state'] !== 'confirmed') {
                                        return;
                                    }
                                }
                                $rxDeliveredConfigHashes[$allWgHash] = true;
                            } finally {
                                @unlink($allWgDest);
                            }
                            continue;
                        }
                    }
                    $allRebeccaInfo = method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaConfigDownloadInfo') ? \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaConfigDownloadInfo($allConfigItem) : null;
                    if (is_array($allRebeccaInfo)) {
                        $allRebeccaDescriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingDescriptorFromLink($allConfigItem);
                        $allRebeccaKey = is_array($allRebeccaDescriptor) ? $allRebeccaDescriptor['key'] : '';
                        $allRebeccaName = normalizeConfigFilename($allRebeccaInfo['filename'], '', $allRebeccaInfo['kind'] === 'wireguard');
                        $allRebeccaAmbiguous = $allRebeccaKey !== '' && (($rxAllWgKeyCounts[$allRebeccaKey] ?? 0) > 1);
                        $allRebeccaResolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($_configgetPanelChk, 'rebecca', $allRebeccaKey, $allRebeccaName, '', $allRebeccaAmbiguous);
                        $allRebeccaTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rxconf-' . bin2hex(random_bytes(8)) . '.tmp';
                        try {
                            if (!\Faoxima\Services\Panels\Drivers\Rebecca::rebeccaDownloadConfigFile($allRebeccaInfo['url'], $allRebeccaTemp)) {
                                sendmessage($from_id, '❌ خطا در دریافت فایل کانفیگ از پنل.', null, 'HTML');
                                return;
                            }
                            sendDocument($from_id, $allRebeccaTemp, $allRebeccaResolved['caption'], $allRebeccaResolved['filename'], $configDelivery);
                            if ($configDelivery['delivery_status'] !== 'completed') {
                                sendmessage($from_id, \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($configDelivery['delivery_status']), null, 'HTML');
                                if ($configDelivery['media_state'] !== 'confirmed') {
                                    return;
                                }
                            }
                        } finally {
                            @unlink($allRebeccaTemp);
                        }
                        continue;
                    }
                    if (preg_match('/^https?:\/\//i', $allConfigItem)) {
                        $allConfigItem = strtok($allConfigItem, '#');
                    }
                    sendmessage($from_id, "<code>{$allConfigItem}</code>", \Faoxima\Telegram\Support\Transport::rx_copy_button_keyboard($allConfigItem, '📋 کپی کانفیگ', null, 'config_copy:' . substr(sha1($allConfigItem), 0, 16)), 'HTML');
                }
                return;
            }
            $selectedConfig = (string) $DataUserOut['links'][$dataget[2]];
            if ((int) $dataget[2] === 0 && !empty($DataUserOut['single_config_file']) && is_array($DataUserOut['single_config_file'])) {
                $singleConfigFile = $DataUserOut['single_config_file'];
                $xuiFileName = normalizeConfigFilename($singleConfigFile['filename'], '', true);
                $xuiFileKey = \Faoxima\Services\Delivery\FileNaming::rxFileNamingScopedKey(\Faoxima\Services\Delivery\FileNaming::rxFileNamingKeyFile($singleConfigFile['filename']), $singleConfigFile['protocol'] ?? 'wireguard');
                $xuiFileResolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($_configgetPanelChk, 'xui_file', $xuiFileKey, $xuiFileName, '');
                $xuiFileName = $xuiFileResolved['filename'];
                $xuiFileDest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rxconf-' . bin2hex(random_bytes(8)) . '.conf';
                file_put_contents($xuiFileDest, $singleConfigFile['value']);
                try {
                    sendDocument($from_id, $xuiFileDest, $xuiFileResolved['caption'], $xuiFileName, $configDelivery);
                    if ($configDelivery['delivery_status'] !== 'completed') {
                        sendmessage($from_id, \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($configDelivery['delivery_status']), null, 'HTML');
                    }
                } finally {
                    @unlink($xuiFileDest);
                }
                return;
            }
            if (method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_wg_conf_from_link')) {
                $xuiWgConf = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_from_link($selectedConfig);
                if (is_array($xuiWgConf)) {
                    $xuiWgName = normalizeConfigFilename(\Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_filename($selectedConfig, $xuiWgConf['protocol'], (int) $dataget[2]), '', true);
                    $xuiWgDescriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingDescriptorFromLink($selectedConfig);
                    $xuiWgKey = is_array($xuiWgDescriptor) ? (string) ($xuiWgDescriptor['key'] ?? '') : '';
                    $xuiWgAmbiguous = $xuiWgKey !== '' && (($rxAllWgKeyCounts[$xuiWgKey] ?? 0) > 1);
                    $xuiWgResolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($_configgetPanelChk, 'xui_wg', $xuiWgKey, $xuiWgName, '', $xuiWgAmbiguous);
                    $xuiWgName = $xuiWgResolved['filename'];
                    $xuiWgDest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rxconf-' . bin2hex(random_bytes(8)) . '.conf';
                    file_put_contents($xuiWgDest, $xuiWgConf['conf']);
                    try {
                        sendDocument($from_id, $xuiWgDest, $xuiWgResolved['caption'], $xuiWgName, $configDelivery);
                        if ($configDelivery['delivery_status'] !== 'completed') {
                            sendmessage($from_id, \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($configDelivery['delivery_status']), null, 'HTML');
                        }
                    } finally {
                        @unlink($xuiWgDest);
                    }
                    return;
                }
            }
            if (method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadIsProtocolMarker') && \Faoxima\Services\Panels\Drivers\JovadPanel::jovadIsProtocolMarker($selectedConfig)) {
                $jovadSingleKeyboard = json_encode([
                    'inline_keyboard' => [
                        [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "productcheckdata"]],
                    ],
                ], JSON_UNESCAPED_UNICODE);
                $jovadSingleResult = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelDeliverMarkerFiles($from_id, $_configgetPanelChk, $selectedConfig, $jovadSingleKeyboard);
                if (empty($jovadSingleResult['ok'])) {
                    sendmessage($from_id, htmlspecialchars((string) ($jovadSingleResult['msg'] ?? 'خطا در دریافت فایل کانفیگ؛ دوباره تلاش کنید.'), ENT_QUOTES, 'UTF-8'), null, 'html');
                }
                return;
            }
            if (method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaConfigDownloadInfo')) {
                $rebeccaDownloadInfo = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaConfigDownloadInfo($selectedConfig);
                if ($rebeccaDownloadInfo !== null) {
                    $rebeccaPublicName = normalizeConfigFilename($rebeccaDownloadInfo['filename'], '', $rebeccaDownloadInfo['kind'] === 'wireguard');
                    $rebeccaDescriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingDescriptorFromLink($selectedConfig);
                    $rebeccaKey = is_array($rebeccaDescriptor) ? (string) ($rebeccaDescriptor['key'] ?? '') : '';
                    $rebeccaAmbiguous = $rebeccaKey !== '' && (($rxAllWgKeyCounts[$rebeccaKey] ?? 0) > 1);
                    $rebeccaResolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($_configgetPanelChk, 'rebecca', $rebeccaKey, $rebeccaPublicName, '', $rebeccaAmbiguous);
                    $rebeccaPublicName = $rebeccaResolved['filename'];
                    $rebeccaTempExt = strtolower((string) pathinfo($rebeccaPublicName, PATHINFO_EXTENSION));
                    $rebeccaTempExt = preg_match('/^[a-z0-9]{1,10}$/', $rebeccaTempExt) ? '.' . $rebeccaTempExt : '.tmp';
                    $rebeccaDownloadDest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rxconf-' . bin2hex(random_bytes(8)) . $rebeccaTempExt;
                    if (\Faoxima\Services\Panels\Drivers\Rebecca::rebeccaDownloadConfigFile($rebeccaDownloadInfo['url'], $rebeccaDownloadDest)) {
                        sendDocument($from_id, $rebeccaDownloadDest, $rebeccaResolved['caption'], $rebeccaPublicName, $configDelivery);
                        @unlink($rebeccaDownloadDest);
                        if ($configDelivery['delivery_status'] !== 'completed') {
                            sendmessage($from_id, \Faoxima\Telegram\Support\NavigationFunctionsHelpers::rxConfigDeliveryNotice($configDelivery['delivery_status']), null, 'HTML');
                        }
                    } else {
                        sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
                    }
                    return;
                }
            }
            if (preg_match('/^https?:\/\//i', $selectedConfig)) {
                $selectedConfig = strtok($selectedConfig, '#');
            }
            $isStructuredConnectionInfo = strpos($selectedConfig, "\n") !== false && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:\/\//', $selectedConfig);
            $qrWantedForConfig = (!method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') || !\Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled())
                && !(method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($_configgetPanelChk))
                && !$isStructuredConnectionInfo
                && !preg_match('#^(tg|tg-proxy|proxy|wireguard|wg|vpn|awg|amneziawg)://#i', trim($selectedConfig))
                && trim($selectedConfig) !== '';
            $selectedConfigCopyKb = \Faoxima\Telegram\Support\Transport::rx_copy_button_keyboard($selectedConfig, '📋 کپی کانفیگ', null, 'config_copy:' . substr(sha1($selectedConfig), 0, 16));
            if ($qrWantedForConfig) {
                $qrCode = \Faoxima\Telegram\Support\QrAndKeys::createqrcode($selectedConfig);
                if ($qrCode === null) {
                    sendmessage($from_id, "<code>{$selectedConfig}</code>", $selectedConfigCopyKb, 'HTML');
                    sendmessage($from_id, $datatextbot['dyn_errors_qr_too_long'] ?? "❌ ساخت QR کد برای این کانفیگ ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از متن بالا کپی کنید.", null, 'HTML');
                    return;
                }
                $urlimage = "{$from_id}" . bin2hex(random_bytes(3)) . ".png";
                file_put_contents($urlimage, $qrCode->getString());
                \Faoxima\Telegram\Support\QrAndKeys::addBackgroundImage($urlimage, $qrCode, 'images.jpg');
                $photoPayload = [
                    'chat_id' => $from_id,
                    'photo' => new CURLFile($urlimage),
                    'caption' => '<code>' . htmlspecialchars($selectedConfig, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>',
                    'parse_mode' => "HTML",
                ];
                if ($selectedConfigCopyKb !== null) {
                    $photoPayload['reply_markup'] = $selectedConfigCopyKb;
                }
                rxSendMediaWithCaption('sendphoto', $photoPayload, null, $rxCfgQrDelivery, true);
                unlink($urlimage);
            } else {
                sendmessage($from_id, "<code>{$selectedConfig}</code>", $selectedConfigCopyKb, 'HTML');
            }
            break;
        }

        return true;
    }
}
