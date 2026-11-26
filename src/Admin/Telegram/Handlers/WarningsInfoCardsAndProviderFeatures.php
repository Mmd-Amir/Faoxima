<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use PDOException;

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
            $panelName = (string) ($user['Processing_value'] ?? '');
            $panel = $panelName === '' ? false : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select", ['cache' => false]);
            if (!is_array($panel) || empty($panel['code_panel'])) {
                self::replyPanelUnavailable($from_id);
                return;
            }
            if (!in_array($panel['subvip'] ?? null, ['offsubvip', 'onsubvip'], true)) {
                $panel = self::saveExistingPanel($pdo, $panel, ['subvip' => 'offsubvip'], $from_id, $backadmin);
                if ($panel === null) {
                    return;
                }
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
            $panelCurrent = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $code_panel, "select", ['cache' => false]);
            if (!is_array($panelCurrent) || empty($panelCurrent['code_panel'])) {
                self::replyPanelUnavailable($from_id);
                return;
            }
            $changes = [];
            if ($type == "stautsconfig") {
                $valuenew = (($panelCurrent['config'] ?? '') == "onconfig") ? "offconfig" : "onconfig";
                $changes['config'] = $valuenew;
            } elseif ($type == "sublink") {
                $valuenew = (($panelCurrent['sublink'] ?? '') == "onsublink") ? "offsublink" : "onsublink";
                $changes['sublink'] = $valuenew;
            } elseif ($type == "autofile") {
                $valuenew = (($panelCurrent['auto_file'] ?? 'offautofile') == "onautofile") ? "offautofile" : "onautofile";
                $changes['auto_file'] = $valuenew;
            } elseif ($type == "statusbuy") {
                $valuenew = (($panelCurrent['status'] ?? '') == "active") ? "disable" : "active";
                $changes['status'] = $valuenew;
            } elseif ($type == "statustest") {
                $valuenew = (($panelCurrent['TestAccount'] ?? '') == "ONTestAccount") ? "OFFTestAccount" : "ONTestAccount";
                $changes['TestAccount'] = $valuenew;
            } elseif ($type == "versionpanel") {
                $valuenew = ((string)($panelCurrent['version_panel'] ?? '0') === "1") ? "0" : "1";
                $changes['version_panel'] = $valuenew;
            } elseif ($type == "connecton") {
                $valuenew = (($panelCurrent['conecton'] ?? '') == "onconecton") ? "offconecton" : "onconecton";
                $changes['conecton'] = $valuenew;
            } elseif ($type == "stautsextend") {
                $valuenew = (($panelCurrent['status_extend'] ?? '') == "on_extend") ? "off_extend" : "on_extend";
                $changes['status_extend'] = $valuenew;
            } elseif ($type == "changeloc") {
                $valuenew = (($panelCurrent['changeloc'] ?? '') == "onchangeloc") ? "offchangeloc" : "onchangeloc";
                $changes['changeloc'] = $valuenew;
            } elseif ($type == "inbocunddisable") {
                $valuenew = (($panelCurrent['inboundstatus'] ?? '') == "oninbounddisable") ? "offinbounddisable" : "oninbounddisable";
                $changes['inboundstatus'] = $valuenew;
            } elseif ($type == "subvip") {
                $valuenew = (($panelCurrent['subvip'] ?? '') == "onsubvip") ? "offsubvip" : "onsubvip";
                $changes['subvip'] = $valuenew;
            } elseif ($type == "ipguard") {
                $valuenew = (($panelCurrent['ip_limit_guard'] ?? '') == "onipguard") ? "offipguard" : "onipguard";
                $changes['ip_limit_guard'] = $valuenew;
            } elseif ($type == "customstatusf") {
                $customvlume = json_decode($panelCurrent['customvolume'] ?? '', true);
                if (!is_array($customvlume)) {
                    $customvlume = array();
                }
                $valuenew = ((string)($customvlume['f'] ?? '0') === "1") ? "0" : "1";
                $customvlume['f'] = $valuenew;
                $changes['customvolume'] = json_encode($customvlume);
            } elseif ($type == "customstatusn") {
                $customvlume = json_decode($panelCurrent['customvolume'] ?? '', true);
                if (!is_array($customvlume)) {
                    $customvlume = array();
                }
                $valuenew = ((string)($customvlume['n'] ?? '0') === "1") ? "0" : "1";
                $customvlume['n'] = $valuenew;
                $changes['customvolume'] = json_encode($customvlume);
            } elseif ($type == "customstatusn2") {
                $customvlume = json_decode($panelCurrent['customvolume'] ?? '', true);
                if (!is_array($customvlume)) {
                    $customvlume = array();
                }
                $valuenew = ((string)($customvlume['n2'] ?? '0') === "1") ? "0" : "1";
                $customvlume['n2'] = $valuenew;
                $changes['customvolume'] = json_encode($customvlume);
            } elseif ($type == "nationalnet") {
                $valuenew = (($panelCurrent['national_net_status'] ?? '') == "on_national_net") ? "off_national_net" : "on_national_net";
                $changes['national_net_status'] = $valuenew;
                if ($valuenew == "on_national_net") {
                    $changes['stock_source_panel'] = $code_panel;
                }
            } elseif ($type == "on_hold_Test") {
                $valuenew = ((string)($panelCurrent['on_hold_test'] ?? '0') === "0") ? "1" : "0";
                $changes['on_hold_test'] = $valuenew;
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
                    $changes['shop_features'] = $rxNewFeaturesJson;
                }
            }
            $panel = $changes === [] ? $panelCurrent : self::saveExistingPanel($pdo, $panelCurrent, $changes, $from_id, $backadmin);
            if ($panel === null) {
                return;
            }

            if (($changes['ip_limit_guard'] ?? null) === 'onipguard' && ($panel['type'] ?? '') === "rebecca" && method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaAccessInsightsPermissionStatus')) {
                $rxIpGuardCheck = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaAccessInsightsPermissionStatus($panel['name_panel']);
                if ($rxIpGuardCheck['status'] === true) {
                    sendmessage($from_id, "✅ محدودیت IP برای پنل «{$panel['name_panel']}» با موفقیت فعال شد.", null, 'html');
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

    private static function saveExistingPanel(?PDO $pdo, array $panel, array $changes, $adminId, $backKeyboard): ?array
    {
        try {
            $pdo ??= \Faoxima\Core\Database\Db::getDatabaseConnection();
            if (!$pdo instanceof PDO) {
                throw new PDOException('Database connection unavailable');
            }

            $assignments = [];
            foreach (array_keys($changes) as $column) {
                $assignments[] = $column . ' = ?';
            }
            $identity = [$panel['id'], $panel['code_panel']];
            $stmt = $pdo->prepare('UPDATE marzban_panel SET ' . implode(', ', $assignments) . ' WHERE id = ? AND code_panel = ?');
            $stmt->execute([...array_values($changes), ...$identity]);
            if ($stmt->rowCount() > 0) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('marzban_panel');
                \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('marzban_panel');
            }

            $stmt = $pdo->prepare('SELECT * FROM marzban_panel WHERE id = ? AND code_panel = ? LIMIT 1');
            $stmt->execute($identity);
            $updatedPanel = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($updatedPanel)) {
                return $updatedPanel;
            }
        } catch (PDOException $e) {
            error_log('[admin panel features] ' . $e->getMessage());
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($adminId, "❌ ذخیرهٔ تنظیمات پنل ممکن نشد. لطفاً دوباره تلاش کنید.", $backKeyboard, 'HTML');
            return null;
        }

        self::replyPanelUnavailable($adminId);
        return null;
    }

    private static function replyPanelUnavailable($adminId): void
    {
        $keyboard = json_encode(['inline_keyboard' => [[
            ['text' => 'بازگشت به مدیریت پنل‌ها', 'callback_data' => 'admin_panels'],
        ]]], JSON_UNESCAPED_UNICODE);
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($adminId, "❌ پنل انتخاب‌شده در دسترس نیست. لطفاً دوباره پنل را انتخاب کنید.", $keyboard, 'HTML');
    }
}
