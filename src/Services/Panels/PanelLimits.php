<?php

namespace Faoxima\Services\Panels;

use Throwable;

final class PanelLimits
{
    public static function panel_limit_count_statuses()
    {
        return ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'processing', 'manual_review'];
    }

    public static function panel_limit_is_unlimited_raw($rawLimit)
    {
        $rawLimit = strtolower(trim((string) $rawLimit));
        return $rawLimit === '' || in_array($rawLimit, ['unlimited', 'unlimted'], true);
    }

    public static function panel_limit_used_count($panelName, $resetAt = null)
    {
        global $pdo;

        $statuses = self::panel_limit_count_statuses();
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = $statuses;
        $sql = "SELECT COUNT(*) FROM invoice WHERE Status IN ({$placeholders}) AND Service_location = ?";
        $params[] = (string) $panelName;

        $resetAt = (int) $resetAt;
        if ($resetAt > 0) {
            $sql .= " AND time_sell >= ?";
            $params[] = $resetAt;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function panel_limit_stats($panel)
    {
        $row = \Faoxima\Services\Panels\PanelFeatures::panel_feature_resolve_row($panel);
        if (!is_array($row) || empty($row['name_panel'])) {
            return null;
        }

        $panelName = (string) $row['name_panel'];
        $rawLimit = (string) ($row['limit_panel'] ?? '');
        $resetAt = isset($row['limit_reset_at']) ? (int) $row['limit_reset_at'] : 0;
        $unlimited = self::panel_limit_is_unlimited_raw($rawLimit);

        $used = self::panel_limit_used_count($panelName, $resetAt);

        $limit = $unlimited ? null : (preg_match('/^\d+$/', trim($rawLimit)) ? (int) trim($rawLimit) : null);
        $remaining = ($limit !== null) ? max(0, $limit - $used) : null;

        return [
            'name_panel' => $panelName,
            'unlimited' => $unlimited,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'reset_at' => $resetAt > 0 ? $resetAt : null,
        ];
    }

    public static function panel_limit_lock_acquire($panelName)
    {
        global $pdo;
        $lockKey = 'faoxima_panel_limit_' . md5((string) $panelName);
        try {
            $stmt = $pdo->prepare("SELECT GET_LOCK(?, 3)");
            $stmt->execute([$lockKey]);
            if ((int) $stmt->fetchColumn() === 1) {
                return $lockKey;
            }
        } catch (Throwable $e) {
            error_log('panel_limit_lock_acquire: ' . $e->getMessage());
        }
        return null;
    }

    public static function panel_limit_lock_release($lockKey)
    {
        global $pdo;
        if ($lockKey === null) {
            return;
        }
        try {
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?)");
            $stmt->execute([$lockKey]);
        } catch (Throwable $e) {
            error_log('panel_limit_lock_release: ' . $e->getMessage());
        }
    }

    public static function panel_limit_set($panel, $newLimit)
    {
        $row = \Faoxima\Services\Panels\PanelFeatures::panel_feature_resolve_row($panel);
        if (!is_array($row) || empty($row['name_panel'])) {
            return false;
        }
        $panelName = (string) $row['name_panel'];
        $newLimit = trim((string) $newLimit);
        if (!preg_match('/^\d+$/', $newLimit)) {
            return false;
        }

        $lockKey = self::panel_limit_lock_acquire($panelName);
        try {
            \Faoxima\Core\Database\Db::update("marzban_panel", "limit_panel", $newLimit, "name_panel", $panelName);
            \Faoxima\Core\Database\Db::update("marzban_panel", "limit_reset_at", (string) time(), "name_panel", $panelName);
        } finally {
            self::panel_limit_lock_release($lockKey);
        }
        return true;
    }

    public static function panel_limit_reset($panel)
    {
        $row = \Faoxima\Services\Panels\PanelFeatures::panel_feature_resolve_row($panel);
        if (!is_array($row) || empty($row['name_panel'])) {
            return false;
        }
        $panelName = (string) $row['name_panel'];

        $lockKey = self::panel_limit_lock_acquire($panelName);
        try {
            \Faoxima\Core\Database\Db::update("marzban_panel", "limit_reset_at", (string) time(), "name_panel", $panelName);
        } finally {
            self::panel_limit_lock_release($lockKey);
        }
        return true;
    }

    public static function panel_limit_set_unlimited($panel)
    {
        $row = \Faoxima\Services\Panels\PanelFeatures::panel_feature_resolve_row($panel);
        if (!is_array($row) || empty($row['name_panel'])) {
            return false;
        }
        $panelName = (string) $row['name_panel'];

        $lockKey = self::panel_limit_lock_acquire($panelName);
        try {
            \Faoxima\Core\Database\Db::update("marzban_panel", "limit_panel", "unlimited", "name_panel", $panelName);
        } finally {
            self::panel_limit_lock_release($lockKey);
        }
        return true;
    }

    public static function panel_creation_limit_reached_unlocked($panel)
    {
        $row = \Faoxima\Services\Panels\PanelFeatures::panel_feature_resolve_row($panel);
        if (!is_array($row) || empty($row['name_panel'])) {
            return true;
        }

        $rawLimit = (string) ($row['limit_panel'] ?? '');
        if (self::panel_limit_is_unlimited_raw($rawLimit)) {
            return false;
        }
        $rawLimit = trim($rawLimit);
        if (!preg_match('/^\d+$/', $rawLimit)) {
            return false;
        }

        $panelName = (string) $row['name_panel'];
        $resetAt = isset($row['limit_reset_at']) ? (int) $row['limit_reset_at'] : 0;
        $used = self::panel_limit_used_count($panelName, $resetAt);
        return $used >= (int) $rawLimit;
    }

    public static function panel_creation_limit_reached($panel)
    {
        $row = \Faoxima\Services\Panels\PanelFeatures::panel_feature_resolve_row($panel);
        if (!is_array($row) || empty($row['name_panel'])) {
            return true;
        }

        $panelName = (string) $row['name_panel'];
        $lockKey = self::panel_limit_lock_acquire($panelName);
        try {
            return self::panel_creation_limit_reached_unlocked($row);
        } finally {
            self::panel_limit_lock_release($lockKey);
        }
    }

    public static function panel_limit_menu_render($panel)
    {
        global $textbotlang;

        $stats = self::panel_limit_stats($panel);
        if (!is_array($stats)) {
            return null;
        }

        $lang = $textbotlang['Admin']['managepanel'] ?? [];
        $title = (string) ($lang['limitmenu_title'] ?? '🚨 مدیریت محدودیت اکانت');

        $lines = [$title, '', '🖥 پنل: ' . $stats['name_panel']];
        if ($stats['unlimited']) {
            $lines[] = '📊 وضعیت: ♾ بدون محدودیت';
            $lines[] = '👤 اکانت‌های ثبت‌شده از آخرین ریست: ' . $stats['used'];
            $lines[] = '🎯 سقف: نامحدود';
        } else {
            $lines[] = '📊 وضعیت: ' . ($stats['remaining'] > 0 ? 'فعال' : 'محدود');
            $lines[] = '👤 اکانت استفاده‌شده: ' . $stats['used'];
            $lines[] = '🎯 سقف اکانت: ' . $stats['limit'];
            $lines[] = '📦 ظرفیت باقی‌مانده: ' . $stats['remaining'];
            $lines[] = '';
            $lines[] = $stats['used'] . ' / ' . $stats['limit'];
        }
        $text = implode("\n", $lines);

        $keyboard = [
            'inline_keyboard' => [
                [['text' => (string) ($lang['limitmenu_btn_change'] ?? '✏️ تغییر محدودیت'), 'callback_data' => 'panellimit_change']],
                [['text' => (string) ($lang['limitmenu_btn_reset'] ?? '🔄 ریست شمارنده'), 'callback_data' => 'panellimit_reset']],
                [['text' => (string) ($lang['limitmenu_btn_unlimited'] ?? '♾ بدون محدودیت'), 'callback_data' => 'panellimit_unlimited']],
                [['text' => (string) ($lang['limitmenu_btn_back'] ?? '🔙 بازگشت'), 'callback_data' => 'panellimit_back']],
            ],
        ];

        return [
            'text' => $text,
            'keyboard' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
        ];
    }
}
