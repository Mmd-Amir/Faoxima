<?php

namespace Faoxima\Admin\Telegram\Handlers;

use Faoxima\Admin\Keyboard\FeatureToggleMenu;
use Faoxima\Admin\Security\TelegramAccess;
use Faoxima\Core\Cron\Crontab;
use Faoxima\Core\Database\Db;
use PDO;

final class FeatureToggle
{
    private const CALLBACK_TOGGLES = [
        'statusbot' => ['Bot_Status', 'botstatuson', 'botstatusoff', 'botstatuson'],
        'usernamebtn' => ['NotUser', 'onnotuser', 'offnotuser', 'onnotuser'],
        'notifnew' => ['statusnewuser', 'onnewuser', 'offnewuser', 'onnewuser'],
        'showagent' => ['statusagentrequest', 'onrequestagent', 'offrequestagent', 'onrequestagent'],
        'role' => ['roll_Status', 'rolleon', 'rolleoff', 'rolleon'],
        'statussupportpv' => ['statussupportpv', 'onpvsupport', 'offpvsupport', 'onpvsupport'],
        'statusnamecustom' => ['statusnamecustom', 'onnamecustom', 'offnamecustom', 'onnamecustom'],
        'bulkbuy' => ['bulkbuy', 'onbulk', 'offbulk', 'onbulk'],
        'verifybyuser' => ['verifybucodeuser', 'onverify', 'offverify', 'onverify'],
        'authscope' => ['auth_scope', 'newonly', 'all', 'newonly'],
        'wheelagent' => ['wheelagent', '1', '0', '1'],
        'keyconfig' => ['status_keyboard_config', '1', '0', '1'],
        'Lotteryagent' => ['Lotteryagent', '1', '0', '1'],
        'compycart' => ['statuscopycart', '1', '0', '1'],
        'score' => ['scorestatus', '1', '0', '1'],
        'wheel_luck' => ['wheelـluck', '1', '0', '1'],
        'affiliatesstatus' => ['affiliatesstatus', 'onaffiliates', 'offaffiliates', 'onaffiliates'],
        'btn_status_category' => ['categoryhelp', '1', '0', '1'],
        'wheelagentfirst' => ['statusfirstwheel', '1', '0', '1'],
        'changeloc' => ['statuslimitchangeloc', '1', '0', '1'],
        'Debtsettlement' => ['Debtsettlement', '1', '0', '1'],
        'Dice' => ['Dice', '1', '0', '1'],
        'statusnamecustomf' => ['statusnoteforf', '1', '0', '1'],
    ];

    private const CRON_FIELDS = [
        'crontest' => 'test',
        'cronday' => 'day',
        'cronvolume' => 'volume',
        'notifremove' => 'remove',
        'notifremove_volume' => 'remove_volume',
        'uptime_node' => 'uptime_node',
        'uptime_panel' => 'uptime_panel',
        'on_hold' => 'on_hold',
    ];

