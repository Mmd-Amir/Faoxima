<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $colorMatch, $dataget, $from_id, $keyboardadmin, $message_id, $pdo, $rxPanelAlreadyLoadedForEditpanel, $setting, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $code_panel, $panel, $stmt, $value, $valuenew.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class WarningsInfoCardsAndProviderFeatures
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        &$code_panel,
        $colorMatch,
        $dataget,
        $from_id,
        $keyboardadmin,
        $message_id,
        &$panel,
        $pdo,
        $rxPanelAlreadyLoadedForEditpanel,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        &$value,
        &$valuenew
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید چند روز مانده است به پایان اشتراک به کاربر اطلاع داده شود. زمان برحسب روز است" . $setting['daywarn'] . "روز", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getdaywarn", $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("setting", "daywarn", $text);
            break;
        case 3:

            $currentRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "infocard_color", "select");
            $current = (is_array($currentRow) && isset($currentRow['value'])) ? (string)$currentRow['value'] : 'yellow';
            $mark = function ($c) use ($current) { return $c === $current ? '✅ ' : ''; };
            $colorKeyboard = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $mark('yellow') . '🟡 زرد',   'callback_data' => 'infocard_setcolor_yellow'],
                        ['text' => $mark('green')  . '🟢 سبز',   'callback_data' => 'infocard_setcolor_green'],
                    ],
                    [
                        ['text' => $mark('red')    . '🔴 قرمز',  'callback_data' => 'infocard_setcolor_red'],
                        ['text' => $mark('blue')   . '🔵 آبی',   'callback_data' => 'infocard_setcolor_blue'],
                    ],
                    [
                        ['text' => $mark('purple') . '🟣 بنفش',  'callback_data' => 'infocard_setcolor_purple'],
                        ['text' => $mark('orange') . '🟠 نارنجی', 'callback_data' => 'infocard_setcolor_orange'],
                    ],
                    [
                        ['text' => '🔙 بازگشت', 'callback_data' => 'featcat_shop'],
                    ],
                ]
            ], JSON_UNESCAPED_UNICODE);
            if (function_exists('Editmessagetext') && isset($message_id)) {
                Editmessagetext($from_id, $message_id, "🎨 رنگ کارت مشخصات سرویس را انتخاب کنید:", $colorKeyboard);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🎨 رنگ کارت مشخصات سرویس را انتخاب کنید:", $colorKeyboard, 'HTML');
            }
            break;
        case 4:
            $newColor = $colorMatch[1];
            $existing = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "infocard_color", "select");
            if (is_array($existing) && isset($existing['Namevalue'])) {
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $newColor, "Namevalue", "infocard_color");
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO shopSetting (Namevalue, value) VALUES (:n, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)");
                    $stmt->execute([':n' => 'infocard_color', ':v' => $newColor]);
                    if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('shopSetting');
                } catch (\Throwable $e) {
                    error_log('infocard_color insert failed: ' . $e->getMessage());
                }
            }
            if (isset($callback_query_id)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => '✅ رنگ کارت تنظیم شد',
                    'show_alert' => false,
                    'cache_time' => 0,
                ]);
            }

            $currentRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "infocard_color", "select");
            $current = (is_array($currentRow) && isset($currentRow['value'])) ? (string)$currentRow['value'] : 'yellow';
            $mark = function ($c) use ($current) { return $c === $current ? '✅ ' : ''; };
            $colorKeyboard = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $mark('yellow') . '🟡 زرد',   'callback_data' => 'infocard_setcolor_yellow'],
                        ['text' => $mark('green')  . '🟢 سبز',   'callback_data' => 'infocard_setcolor_green'],
                    ],
                    [
                        ['text' => $mark('red')    . '🔴 قرمز',  'callback_data' => 'infocard_setcolor_red'],
                        ['text' => $mark('blue')   . '🔵 آبی',   'callback_data' => 'infocard_setcolor_blue'],
                    ],
                    [
                        ['text' => $mark('purple') . '🟣 بنفش',  'callback_data' => 'infocard_setcolor_purple'],
                        ['text' => $mark('orange') . '🟠 نارنجی', 'callback_data' => 'infocard_setcolor_orange'],
                    ],
                    [
                        ['text' => '🔙 بازگشت', 'callback_data' => 'featcat_shop'],
                    ],
                ]
            ], JSON_UNESCAPED_UNICODE);
            if (function_exists('Editmessagetext') && isset($message_id)) {
                Editmessagetext($from_id, $message_id, "🎨 رنگ کارت مشخصات سرویس را انتخاب کنید:", $colorKeyboard);
            }
            break;
        case 5:
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (!in_array($panel['subvip'], ['offsubvip', 'onsubvip'])) {
                \Faoxima\Core\Database\Db::update("marzban_panel", "subvip", "offsubvip", "code_panel", $panel['code_panel']);
                $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $panel['code_panel'], "select");
            }
            $customvlume = json_decode($panel['customvolume'], true);
            if (!is_array($customvlume)) { $customvlume = ['f' => '0', 'n' => '0', 'n2' => '0']; }
            $Bot_Status = json_encode(\Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildPanelFeatureKeyboard($panel, $customvlume, $textbotlang));
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
            break;
        case 6:
            $type = $dataget[1];
            $value = $dataget[2];
            $code_panel = $dataget[3];
            $panelCurrent = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $code_panel, "select");
            if (!is_array($panelCurrent)) {
                $panelCurrent = array();
            }
            if ($type == "stautsconfig") {
                $valuenew = (($panelCurrent['config'] ?? '') == "onconfig") ? "offconfig" : "onconfig";
                \Faoxima\Core\Database\Db::update("marzban_panel", "config", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "sublink") {
                $valuenew = (($panelCurrent['sublink'] ?? '') == "onsublink") ? "offsublink" : "onsublink";
                \Faoxima\Core\Database\Db::update("marzban_panel", "sublink", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "autofile") {
                $valuenew = (($panelCurrent['auto_file'] ?? 'offautofile') == "onautofile") ? "offautofile" : "onautofile";
                \Faoxima\Core\Database\Db::update("marzban_panel", "auto_file", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "statusbuy") {
                $valuenew = (($panelCurrent['status'] ?? '') == "active") ? "disable" : "active";
                \Faoxima\Core\Database\Db::update("marzban_panel", "status", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "statustest") {
                $valuenew = (($panelCurrent['TestAccount'] ?? '') == "ONTestAccount") ? "OFFTestAccount" : "ONTestAccount";
                \Faoxima\Core\Database\Db::update("marzban_panel", "TestAccount", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "versionpanel") {
                $valuenew = ((string)($panelCurrent['version_panel'] ?? '0') === "1") ? "0" : "1";
                \Faoxima\Core\Database\Db::update("marzban_panel", "version_panel", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "connecton") {
                $valuenew = (($panelCurrent['conecton'] ?? '') == "onconecton") ? "offconecton" : "onconecton";
                \Faoxima\Core\Database\Db::update("marzban_panel", "conecton", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "stautsextend") {
                $valuenew = (($panelCurrent['status_extend'] ?? '') == "on_extend") ? "off_extend" : "on_extend";
                \Faoxima\Core\Database\Db::update("marzban_panel", "status_extend", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "changeloc") {
                $valuenew = (($panelCurrent['changeloc'] ?? '') == "onchangeloc") ? "offchangeloc" : "onchangeloc";
                \Faoxima\Core\Database\Db::update("marzban_panel", "changeloc", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "inbocunddisable") {
                $valuenew = (($panelCurrent['inboundstatus'] ?? '') == "oninbounddisable") ? "offinbounddisable" : "oninbounddisable";
                \Faoxima\Core\Database\Db::update("marzban_panel", "inboundstatus", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "subvip") {
                $valuenew = (($panelCurrent['subvip'] ?? '') == "onsubvip") ? "offsubvip" : "onsubvip";
                \Faoxima\Core\Database\Db::update("marzban_panel", "subvip", $valuenew, "code_panel", $code_panel);
            } elseif ($type == "ipguard") {
                $valuenew = (($panelCurrent['ip_limit_guard'] ?? '') == "onipguard") ? "offipguard" : "onipguard";
                \Faoxima\Core\Database\Db::update("marzban_panel", "ip_limit_guard", $valuenew, "code_panel", $code_panel);
                if ($valuenew === "onipguard" && ($panelCurrent['type'] ?? '') === "rebecca" && method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaAccessInsightsPermissionStatus')) {
                    $rxIpGuardCheck = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaAccessInsightsPermissionStatus($panelCurrent['name_panel']);
                    if ($rxIpGuardCheck['status'] === true) {
                        sendmessage($from_id, "✅ محدودیت IP برای پنل «{$panelCurrent['name_panel']}» با موفقیت فعال شد.", null, 'html');
                    } elseif ($rxIpGuardCheck['reason'] === 'forbidden') {
                        sendmessage(
                            $from_id,
                            "⚠️ محدودیت IP فعال شد، اما با یک محدودیت مهم:\n\n"
                                . "API Key این پنل به لاگ هسته‌ی Xray دسترسی ندارد (سطح دسترسی سودو ادمین نیست)، بنابراین:\n\n"
                                . "▫️ تعداد کاربران مجاز طبق تنظیمات شما اعمال می‌شود (این بخش درست کار می‌کند)\n"
                                . "▫️ اما تعداد IP متصل و اعلان خودکار کرون (قطع IP تکراری) کار نخواهد کرد، چون این‌ها نیاز به خواندن لاگ Xray دارند\n\n"
                                . "برای فعال شدن کامل این قابلیت، API Key باید با دسترسی سودو ادمین ساخته شود.",
                            null,
                            'html'
                        );
                    } else {
                        sendmessage($from_id, "⚠️ محدودیت IP فعال شد، اما بررسی اتصال به پنل با خطا مواجه شد: " . htmlspecialchars((string) ($rxIpGuardCheck['msg'] ?? ''), ENT_QUOTES, 'UTF-8'), null, 'html');
                    }
                }
            } elseif ($type == "customstatusf") {
                $customvlume = json_decode($panelCurrent['customvolume'] ?? '', true);
                if (!is_array($customvlume)) {
                    $customvlume = array();
                }
                $valuenew = ((string)($customvlume['f'] ?? '0') === "1") ? "0" : "1";
                $customvlume['f'] = $valuenew;
                \Faoxima\Core\Database\Db::update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
            } elseif ($type == "customstatusn") {
                $customvlume = json_decode($panelCurrent['customvolume'] ?? '', true);
                if (!is_array($customvlume)) {
                    $customvlume = array();
                }
                $valuenew = ((string)($customvlume['n'] ?? '0') === "1") ? "0" : "1";
                $customvlume['n'] = $valuenew;
                \Faoxima\Core\Database\Db::update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
            } elseif ($type == "customstatusn2") {
                $customvlume = json_decode($panelCurrent['customvolume'] ?? '', true);
                if (!is_array($customvlume)) {
                    $customvlume = array();
                }
                $valuenew = ((string)($customvlume['n2'] ?? '0') === "1") ? "0" : "1";
                $customvlume['n2'] = $valuenew;
                \Faoxima\Core\Database\Db::update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
            } elseif ($type == "nationalnet") {
                $valuenew = (($panelCurrent['national_net_status'] ?? '') == "on_national_net") ? "off_national_net" : "on_national_net";
                \Faoxima\Core\Database\Db::update("marzban_panel", "national_net_status", $valuenew, "code_panel", $code_panel);
                if ($valuenew == "on_national_net") \Faoxima\Core\Database\Db::update("marzban_panel", "stock_source_panel", $code_panel, "code_panel", $code_panel);
            } elseif ($type == "on_hold_Test") {
                $valuenew = ((string)($panelCurrent['on_hold_test'] ?? '0') === "0") ? "1" : "0";
                \Faoxima\Core\Database\Db::update("marzban_panel", "on_hold_test", $valuenew, "code_panel", $code_panel);
            } elseif (preg_match('/^pf(\d+)$/', $type, $rxPfMatch)) {
                $rxFeatKeysOrdered = \Faoxima\Services\Panels\PanelFeatures::panel_feature_keys();
                $featureKey = $rxFeatKeysOrdered[(int) $rxPfMatch[1]] ?? '';
                if ($featureKey !== '') {
                    $shopFeatures = json_decode((string) ($panelCurrent['shop_features'] ?? ''), true);
                    if (!is_array($shopFeatures)) {
                        $shopFeatures = [];
                    }
                    $currentFeatureValue = (string) ($shopFeatures[$featureKey] ?? '0');
                    $shopFeatures[$featureKey] = ($currentFeatureValue === "1") ? "0" : "1";
                    $rxNewFeaturesJson = json_encode($shopFeatures, JSON_UNESCAPED_UNICODE);
                    \Faoxima\Core\Database\Db::update("marzban_panel", "shop_features", $rxNewFeaturesJson, "code_panel", $code_panel);
                    $panelCurrent['shop_features'] = $rxNewFeaturesJson;
                }
                $panel = $panelCurrent;
                $rxPanelAlreadyLoadedForEditpanel = true;
            }
            if (empty($rxPanelAlreadyLoadedForEditpanel)) {
                $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $code_panel, "select");
            }
            unset($rxPanelAlreadyLoadedForEditpanel);

            if (!is_array($panel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این پنل دیگر در دسترس نیست (احتمالاً حذف شده است).", $backadmin, 'HTML');
                return;
            }

            $customvlume = json_decode($panel['customvolume'], true);
            if (!is_array($customvlume)) {
                $customvlume = [];
            }
            $customvlume = array_merge([
                'f' => '0',
                'n' => '0',
                'n2' => '0',
            ], $customvlume);

            $Bot_Status = json_encode(\Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildPanelFeatureKeyboard($panel, $customvlume, $textbotlang));
            $rxToggleTitle = $textbotlang['Admin']['Status']['BotTitle'] ?? '⚙️ وضعیت';
            if ((int) $message_id > 0 && function_exists('rx_telegram_dispatch_concurrent')) {
                $rxToggleEditText = function_exists('applyPremiumEmojiTransform') ? applyPremiumEmojiTransform($rxToggleTitle, 'HTML') : $rxToggleTitle;
                $rxToggleEditKeyboard = $Bot_Status;
                if (function_exists('processKeyboardStyles')) {
                    $rxToggleEditKeyboard = processKeyboardStyles($rxToggleEditKeyboard, function_exists('rx_isAdminChat') && rx_isAdminChat($from_id));
                }
                if (function_exists('applyPremiumEmojiToKeyboard')) {
                    $rxToggleEditKeyboard = applyPremiumEmojiToKeyboard($rxToggleEditKeyboard);
                }
                if (function_exists('rx_sanitizeKeyboardButtons')) {
                    $rxToggleEditKeyboard = rx_sanitizeKeyboardButtons($rxToggleEditKeyboard);
                }
                $rxToggleConcurrentRequests = [
                    [
                        'method' => 'editmessagetext',
                        'datas' => [
                            'chat_id' => $from_id,
                            'message_id' => $message_id,
                            'text' => $rxToggleEditText,
                            'reply_markup' => $rxToggleEditKeyboard,
                            'parse_mode' => 'HTML',
                        ],
                    ],
                ];
                if (!empty($callback_query_id)) {
                    $rxToggleConcurrentRequests[] = [
                        'method' => 'answerCallbackQuery',
                        'datas' => ['callback_query_id' => $callback_query_id, 'cache_time' => 0],
                    ];
                }
                rx_telegram_dispatch_concurrent($rxToggleConcurrentRequests);
            } else {
                Editmessagetext($from_id, $message_id, $rxToggleTitle, $Bot_Status);
                if (!empty($callback_query_id) && function_exists('telegram')) {
                    try {
                        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
                    } catch (\Throwable $e) {
                    }
                }
            }
            break;
        }

        return true;
    }
}
