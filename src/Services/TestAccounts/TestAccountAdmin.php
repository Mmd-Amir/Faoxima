<?php

namespace Faoxima\Services\TestAccounts;

use PDO;
use Throwable;

final class TestAccountAdmin
{
    public static function rx_test_admin_edit_settings(array $panel)
    {
        $current = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_panel_settings($panel);
        if ($current['mode'] === 'panel') {
            return $current;
        }
        $legacy = $current['mode'] === 'legacy' ? $current : \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_panel_settings(array_merge($panel, ['test_settings' => null]));
        $setting = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_setting();
        $limit = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_int_in_range(trim((string) ($setting['limit_usertest_all'] ?? '')), 1, RX_TEST_MAX_LIMIT);
        return [
            'mode'             => 'panel',
            'limit'            => $limit === null ? 1 : $limit,
            'limit_unlimited'  => false,
            'time_hours'       => $legacy['time_unlimited'] ? 0 : $legacy['time_hours'],
            'time_unlimited'   => $legacy['time_unlimited'],
            'volume_mb'        => $legacy['volume_unlimited'] ? 0 : $legacy['volume_mb'],
            'volume_unlimited' => $legacy['volume_unlimited'],
            'audience_mode'    => RX_TEST_AUDIENCE_ALL,
            'quota_generation' => 1,
            'quota_changed_at' => 0,
        ];
    }

