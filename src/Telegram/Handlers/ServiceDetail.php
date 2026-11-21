<?php

namespace Faoxima\Telegram\Handlers;

use CURLFile;
use DateTime;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $_rx_nav_styles, $callback_query_id, $datatextbot, $day, $from_id, $keyboard, $message_id, $pdo, $rxQvDelivery, $setting, $telegramRoute, $text, $textbotlang, $textconnect, $textinfo, $timeDiffday, $user, $xuiIpLimitConfigured.
 * Inherited or exported by reference: $Percent, $_rx_svc_styles, $backuser, $codeproduct, $data, $dataget, $datain, $lastupdate, $nameloc, $namestatus, $output, $page, $pages, $row, $status, $stmt, $username.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceDetail
{
    public static function handle(
        $ManagePanel,
        &$Percent,
        $_rx_nav_styles,
        &$_rx_svc_styles,
        &$backuser,
        $callback_query_id,
        &$codeproduct,
        &$data,
        &$dataget,
        &$datain,
        $datatextbot,
        $day,
        $from_id,
        $keyboard,
        &$lastupdate,
        $message_id,
        &$nameloc,
        &$namestatus,
        &$output,
        &$page,
        &$pages,
        $pdo,
        &$row,
        $rxQvDelivery,
        $setting,
        &$status,
        &$stmt,
        $telegramRoute,
        $text,
        $textbotlang,
        $textconnect,
        $textinfo,
        $timeDiffday,
        $user,
        &$username,
        $xuiIpLimitConfigured
    ) {
        switch ($telegramRoute) {
        case 'service_detail':

            if (is_string($datain) && strpos($datain, 'quickview_') === 0) {
                $id_invoice_qv = $dataget[1];
                $stmtQv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :i AND id_user = :u LIMIT 1");
                $stmtQv->execute([':i' => $id_invoice_qv, ':u' => $from_id]);
                $nameloc_qv = $stmtQv->fetch(PDO::FETCH_ASSOC);
                if (!is_array($nameloc_qv)) {
                    if (isset($callback_query_id)) {
                        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ سرویس پیدا نشد', 'show_alert' => true]);
                    }
                    return;
                }
                if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmMaybeShowStockInvoiceDetails') && \Faoxima\Services\Fulfillment\StockPresentation::nmMaybeShowStockInvoiceDetails($from_id, null, $nameloc_qv)) {
                    if (isset($callback_query_id)) telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStopIfServicePanelBlocked') && \Faoxima\Services\Fulfillment\StockPresentation::nmStopIfServicePanelBlocked($nameloc_qv, $from_id, null)) {
                    if (isset($callback_query_id)) telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $panel_qv = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc_qv['Service_location'], "select");
                $cardPath_qv = null;
                if (is_array($panel_qv) && method_exists(\Faoxima\Telegram\Support\InfoCards::class, 'nm_renderInfoCardForInvoice')) {
                    $cardPath_qv = \Faoxima\Telegram\Support\InfoCards::nm_renderInfoCardForInvoice($panel_qv, $nameloc_qv['username'], $nameloc_qv['id_invoice'], $from_id);
                }
                if ($cardPath_qv !== null && is_file($cardPath_qv)) {
                    $note_qv = isset($nameloc_qv['note']) && $nameloc_qv['note'] !== '' ? ' | ' . $nameloc_qv['note'] : '';
                    $caption_qv = '✨ <b>' . htmlspecialchars((string)$nameloc_qv['username'], ENT_QUOTES, 'UTF-8') . '</b>'
                                . htmlspecialchars($note_qv, ENT_QUOTES, 'UTF-8');
                    $__qvRow = [['text' => '🔧 مدیریت سرویس', 'callback_data' => 'product_' . $nameloc_qv['id_invoice']]];
                    if (!(method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') && \Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled())) {
                        $__qvRow[] = ['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_qr_code_btn', '📷 دریافت QR Code'), 'callback_data' => 'infocard_qr_' . $nameloc_qv['id_invoice']];
                    }
                    $kb_qv = json_encode([
                        'inline_keyboard' => [
                            $__qvRow,
                            [
                                ['text' => $textbotlang['users']['stateus']['backlist'] ?? '🔙 بازگشت', 'callback_data' => 'backorder'],
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE);
                    rxSendMediaWithCaption('sendphoto', [
                        'chat_id' => $from_id,
                        'photo'   => new CURLFile($cardPath_qv),
                        'caption' => $caption_qv,
                        'parse_mode' => 'HTML',
                        'reply_markup' => $kb_qv,
                    ], null, $rxQvDelivery, true);
                    @unlink($cardPath_qv);
                    if (isset($callback_query_id)) {
                        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
                    }
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }

                $datain = "product_" . $id_invoice_qv;
                $dataget = [$datain, $id_invoice_qv];
            }

            if ($user['step'] == "getuseragnetservice") {
                $username = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND id_user = :id_user AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin')";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->bindParam(':Service_time', $username, PDO::PARAM_STR);
                $stmt->bindParam(':Volume', $username, PDO::PARAM_STR);
                $stmt->bindParam(':notes', $username, PDO::PARAM_STR);
                $stmt->bindParam(':id_user', $from_id);
                $stmt->execute();
            } elseif ($datain == "productcheckdata") {
                $username = $user['Processing_value'];
                $sql = "SELECT * FROM invoice WHERE username = :username AND id_user = :id_user";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':id_user', $from_id);
                $stmt->execute();
            } elseif ($datain[0] == "u") {
                $username = $dataget[1];
                $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':id_user', $from_id);
                $stmt->execute();
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "♻️ اطلاعات بروز شد",
                    'show_alert' => false,
                    'cache_time' => 0,
                ));
            } else {
                $username = $dataget[1];
                $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':id_user', $from_id);
                $stmt->execute();
            }
            if ($user['step'] == "getuseragnetservice" && $stmt->rowCount() > 1) {
                $countservice = $stmt->rowCount();
                $pages = 1;
                \Faoxima\Core\Database\Db::update("user", "pagenumber", $pages, "id", $from_id);
                $page = 1;
                $items_per_page = 20;
                $start_index = ($page - 1) * $items_per_page;
                $keyboardlists = [
                    'inline_keyboard' => [],
                ];
                $rxLabelDup = \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'], $setting['statusnamecustom'] == 'onnamecustom');
                if ($setting['statusnamecustom'] == 'onnamecustom') {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $data = "";
                        if ($row != null)
                            $data = " | {$row['note']}";
                        $keyboardlists['inline_keyboard'][] = [
                            [
                                'text' => "✨" . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListLabel($row, $rxLabelDup, true) . $data . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListStatusSuffix($row) . "✨",
                                'callback_data' => "quickview_" . $row['id_invoice']
                            ],
                        ];
                    }
                } else {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $keyboardlists['inline_keyboard'][] = [
                            [
                                'text' => "✨" . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListLabel($row, $rxLabelDup, false) . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListStatusSuffix($row) . "✨",
                                'callback_data' => "quickview_" . $row['id_invoice']
                            ],
                        ];
                    }
                }
                $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
                $backuser = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
                ];
                if ($setting['NotUser'] == "onnotuser") {
                    $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
                }
                $keyboardlists['inline_keyboard'][] = $backuser;
                $keyboard_json = json_encode($keyboardlists);
                $rxSvcFoundTpl = $datatextbot['dyn_errors_service_found_count'] ?? "🛍 %s عدد سرویس یافت برای مشاهده و مدیریت سرویس روی یکی از سرویس ها کلیک کنید";
                sendmessage($from_id, sprintf($rxSvcFoundTpl, $countservice), $keyboard_json, 'html');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $nameloc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($nameloc)) {
                sendmessage($from_id, $datatextbot['dyn_errors_service_not_found'] ?? "❌ سرویس مورد نظر پیدا نشد.", $keyboard, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $username = $nameloc['id_invoice'];
            if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'])) {
                sendmessage($from_id, $datatextbot['dyn_errors_view_account_unavailable'] ?? "❌ امکان مشاهده اطلاعات اکانت درحال حاضر وجود ندارد", $keyboard, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $marzban = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if (is_array($marzban) && isset($marzban['name_panel']) && $marzban['name_panel'] != null) {
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $marzban['name_panel'], "id", $from_id);
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if (is_array($DataUserOut) && isset($DataUserOut['status']) && $DataUserOut['status'] !== 'Unsuccessful') {
                try {
                    \Faoxima\Core\Database\Db::update("invoice", "user_info", json_encode($DataUserOut, JSON_UNESCAPED_UNICODE), "id_invoice", $nameloc['id_invoice']);
                } catch (Throwable $e) {
                }
                $bSt = strtolower((string)$DataUserOut['status']);
                if ($bSt === 'expired' && $nameloc['Status'] !== 'end_of_time') {
                    \Faoxima\Core\Database\Db::update("invoice", "Status", "end_of_time", "id_invoice", $nameloc['id_invoice']);
                    $nameloc['Status'] = 'end_of_time';
                } elseif ($bSt === 'limited' && $nameloc['Status'] !== 'end_of_volume') {
                    \Faoxima\Core\Database\Db::update("invoice", "Status", "end_of_volume", "id_invoice", $nameloc['id_invoice']);
                    $nameloc['Status'] = 'end_of_volume';
                } elseif ($bSt === 'on_hold' && $nameloc['Status'] !== 'send_on_hold') {
                    \Faoxima\Core\Database\Db::update("invoice", "Status", "send_on_hold", "id_invoice", $nameloc['id_invoice']);
                    $nameloc['Status'] = 'send_on_hold';
                } elseif ($bSt === 'disabled' && !in_array($nameloc['Status'], ['disabled', 'disablebyadmin'], true)) {
                    \Faoxima\Core\Database\Db::update("invoice", "Status", "disabled", "id_invoice", $nameloc['id_invoice']);
                    $nameloc['Status'] = 'disabled';
                } elseif ($bSt === 'active' && !in_array($nameloc['Status'], ['active', 'sendedwarn'], true)) {
                    \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
                    $nameloc['Status'] = 'active';
                }
            }
            if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
                if (empty($nameloc['invalidated_at'])) {
                    \Faoxima\Core\Database\Db::update("invoice", "invalidated_at", time(), "id_invoice", $nameloc['id_invoice']);
                }
                \Faoxima\Core\Database\Db::update("invoice", "Status", "disabledn", "id_invoice", $nameloc['id_invoice']);
                $keyboard_remove = [
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['stateus']['deleteFromListBtn'], 'callback_data' => 'deletelist-' . $nameloc['id_invoice']]
                        ],
                        [
                            ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']
                        ]
                    ]
                ];
                $keyboard_remove = json_encode($keyboard_remove);
                $msg = $textbotlang['users']['stateus']['UserNotFound'] . "\n\n" . $textbotlang['users']['stateus']['deleteSuggestion'];
                sendmessage($from_id, $msg, $keyboard_remove, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($DataUserOut['status'] == "Unsuccessful") {
                if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "Panel Not Found") {
                    if (empty($nameloc['invalidated_at'])) {
                        \Faoxima\Core\Database\Db::update("invoice", "invalidated_at", time(), "id_invoice", $nameloc['id_invoice']);
                    }
                    \Faoxima\Core\Database\Db::update("invoice", "Status", "Unsuccessful", "id_invoice", $nameloc['id_invoice']);
                }
                $keyboard_remove = [
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['stateus']['deleteFromListBtn'], 'callback_data' => 'deletelist-' . $nameloc['id_invoice']]
                        ],
                        [
                            ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']
                        ]
                    ]
                ];
                $keyboard_remove = json_encode($keyboard_remove);
                $msg = $textbotlang['users']['stateus']['panelNotConnected'] . "\n\n" . $textbotlang['users']['stateus']['deleteSuggestion'];
                sendmessage($from_id, $msg, $keyboard_remove, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $lastonline = \Faoxima\Services\Delivery\ServiceDelivery::formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

            $status = $DataUserOut['status'];
            $bExp2 = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
            $bDl2 = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
            $bUt2 = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
            if ($bExp2 > 0 && $bExp2 <= time()) {
                $status = 'expired';
            } elseif ($bDl2 > 0.0 && $bUt2 >= $bDl2) {
                $status = 'limited';
            }
            $status_map2 = [
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
            $status_var = $status_map2[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

            $expirationDate = $DataUserOut['expire']
                ? jdate('Y/m/d', $DataUserOut['expire'])
                : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

            $LastTraffic = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

            $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
            $RemainingVolume = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($output) : "نامحدود";

            $usedTrafficGb = $DataUserOut['used_traffic'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

            $timeDiff = $DataUserOut['expire'] - time();
            if ($timeDiff < 0) {
                $day = 0;
            } else {
                $day = "";
                $timemonth = floor($timeDiff / 2592000);
                if ($timemonth > 0) {
                    $day .= $timemonth . $textbotlang['users']['stateus']['month'];
                    $timeDiffday = $timeDiff - (2592000 * $timemonth);
                } else {
                    $timeDiffday = $timeDiff;
                }
                $timereminday = floor($timeDiffday / 86400);
                if ($timereminday > 0) {
                    $day .= $timereminday . $textbotlang['users']['stateus']['day'];
                }
                $timehoures = intval(($timeDiffday - ($timereminday * 86400)) / 3600);
                if ($timehoures > 0) {
                    $day .= $timehoures . $textbotlang['users']['stateus']['hour'];
                }
                $timehoursall = $timeDiffday - ($timereminday * 86400);
                $timehoursall = $timehoursall - ($timehoures * 3600);
                $timeminuts = intval($timehoursall / 60);
                if ($timeminuts > 0) {
                    $day .= $timeminuts . $textbotlang['users']['stateus']['min'];
                }
                $day .= " دیگر";
            }

            if ($DataUserOut['sub_updated_at'] !== null) {
                $sub_updated = $DataUserOut['sub_updated_at'];
                $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
                $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
                $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
            }

            if ($DataUserOut['data_limit'] != null && $DataUserOut['used_traffic'] != null) {
                $Percent = ($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) * 100 / $DataUserOut['data_limit'];
            } else {
                $Percent = "100";
            }
            if ($Percent < 0)
                $Percent = -($Percent);
            $Percent = round($Percent, 2);
            $keyboardsetting = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
                    ]
                ]
            ]);
            $userpassword = "";
            if ($marzban['type'] == "Manualsale") {
                $manualDelivery = \Faoxima\Services\Fulfillment\DeliveryState::manualsaleDelivery($nameloc['username']);
                $manualFileItems = \Faoxima\Services\Delivery\ServiceDelivery::rxManualsaleFileItems($manualDelivery['items'] ?? []);
                $manualHasConfig = !empty($manualFileItems);
                if (!$manualHasConfig) {
                    foreach ($manualDelivery['configs'] as $manualCfgItem) {
                        if (is_string($manualCfgItem) && trim($manualCfgItem) !== '') {
                            $manualHasConfig = true;
                            break;
                        }
                    }
                }
                $manualHasSub = trim((string) $manualDelivery['sub']) !== '';
                $textinfo = "وضعیت سرویس : <b>$status_var</b>
نام کاربری سرویس : {$DataUserOut['username']}
📎 کد پیگیری سرویس : {$nameloc['id_invoice']}

📅 تاریخ اتمام : $expirationDate ($day)";
                $manualStatusExtend  = ($marzban['status_extend'] ?? 'on_extend') != "off_extend";
                $manualRefundEnabled = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'refund');
                $manualRows = [];
                if ($manualHasConfig) {
                    $manualRows[] = [
                        ['text' => "🔐 دریافت کانفیگ", 'callback_data' => 'config_' . $nameloc['id_invoice']],
                    ];
                }
                if ($manualHasSub) {
                    $manualRows[] = [
                        ['text' => "🔗 دریافت لینک اشتراک", 'callback_data' => 'subscriptionurl_' . $nameloc['id_invoice']],
                    ];
                }
                if ($manualStatusExtend) {
                    $manualRows[] = [
                        ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extend_' . $nameloc['id_invoice']],
                    ];
                }
                if ($manualRefundEnabled) {
                    $manualRows[] = [
                        ['text' => $textbotlang['users']['stateus']['removeservice'], 'callback_data' => 'removeserviceuser_' . $nameloc['id_invoice']],
                    ];
                }
                $manualRows[] = [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
                ];
                $keyboardsettingManual = json_encode(['inline_keyboard' => $manualRows], JSON_UNESCAPED_UNICODE);
                if ($user['step'] == "getuseragnetservice") {
                    sendmessage($from_id, $textinfo, $keyboardsettingManual, 'html');
                } elseif ($datain == "productcheckdata") {
                    deletemessage($from_id, $message_id);
                    sendmessage($from_id, $textinfo, $keyboardsettingManual, 'html');
                } else {
                    Editmessagetext($from_id, $message_id, $textinfo, $keyboardsettingManual);
                }
                return;
            }
            $nameconfig = "";
            if ($nameloc['note'] != null) {
                $nameconfig = "✍️ یادداشت کانفیگ : {$nameloc['note']}";
            }
            $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
            $stmt->execute([
                ':username' => $nameloc['username'],
            ]);
            if ($stmt->rowCount() != 0) {
                $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
                    $service_other = json_decode($service_other['value'], true);
                    $codeproduct = \Faoxima\Core\Database\Db::select("product", "*", "code_product", $service_other['code_product'], "select");
                    if ($codeproduct != false) {
                        $nameloc['name_product'] = $codeproduct['name_product'];
                        $nameloc['Volume'] = $codeproduct['Volume_constraint'];
                        $nameloc['Service_time'] = $codeproduct['Service_time'];
                    }
                }
            }

            $statustimeextra = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'timeextra') ? "ontimeextraa" : "offtimeextraa";
            $marzbanstatusextra = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'extravolume') ? "onextra" : "offextra";
            $statusdisorder = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'disorder') ? "ondisorder" : "offdisorder";
            $statuschangeservice = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'changeservice') ? "onstatus" : "offstatus";
            $statusshowconfig = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'configbtn') ? "onconfig" : "offconfig";
            $statusremoveserveice = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'refund') ? "on" : "off";
            if (!in_array($status, ["active", "on_hold", "disabled", "Unknown"])) {
                $textinfo = "وضعیت سرویس : <b>$status_var</b>
👤 نام کاربری سرویس : <code>{$DataUserOut['username']}</code>
🌍 موقعیت سرویس :{$nameloc['Service_location']}
نام محصول :{$nameloc['name_product']}

📶 اخرین زمان اتصال شما : $lastonline

🔋 ترافیک : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 تاریخ اتمام :  $expirationDate ($day)

$nameconfig";

                $keyboardsetting = [
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extend_' . $username],
                            ['text' => $textbotlang['users']['Extra_volume']['sellextra'], 'callback_data' => 'Extra_volume_' . $username],
                        ],
                        [
                            ['text' => "❌ حذف سرویس", 'callback_data' => 'removeauto-' . $username],
                            ['text' => $textbotlang['users']['Extra_time']['title'], 'callback_data' => 'Extra_time_' . $username],
                        ],
                        [
                            ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
                        ]
                    ]
                ];
                if ($statustimeextra == "offtimeextraa")
                    unset($keyboardsetting['inline_keyboard'][1][1]);
                if ($marzbanstatusextra == "offextra")
                    unset($keyboardsetting['inline_keyboard'][0][1]);
                $keyboardsetting['inline_keyboard'] = array_values($keyboardsetting['inline_keyboard']);
                $keyboardsetting = json_encode($keyboardsetting);
            } else {
                $marzbancount = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "status", "active", "count");
                if ($DataUserOut['status'] == "active") {
                    $namestatus = '❌ خاموش کردن اکانت';
                } else {
                    $namestatus = '💡 روشن کردن اکانت';
                }
                $keyboarddate = array(
                    'updateinfo' => array(
                        'text' => "♻️ بروزرسانی اطلاعات",
                        'callback_data' => "updateproduct_"
                    ),
                    'linksub' => array(
                        'text' => $textbotlang['users']['stateus']['linksub'],
                        'callback_data' => "subscriptionurl_"
                    ),
                    'config' => array(
                        'text' => $textbotlang['users']['stateus']['config'],
                        'callback_data' => "config_"
                    ),
                    'extend' => array(
                        'text' => $textbotlang['users']['extend']['title'],
                        'callback_data' => "extend_"
                    ),
                    'changelink' => array(
                        'text' => $textbotlang['users']['changelink']['btntitle'],
                        'callback_data' => "changelink_"
                    ),
                    'removeservice' => array(
                        'text' => $textbotlang['users']['stateus']['removeservice'],
                        'callback_data' => "removeserviceuser_"
                    ),
                    'changenameconfig' => array(
                        'text' => '📝 تغییر یادداشت',
                        'callback_data' => "changenote_"
                    ),
                    'Extra_volume' => array(
                        'text' => $textbotlang['users']['Extra_volume']['sellextra'],
                        'callback_data' => "Extra_volume_"
                    ),
                    'Extra_time' => array(
                        'text' => $textbotlang['users']['Extra_time']['title'],
                        'callback_data' => "Extra_time_"
                    ),
                    'changestatus' => array(
                        'text' => $namestatus,
                        'callback_data' => "changestatus_"
                    ),
                    'transfor' => array(
                        'text' => $textbotlang['Admin']['transfor']['title'],
                        'callback_data' => "transfer_"
                    ),
                    'change-location' => array(
                        'text' => $textbotlang['Admin']['change-location']['title'],
                        'callback_data' => "changeloc_"
                    ),
                    'ekhtelal' => array(
                        'text' => "⚠️ ارسال گزارش اختلال",
                        'callback_data' => "disorder-"
                    )
                );
                if ($nameloc['name_product'] == "سرویس تست") {
                    unset($keyboarddate['transfor']);
                    unset($keyboarddate['Extra_time']);
                    unset($keyboarddate['removeservice']);
                }
                if ($marzban['type'] == "eylanpanel") {
                    unset($keyboarddate['config']);
                    unset($keyboarddate['changelink']);
                }
                if ($marzban['type'] == "WGDashboard") {
                    unset($keyboarddate['config']);
                    unset($keyboarddate['changestatus']);
                    unset($keyboarddate['change-location']);
                    unset($keyboarddate['changelink']);
                }
                if ($marzban['type'] == "remnawave") {
                    if (($marzban['remna_revoke'] ?? 'off') !== "on") {
                        unset($keyboarddate['changelink']);
                    }
                    unset($keyboarddate['transfor']);
                    if (($marzban['changeloc'] ?? '') !== "onchangeloc") {
                        unset($keyboarddate['change-location']);
                    }
                    if (($marzban['sublink'] ?? '') !== "onsublink") {
                        unset($keyboarddate['linksub']);
                    }
                    if (($marzban['config'] ?? '') !== "onconfig") {
                        unset($keyboarddate['config']);
                    }
                    if (($marzban['remna_usage_btn'] ?? 'on') === "on") {
                        $keyboarddate['remna_usage'] = ['text' => '📊 مصرف به‌تفکیک سرور', 'callback_data' => 'remnausage_'];
                    }
                    if (($marzban['remna_server_picker'] ?? 'off') === "on") {
                        $keyboarddate['remna_picker'] = ['text' => '🌍 انتخاب سرور', 'callback_data' => 'remnapicker_'];
                    }
                }
                if ($marzban['status_extend'] == "off_extend") {
                    unset($keyboarddate['Extra_time']);
                    unset($keyboarddate['Extra_volume']);
                    unset($keyboarddate['extend']);
                }
                if ($statusremoveserveice == "off")
                    unset($keyboarddate['removeservice']);
                if ($statusshowconfig == "offconfig")
                    unset($keyboarddate['config']);
                if ($statusdisorder == "offdisorder")
                    unset($keyboarddate['ekhtelal']);
                if ($nameloc['Service_time'] == "0")
                    unset($keyboarddate['Extra_time']);
                if ($nameloc['Volume'] == "0") {
                    unset($keyboarddate['Extra_volume']);
                    unset($keyboarddate['Extra_time']);
                }
                if ($statuschangeservice == "offstatus")
                    unset($keyboarddate['changestatus']);
                if ($setting['statusnamecustom'] == 'offnamecustom')
                    unset($keyboarddate['changenameconfig']);
                if ($marzbancount == 1)
                    unset($keyboarddate['change-location']);
                if ($marzban['changeloc'] == "offchangeloc")
                    unset($keyboarddate['change-location']);
                if ($statustimeextra == "offtimeextraa")
                    unset($keyboarddate['Extra_time']);
                if ($marzbanstatusextra == "offextra")
                    unset($keyboarddate['Extra_volume']);
                $_rx_svc_styles = [];
                if (!empty($setting['keyboard_styles_all'])) {
                    $_rx_all_kbd_s = json_decode($setting['keyboard_styles_all'], true);
                    if (is_array($_rx_all_kbd_s) && !empty($_rx_all_kbd_s['service'])) {
                        $_rx_svc_styles = array_filter($_rx_all_kbd_s['service'], static function ($v) { return $v !== 'default'; });
                    }
                }
                if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                    $_rx_svc_styles = $_rx_svc_styles + rx_getKeyboardDefaultStyles('service');
                }
                $tempArray = [];
                $keyboardsetting = ['inline_keyboard' => []];
                foreach ($keyboarddate as $_rx_svc_key => $keyboardtext) {
                    $_rx_svc_btn = ['text' => $keyboardtext['text'], 'callback_data' => $keyboardtext['callback_data'] . $username, '_rxcolor' => 'service:' . $nameloc['id_invoice'] . ':' . $_rx_svc_key];
                    if (!empty($_rx_svc_styles[$_rx_svc_key]) && $_rx_svc_styles[$_rx_svc_key] !== 'default') {
                        $_rx_svc_btn['style'] = $_rx_svc_styles[$_rx_svc_key];
                    }
                    $tempArray[] = $_rx_svc_btn;
                    if (count($tempArray) == 2 or $keyboardtext['text'] == "♻️ بروزرسانی اطلاعات") {
                        $keyboardsetting['inline_keyboard'][] = $tempArray;
                        $tempArray = [];
                    }
                }
                if (count($tempArray) > 0) {
                    $keyboardsetting['inline_keyboard'][] = $tempArray;
                }
                $_rx_back_btn = ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'];
                if (!empty($_rx_svc_styles['backorder']) && $_rx_svc_styles['backorder'] !== 'default') {
                    $_rx_back_btn['style'] = $_rx_svc_styles['backorder'];
                }
                $keyboardsetting['inline_keyboard'][] = [$_rx_back_btn];
                $keyboardsetting = json_encode($keyboardsetting);
                $subLastUserAgent = trim((string)($DataUserOut['sub_last_user_agent'] ?? ''));
                $subClientLine = $subLastUserAgent !== '' && strcasecmp($subLastUserAgent, 'Faoxima-Mini/1.0') !== 0
                    ? "\n#️⃣ کلاینت متصل شده :<code>{$subLastUserAgent}</code>"
                    : '';
                if ($DataUserOut['sub_updated_at'] !== null) {
                    $textconnect = "
📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate{$subClientLine}";
                } elseif ($marzban['type'] == "WGDashboard") {
                    $textconnect = "";
                } else {
                    $textconnect = "📶 اخرین زمان اتصال شما : $lastonline";
                }
                $rwChangelinkHint = (($marzban['type'] ?? '') != "remnawave") ? "💡 برای قطع دسترسی دیگران کافیست روی گزینه \"تغییر لینک\" کلیک کنید." : "";
                $textinfo_tpl = $datatextbot['text_service_detail'] ?? "🗂 نام سرویس : {product}