    public static function handle(
        TelegramAccess $access,
        PDO $pdo,
        array &$setting,
        string $type,
        string $value,
        array $adminrulecheck,
        $callback_query_id,
        $message_id,
        array $textbotlang,
    ): ?bool {
        $from_id = $access->senderId;
        Crontab::normalizeCronStatus($setting['cron_status'] ?? null, true);

        if (isset(self::CALLBACK_TOGGLES[$type])) {
            [$field, $enabled, $whenEnabled, $whenDisabled] = self::CALLBACK_TOGGLES[$type];
            Db::update('setting', $field, $value == $enabled ? $whenEnabled : $whenDisabled);
        } elseif (isset(self::CRON_FIELDS[$type])) {
            $cron = Crontab::normalizeCronStatus($setting['cron_status'] ?? null);
            $cron[self::CRON_FIELDS[$type]] = !$value;
            Db::update('setting', 'cron_status', json_encode($cron));
        } elseif ($type === 'get_number') {
            $current = $setting['get_number'] ?? 'offAuthenticationphone';
            $valuenew = ($current === "onAuthenticationphone") ? "offAuthenticationphone" : "onAuthenticationphone";
            \Faoxima\Core\Database\Db::update("setting", "get_number", $valuenew);
            $setting['get_number'] = $valuenew;
        } elseif ($type === 'Authenticationiran') {
            $current = $setting['iran_number'] ?? 'offAuthenticationiran';
            $valuenew = ($current === "onAuthenticationiran") ? "offAuthenticationiran" : "onAuthenticationiran";
            \Faoxima\Core\Database\Db::update("setting", "iran_number", $valuenew);
            $setting['iran_number'] = $valuenew;
        } elseif ($type === 'inlinebtnmain' || $type === 'user_rich_buttons') {
            \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_adminToggle(
                $type == "inlinebtnmain" ? 'glass' : 'rich',
                (string)$value,
                ($adminrulecheck['rule'] ?? '') === 'administrator',
                $callback_query_id ?? null
            );
            \Faoxima\Admin\Telegram\FeatureViews::rxRenderFeatureStatus('glass_mode', $from_id);
            return null;
        } elseif ($type === 'auto_remove_kb') {
            $valuenew = ($value === "on") ? "off" : "on";
            \Faoxima\Core\Database\Db::update("setting", "auto_remove_reply_keyboard", $valuenew);
            $setting['auto_remove_reply_keyboard'] = $valuenew;
            \Faoxima\Admin\Telegram\FeatureViews::rxRenderFeatureStatus('glass_mode', $from_id);
            return null;
        } elseif ($type === 'verifystart') {
            $current = $setting['verifystart'] ?? 'offverify';
            $valuenew = ($current === "onverify") ? "offverify" : "onverify";
            \Faoxima\Core\Database\Db::update("setting", "verifystart", $valuenew);
            $setting['verifystart'] = $valuenew;
        } elseif ($type === 'cardverify') {
            $current = $setting['card_verify_status'] ?? 'offcardverify';
            $valuenew = ($current === 'oncardverify') ? 'offcardverify' : 'oncardverify';
            \Faoxima\Core\Database\Db::update("setting", "card_verify_status", $valuenew);
            $setting['card_verify_status'] = $valuenew;
        } elseif ($type === 'forced_miniapp') {
            $valuenew = ($value === '1') ? '0' : '1';
            \Faoxima\Core\Database\Db::update("setting", "forced_miniapp_mode", $valuenew);
            $setting['forced_miniapp_mode'] = $valuenew;
        } elseif ($type === 'miniappticket') {
            $valuenew = ($value === '1') ? '0' : '1';
            \Faoxima\Core\Database\Db::update("setting", "miniapp_ticket_mode", $valuenew);
            $setting['miniapp_ticket_mode'] = $valuenew;
        } elseif ($type === 'premiumemoji') {

            $rxPemCurrent = (string)($setting['premium_emoji_status'] ?? '0');
            $valuenew = ($rxPemCurrent === '1') ? '0' : '1';
            \Faoxima\Core\Database\Db::update("setting", "premium_emoji_status", $valuenew);

            $setting['premium_emoji_status'] = $valuenew;
            if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }

            if (method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxRenderPremiumEmojiPanel')) {
                \Faoxima\Admin\Telegram\PremiumEmoji::rxRenderPremiumEmojiPanel($from_id, 1);
            }
            return null;
        } elseif ($type === 'redis_enabled') {
            if ($value === "1") {
                $valuenew = "0";
            } else {
                if (!method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_extension_available') || !\Faoxima\Core\Cache\RedisCache::rx_redis_extension_available()) {
                    if (!empty($callback_query_id)) {
                        try {
                            telegram('answerCallbackQuery', [
                                'callback_query_id' => $callback_query_id,
                                'text'              => method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_extension_guidance') ? \Faoxima\Core\Cache\RedisCache::rx_redis_extension_guidance() : '⚠️ افزونه Redis در PHP فعال نیست.',
                                'show_alert'        => true,
                            ]);
                        } catch (\Throwable $rxRedisToggleCbErr) {}
                    }
                    \Faoxima\Admin\Telegram\FeatureViews::rxRenderFeatureStatus('redis', $from_id);
                    return null;
                }
                $valuenew = "1";
            }
            \Faoxima\Core\Database\Db::update("setting", "redis_enabled", $valuenew);
            if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_reset_resolution')) {
                \Faoxima\Core\Cache\RedisCache::rx_redis_reset_resolution();
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('setting');
            }
            $setting['redis_enabled'] = $valuenew;
            \Faoxima\Admin\Telegram\FeatureViews::rxRenderFeatureStatus('redis', $from_id);
            return null;
        } elseif ($type === 'randomwallet') {
            $valuenew = ($value === '1') ? '0' : '1';
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "randomwallet_status");
        } elseif ($type === 'btnstautslanguage') {
            if ($setting['languageru'] == "1") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "زبان روسیه ای روشن است و نمی توانید زبان انگلیسی را تغییر وضعیت دهید", null, 'HTML');
                return null;
            }
            if ($value == "1") {
                $valuenew = "0";
            } else {
                $valuenew = "1";
            }
            \Faoxima\Core\Database\Db::update("setting", "languageen", $valuenew);
        } elseif ($type === 'btnstautslanguageru') {
            if ($setting['languageen'] == "1") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "زبان انگلیسی روشن است و نمی توانید زبان روسیه ای را تغییر وضعیت دهید", null, 'HTML');
                return null;
            }
            if ($value == "1") {
                $valuenew = "0";
            } else {
                $valuenew = "1";
            }
            \Faoxima\Core\Database\Db::update("setting", "languageru", $valuenew);
        } elseif ($type === 'infocard') {

            $valuenew = ($value === '1') ? '0' : '1';
            $existing = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "infocard_status", "select");
            if (is_array($existing) && isset($existing['Namevalue'])) {
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $valuenew, "Namevalue", "infocard_status");
            } else {

                try {
                    $stmt = $pdo->prepare("INSERT INTO shopSetting (Namevalue, value) VALUES (:n, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)");
                    $stmt->execute([':n' => 'infocard_status', ':v' => $valuenew]);
                    if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('shopSetting');
                } catch (\Throwable $e) {
                    error_log('infocard_status insert failed: ' . $e->getMessage());
                }
            }
        }

        $setting = Db::select("setting", "*");
        $cron = Crontab::normalizeCronStatus($setting['cron_status'] ?? null, true);
        [$title, $rows] = FeatureToggleMenu::build($pdo, $type, $setting, $textbotlang, $cron);
        Editmessagetext($from_id, $message_id, $title, json_encode(['inline_keyboard' => $rows]));
        return true;
    }
}