    public static function rx_test_admin_save_settings(array $panel, $field, $value, $audience = null)
    {
        $pdo = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_pdo();
        $code = (string) ($panel['code_panel'] ?? '');
        if ($pdo === null || !\Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_valid_code($code) || !\Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_ensure_settings_column()) {
            return false;
        }
        if (!in_array($field, ['limit', 'time', 'volume'], true)) {
            return false;
        }
        if ($audience !== null && !in_array($audience, [RX_TEST_AUDIENCE_ALL, RX_TEST_AUDIENCE_NEW], true)) {
            return false;
        }
        if ($value !== 'unlimited') {
            $max = $field === 'limit' ? RX_TEST_MAX_LIMIT : ($field === 'time' ? RX_TEST_MAX_HOURS : RX_TEST_MAX_MB);
            $value = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_int_in_range(\Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_normalize_digits($value), 1, $max);
            if ($value === null) {
                return false;
            }
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $row = self::rx_test_admin_load_panel($code);
            if ($row === null) {
                return false;
            }
            $previousRaw = $row['test_settings'] ?? null;
            $settings = self::rx_test_admin_edit_settings($row);
            $extra = [];
            if (is_string($previousRaw) && trim($previousRaw) !== '') {
                $decoded = json_decode($previousRaw, true);
                if (is_array($decoded) && \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_panel_settings($row)['mode'] === 'panel') {
                    $extra = $decoded;
                }
            }
            if ($field === 'limit') {
                $settings['limit_unlimited'] = $value === 'unlimited';
                $settings['limit'] = $value === 'unlimited' ? 0 : $value;
                if ($audience !== null) {
                    $settings['audience_mode'] = $audience;
                }
                $settings['quota_generation'] = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_panel_settings($row)['mode'] === 'panel' ? \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_generation($settings) + 1 : 1;
                $settings['quota_changed_at'] = time();
            } elseif ($field === 'time') {
                $settings['time_unlimited'] = $value === 'unlimited';
                $settings['time_hours'] = $value === 'unlimited' ? 0 : $value;
            } else {
                $settings['volume_unlimited'] = $value === 'unlimited';
                $settings['volume_mb'] = $value === 'unlimited' ? 0 : $value;
            }
            $payload = array_merge($extra, [
                'v'                => 1,
                'limit'            => (int) $settings['limit'],
                'limit_unlimited'  => (bool) $settings['limit_unlimited'],
                'time_hours'       => (int) $settings['time_hours'],
                'time_unlimited'   => (bool) $settings['time_unlimited'],
                'volume_mb'        => (int) $settings['volume_mb'],
                'volume_unlimited' => (bool) $settings['volume_unlimited'],
                'audience_mode'    => (string) $settings['audience_mode'],
                'quota_generation' => \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_generation($settings),
                'quota_changed_at' => (int) $settings['quota_changed_at'],
            ]);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (!is_array(\Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_parse_settings_json($json))) {
                return false;
            }
            try {
                if ($previousRaw === null) {
                    $stmt = $pdo->prepare("UPDATE marzban_panel SET test_settings = ?, time_usertest = ?, val_usertest = ? WHERE code_panel = ? AND test_settings IS NULL");
                    $stmt->execute([$json, (string) \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_stored_hours($settings), (string) \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_stored_mb($settings), $code]);
                } else {
                    $stmt = $pdo->prepare("UPDATE marzban_panel SET test_settings = ?, time_usertest = ?, val_usertest = ? WHERE code_panel = ? AND test_settings = ?");
                    $stmt->execute([$json, (string) \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_stored_hours($settings), (string) \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_stored_mb($settings), $code, (string) $previousRaw]);
                }
            } catch (Throwable $e) {
                error_log('[test_account_policy] save settings: ' . $e->getMessage());
                return false;
            }
            if ($stmt->rowCount() === 1 || ($previousRaw !== null && (string) $previousRaw === $json)) {
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                    \Faoxima\Core\Database\SelectCache::clearSelectCache('marzban_panel');
                }
                return true;
            }
        }
        return false;
    }

    public static function rx_test_admin_load_panel($code)
    {
        $pdo = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_pdo();
        if ($pdo === null || !\Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_valid_code((string) $code)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE code_panel = ? LIMIT 1");
            $stmt->execute([(string) $code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        return is_array($row) ? $row : null;
    }

    public static function rx_test_audience_label($audience)
    {
        return $audience === RX_TEST_AUDIENCE_NEW ? 'کاربران جدید ۲۴ ساعت گذشته' : 'همه کاربران';
    }

    public static function rx_test_admin_menu_render(array $panel)
    {
        $settings = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_panel_settings($panel);
        $code = (string) $panel['code_panel'];
        $unlimited = '♾ نامحدود';
        $lines = ['🧪 <b>تنظیمات اکانت تست</b>', '', '🖥 پنل: ' . htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8')];
        if ($settings['mode'] === 'invalid') {
            $lines[] = '⚠️ تنظیمات ذخیره‌شده نامعتبر است؛ تا اصلاح، تست از این پنل صادر نمی‌شود.';
            $settings = self::rx_test_admin_edit_settings(array_merge($panel, ['test_settings' => null]));
            $settings['mode'] = 'invalid';
        }
        if ($settings['mode'] === 'legacy') {
            $lines[] = '📌 حالت: قدیمی (سهمیه عمومی هر کاربر)';
        } elseif ($settings['mode'] === 'panel') {
            $lines[] = '📌 حالت: اختصاصی همین پنل';
        }
        $lines[] = '👥 اعمال روی: ' . self::rx_test_audience_label($settings['audience_mode'] ?? RX_TEST_AUDIENCE_ALL);
        if ($settings['mode'] === 'legacy') {
            $lines[] = '👤 سقف تست هر کاربر: طبق سهمیه عمومی کاربر';
        } else {
            $lines[] = '👤 سقف تست هر کاربر: ' . (!empty($settings['limit_unlimited']) ? $unlimited : (int) $settings['limit']);
        }
        $lines[] = '⏳ مدت زمان: ' . (!empty($settings['time_unlimited']) ? $unlimited : ((int) $settings['time_hours'] . ' ساعت'));
        $lines[] = '💾 حجم: ' . (!empty($settings['volume_unlimited']) ? $unlimited : ((int) $settings['volume_mb'] . ' مگابایت'));
        $lines[] = '📊 تست‌های دریافت‌شده: ' . \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_issued_count($panel, $settings['mode'] === 'invalid' ? null : $settings);
        $lines[] = '';
        $lines[] = 'با ثبت اولین تغییر، این پنل به حالت اختصاصی می‌رود و سقف تست برای هر پنل جداگانه شمرده می‌شود. تغییر سقف تست، شمارنده این دوره را صفر می‌کند.';
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '👤 تغییر سقف تست', 'callback_data' => 'tset_limit_' . $code],
                    ['text' => '♾ سقف نامحدود', 'callback_data' => 'tset_ulimit_' . $code],
                ],
                [
                    ['text' => '⏳ تغییر مدت زمان', 'callback_data' => 'tset_time_' . $code],
                    ['text' => '♾ زمان نامحدود', 'callback_data' => 'tset_utime_' . $code],
                ],
                [
                    ['text' => '💾 تغییر حجم', 'callback_data' => 'tset_vol_' . $code],
                    ['text' => '♾ حجم نامحدود', 'callback_data' => 'tset_uvol_' . $code],
                ],
                [
                    ['text' => '🔙 بازگشت به مدیریت پنل', 'callback_data' => 'tset_back_' . $code],
                ],
            ],
        ];
        return [
            'text'     => implode("\n", $lines),
            'keyboard' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
        ];
    }

    public static function rx_test_admin_audience_menu_render(array $panel)
    {
        $code = (string) $panel['code_panel'];
        $settings = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_panel_settings($panel);
        $text = "👤 <b>تغییر سقف تست</b>\n\n🖥 پنل: " . htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8')
            . "\n👥 اعمال فعلی: " . self::rx_test_audience_label($settings['audience_mode'] ?? RX_TEST_AUDIENCE_ALL)
            . "\n\nسقف جدید روی کدام کاربران اعمال شود؟\n⚠️ با ثبت سقف جدید، شمارنده تست‌های این پنل از صفر شروع می‌شود.";
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🆕 اعمال روی کاربران جدید ۲۴ ساعت گذشته', 'callback_data' => 'tset_anew_' . $code]],
                [['text' => '👥 اعمال روی تمامی کاربران', 'callback_data' => 'tset_aall_' . $code]],
                [['text' => '🔙 بازگشت', 'callback_data' => 'tset_menu_' . $code]],
            ],
        ];
        return [
            'text'     => $text,
            'keyboard' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
        ];
    }

    public static function rx_test_admin_flow_state($code, $audience)
    {
        return 'tset:' . $code . ':' . $audience;
    }

    public static function rx_test_admin_parse_flow_state($raw, $expectedCode)
    {
        if (!is_string($raw) || !preg_match('/^tset:([A-Za-z0-9_-]{1,100}):(all_users|new_users_24h)$/', $raw, $m)) {
            return null;
        }
        return $m[1] === (string) $expectedCode ? $m[2] : null;
    }

    public static function rx_test_audience_denied_text()
    {
        $fallback = '❌ دریافت اکانت تست این پنل فقط برای کاربرانی فعال است که در ۲۴ ساعت گذشته عضو شده‌اند.';
        return method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_textbot_get') ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_testaccount_audience_restricted', $fallback) : $fallback;
    }

    public static function rx_test_overview(array $user, $isAdmin)
    {
        $overview = ['enabled' => \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_feature_enabled(), 'gate' => null, 'panels' => [], 'any_available' => false, 'reason' => ''];
        if (!$overview['enabled']) {
            $overview['reason'] = 'feature_disabled';
            return $overview;
        }
        $overview['gate'] = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_verification_gate($user, $isAdmin);
        $audienceOnly = true;
        foreach (\Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_eligible_panels($user) as $panel) {
            $settings = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_panel_settings($panel);
            $quota = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_quota_state($user, $panel, $settings, $isAdmin);
            $overview['panels'][] = ['panel' => $panel, 'settings' => $settings, 'quota' => $quota];
            if ($quota['can_create']) {
                $overview['any_available'] = true;
            } elseif ($quota['reason'] !== 'audience_restricted') {
                $audienceOnly = false;
            }
        }
        if ($overview['gate'] !== null) {
            $overview['reason'] = 'verify_' . $overview['gate'];
        } elseif (empty($overview['panels'])) {
            $overview['reason'] = 'no_panel';
        } elseif (!$overview['any_available']) {
            $overview['reason'] = $audienceOnly ? 'audience_restricted' : 'limit_reached';
        }
        return $overview;
    }
}