📊وضعیت سرویس : {status}
🌍 موقعیت سرویس :{location}
👤 شناسه سرویس : <code>{username}</code>

🔋 ترافیک : {traffic}
📥 حجم مصرفی : {used}
💢 حجم باقی مانده : {remaining} ({percent}%)

📅 تاریخ اتمام : {expire_date} ({days_left})";
                $textinfo_replacements = [
                    '{status}' => $status_var,
                    '{username}' => $DataUserOut['username'],
                    '{location}' => $nameloc['Service_location'],
                    '{product}' => $nameloc['name_product'],
                    '{traffic}' => $LastTraffic,
                    '{used}' => $usedTrafficGb,
                    '{remaining}' => $RemainingVolume,
                    '{percent}' => $Percent,
                    '{expire_date}' => $expirationDate,
                    '{days_left}' => $day,
                ];
                $textinfo = strtr($textinfo_tpl, $textinfo_replacements);
                $textinfo = "$textinfo\n$userpassword\n$nameconfig\n\n$textconnect\n\n$rwChangelinkHint";
                $rxReservationSummary = is_callable([\Faoxima\Services\Renewal\ReservationOrders::class, 'reservationSummaryForService']) ? \Faoxima\Services\Renewal\ReservationOrders::reservationSummaryForService($pdo, (string) $nameloc['username'], (string) $nameloc['Service_location'], (string) $nameloc['id_invoice']) : null;
                if (is_array($rxReservationSummary)) {
                    $textinfo .= "\n\n" . htmlspecialchars((string) $rxReservationSummary['text'], ENT_QUOTES, 'UTF-8');
                }
            }
            if (($marzban['type'] ?? '') == "remnawave") {
                $rwLight = !empty($DataUserOut['is_online']) ? '🟢 آنلاین' : '🔴 آفلاین';
                $textinfo .= "\n📶 وضعیت اتصال: $rwLight";
                if (!empty($DataUserOut['node_name'])) {
                    $textinfo .= "\n🌍 سرور فعال: " . $DataUserOut['node_name'];
                }
            }
            $symbolicLimitLabel = \Faoxima\Telegram\Support\Texts::faoxima_symbolic_limit_label($nameloc);
            if ($symbolicLimitLabel !== null) {
                $textinfo .= "\n👥 تعداد کاربر مجاز: $symbolicLimitLabel";
            } elseif (($marzban['type'] ?? '') == "x-ui_single") {
                $xuiIpGuardActive = (($marzban['ip_limit_guard'] ?? '') === 'onipguard');
                if ($xuiIpGuardActive) {
                    $xuiIpLimitConfigured = intval($nameloc['ip_limit'] ?? 0);
                    $xuiIpLimitText = $xuiIpLimitConfigured > 0 ? "{$xuiIpLimitConfigured} کاربر" : 'نامحدود';
                    $textinfo .= "\n👥 تعداد کاربر مجاز: $xuiIpLimitText";
                }
                if (method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_panel_uses_token') && \Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($marzban)) {
                    $xuiOnlineCheck = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_client_is_online($marzban, $DataUserOut['username'] ?? '');
                    if (!empty($xuiOnlineCheck['status'])) {
                        $textinfo .= "\n📶 وضعیت اتصال: " . (!empty($xuiOnlineCheck['online']) ? '🟢 آنلاین' : '⚪ آفلاین');
                    }
                    if ($xuiIpGuardActive) {
                        $xuiIpSummary = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_client_ip_summary($marzban, $DataUserOut['username'] ?? '');
                        if (!empty($xuiIpSummary['status']) && method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_cap_ip_summary')) {
                            $xuiIpSummary = \Faoxima\Telegram\Support\Texts::faoxima_cap_ip_summary($xuiIpSummary, $xuiIpLimitConfigured);
                        }
                        if (!empty($xuiIpSummary['status'])) {
                            $textinfo .= "\n🌐 تعداد IP مشاهده‌شده: " . intval($xuiIpSummary['count']);
                            if (!empty($xuiIpSummary['raw'])) {
                                foreach (array_slice($xuiIpSummary['raw'], 0, 5) as $xuiRawIp) {
                                    $textinfo .= "\n📍 IP متصل: " . htmlspecialchars($xuiRawIp, ENT_QUOTES, 'UTF-8');
                                }
                            }
                        } else {
                            $textinfo .= "\n🌐 اطلاعات IP متصل در حال حاضر در دسترس نیست.";
                        }
                    }
                    $xuiHwidLimitConfigured = intval($nameloc['hwid_limit'] ?? 0);
                    if ($xuiHwidLimitConfigured > 0 && method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_client_hwid_devices')) {
                        $xuiHwidResponse = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_client_hwid_devices($marzban, $DataUserOut['username'] ?? '');
                        if (!empty($xuiHwidResponse['status'])) {
                            $xuiHwidList = is_array($xuiHwidResponse['devices'] ?? null) ? $xuiHwidResponse['devices'] : [];
                            $textinfo .= "\n👥 دستگاه‌های متصل: " . count($xuiHwidList) . " از {$xuiHwidLimitConfigured} دستگاه";
                            foreach (array_slice($xuiHwidList, 0, 5) as $xuiHwidEntry) {
                                $xuiDeviceOs = (string) ($xuiHwidEntry['deviceOs'] ?? '');
                                $xuiDeviceModel = (string) ($xuiHwidEntry['deviceModel'] ?? '');
                                $xuiDeviceLabel = $xuiDeviceOs !== '' ? ($xuiDeviceModel !== '' ? "{$xuiDeviceOs} ({$xuiDeviceModel})" : $xuiDeviceOs) : 'نامشخص';
                                $xuiLastSeenRaw = intval($xuiHwidEntry['lastSeen'] ?? 0);
                                $xuiLastSeenText = '';
                                if ($xuiLastSeenRaw > 0) {
                                    try {
                                        $xuiLastSeenDt = new DateTime('@' . intval($xuiLastSeenRaw / 1000));
                                        $xuiLastSeenDt->setTimezone(new DateTimeZone('Asia/Tehran'));
                                        $xuiLastSeenText = ' — آخرین اتصال: ' . jdate('Y/m/d H:i:s', $xuiLastSeenDt->getTimestamp());
                                    } catch (Throwable $e) {
                                    }
                                }
                                $textinfo .= "\n📱 دستگاه: {$xuiDeviceLabel}{$xuiLastSeenText}";
                            }
                        } else {
                            $textinfo .= "\n👥 دستگاه‌های متصل: 0 از {$xuiHwidLimitConfigured} دستگاه";
                        }
                    }
                }
            } elseif (($marzban['type'] ?? '') == "rebecca") {
                $rebeccaIpGuardActive = (($marzban['ip_limit_guard'] ?? '') === 'onipguard');
                if ($rebeccaIpGuardActive) {
                    $rebeccaIpLimitConfigured = intval($nameloc['ip_limit'] ?? 0);
                    $rebeccaIpLimitText = $rebeccaIpLimitConfigured > 0 ? "{$rebeccaIpLimitConfigured} کاربر" : 'نامحدود';
                    $textinfo .= "\n👥 تعداد کاربر مجاز: $rebeccaIpLimitText";
                    $rebeccaInsightsAllowed = !method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaAccessInsightsPermissionStatus')
                        || (\Faoxima\Services\Panels\Drivers\Rebecca::rebeccaAccessInsightsPermissionStatus($marzban['name_panel'])['status'] ?? false) === true;
                    if ($rebeccaInsightsAllowed && method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaClientIpSummary')) {
                        $rebeccaIpSummary = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaClientIpSummary($marzban['name_panel'], $DataUserOut['username'] ?? '');
                        if (!empty($rebeccaIpSummary['status']) && method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_cap_ip_summary')) {
                            $rebeccaIpSummary = \Faoxima\Telegram\Support\Texts::faoxima_cap_ip_summary($rebeccaIpSummary, $rebeccaIpLimitConfigured);
                        }
                        if (!empty($rebeccaIpSummary['status'])) {
                            $textinfo .= "\n🌐 تعداد IP مشاهده‌شده: " . intval($rebeccaIpSummary['count']);
                            if (!empty($rebeccaIpSummary['raw'])) {
                                foreach (array_slice($rebeccaIpSummary['raw'], 0, 5) as $rebeccaRawIp) {
                                    $textinfo .= "\n📍 IP متصل: " . htmlspecialchars($rebeccaRawIp, ENT_QUOTES, 'UTF-8');
                                }
                            }
                        } else {
                            $textinfo .= "\n🌐 اطلاعات IP متصل در حال حاضر در دسترس نیست.";
                        }
                    }
                }
            } elseif (($marzban['type'] ?? '') == "pasarguard") {
                $pasarguardHwidLimitConfigured = intval($DataUserOut['hwid_limit'] ?? 0);
                if ($pasarguardHwidLimitConfigured > 0 && method_exists(\Faoxima\Services\Panels\Drivers\PasarGuard::class, 'pasarguardGetUserHwids')) {
                    $pasarguardHwidResponse = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardGetUserHwids($DataUserOut['username'] ?? $nameloc['username'], $marzban['name_panel']);
                    if (empty($pasarguardHwidResponse['error']) && !empty($pasarguardHwidResponse['body'])) {
                        $pasarguardHwidData = json_decode($pasarguardHwidResponse['body'], true);
                        $pasarguardHwidList = is_array($pasarguardHwidData['hwids'] ?? null) ? $pasarguardHwidData['hwids'] : [];
                        $textinfo .= "\n👥 دستگاه‌های متصل: " . count($pasarguardHwidList) . " از {$pasarguardHwidLimitConfigured} دستگاه";
                        foreach (array_slice($pasarguardHwidList, 0, 5) as $pasarguardHwidEntry) {
                            $pasarguardDeviceOs = (string) ($pasarguardHwidEntry['device_os'] ?? '');
                            $pasarguardDeviceModel = (string) ($pasarguardHwidEntry['device_model'] ?? '');
                            $pasarguardDeviceLabel = $pasarguardDeviceOs !== '' ? ($pasarguardDeviceModel !== '' ? "{$pasarguardDeviceOs} ({$pasarguardDeviceModel})" : $pasarguardDeviceOs) : 'نامشخص';
                            $pasarguardLastUsedRaw = (string) ($pasarguardHwidEntry['last_used_at'] ?? '');
                            $pasarguardLastSeenText = '';
                            if ($pasarguardLastUsedRaw !== '') {
                                try {
                                    $pasarguardLastSeenDt = new DateTime($pasarguardLastUsedRaw, new DateTimeZone('UTC'));
                                    $pasarguardLastSeenDt->setTimezone(new DateTimeZone('Asia/Tehran'));
                                    $pasarguardLastSeenText = ' — آخرین اتصال: ' . jdate('Y/m/d H:i:s', $pasarguardLastSeenDt->getTimestamp());
                                } catch (Throwable $e) {
                                }
                            }
                            $textinfo .= "\n📱 دستگاه: {$pasarguardDeviceLabel}{$pasarguardLastSeenText}";
                        }
                    } else {
                        $textinfo .= "\n👥 دستگاه‌های متصل: 0 از {$pasarguardHwidLimitConfigured} دستگاه";
                    }
                }
            } elseif (($marzban['type'] ?? '') == "remnawave") {
                $remnaHwidLimitConfigured = intval($DataUserOut['hwid_limit'] ?? 0);
                if ($remnaHwidLimitConfigured > 0 && method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_get_user_hwid_devices')) {
                    $remnaHwidResponse = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_get_user_hwid_devices($marzban['name_panel'], $DataUserOut['username'] ?? $nameloc['username']);
                    if (($remnaHwidResponse['status'] ?? '') === 'successful') {
                        $remnaHwidList = is_array($remnaHwidResponse['devices'] ?? null) ? $remnaHwidResponse['devices'] : [];
                        $textinfo .= "\n👥 دستگاه‌های متصل: " . count($remnaHwidList) . " از {$remnaHwidLimitConfigured} دستگاه";
                        foreach (array_slice($remnaHwidList, 0, 5) as $remnaHwidEntry) {
                            $remnaDevicePlatform = (string) ($remnaHwidEntry['platform'] ?? '');
                            $remnaDeviceModel = (string) ($remnaHwidEntry['deviceModel'] ?? '');
                            $remnaDeviceLabel = $remnaDevicePlatform !== '' ? ($remnaDeviceModel !== '' ? "{$remnaDevicePlatform} ({$remnaDeviceModel})" : $remnaDevicePlatform) : 'نامشخص';
                            $remnaLastUsedRaw = (string) ($remnaHwidEntry['updatedAt'] ?? '');
                            $remnaLastSeenText = '';
                            if ($remnaLastUsedRaw !== '') {
                                try {
                                    $remnaLastSeenDt = new DateTime($remnaLastUsedRaw, new DateTimeZone('UTC'));
                                    $remnaLastSeenDt->setTimezone(new DateTimeZone('Asia/Tehran'));
                                    $remnaLastSeenText = ' — آخرین اتصال: ' . jdate('Y/m/d H:i:s', $remnaLastSeenDt->getTimestamp());
                                } catch (Throwable $e) {
                                }
                            }
                            $textinfo .= "\n📱 دستگاه: {$remnaDeviceLabel}{$remnaLastSeenText}";
                        }
                    } else {
                        $textinfo .= "\n👥 دستگاه‌های متصل: 0 از {$remnaHwidLimitConfigured} دستگاه";
                    }
                }
            }
            if ($user['step'] == "getuseragnetservice") {
                sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
            } elseif ($datain == "productcheckdata") {
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
            } else {
                Editmessagetext($from_id, $message_id, $textinfo, $keyboardsetting);
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            return;
        }

        return true;
    }
}
