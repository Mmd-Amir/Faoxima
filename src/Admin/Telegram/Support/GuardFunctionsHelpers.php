<?php

namespace Faoxima\Admin\Telegram\Support;

use PDO;

final class GuardFunctionsHelpers
{
    public static function ensureGuardPanelColumnsReady(PDO $pdo)
{
    $requiredColumns = [
        'api_key' => "VARCHAR(500)",
        'guard_service_ids' => "TEXT",
        'guard_note' => "TEXT",
        'guard_auto_delete_days' => "INT(11)",
        'guard_auto_renewals' => "TEXT",
        'guard_version' => "VARCHAR(10)",
    ];

    if (function_exists('ensureMarzbanGuardFieldsMigrated')) {
        ensureMarzbanGuardFieldsMigrated();
    } else {
        foreach ($requiredColumns as $column => $datatype) {
            \Faoxima\Core\Database\Db::addFieldToTable("marzban_panel", $column, null, $datatype);
        }
    }

    $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marzban_panel' AND COLUMN_NAME IN ($placeholders)"
    );
    $stmt->execute(array_keys($requiredColumns));
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $missingColumns = array_diff(array_keys($requiredColumns), $existingColumns);

    return [
        'status' => empty($missingColumns),
        'missing' => array_values($missingColumns),
    ];
}

    public static function guardExtractUsageRateValue(array $service)
{
    foreach (['usage_rate', 'usageRate'] as $rateKey) {
        if (isset($service[$rateKey]) && is_numeric($service[$rateKey])) {
            return floatval($service[$rateKey]);
        }
    }
    return null;
}

    public static function guardFormatUsageRateLabel($rate)
{
    $value = is_numeric($rate) ? floatval($rate) : 1.0;
    $precision = (floor($value) == $value) ? 1 : 2;
    $formatted = number_format($value, $precision, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    if (strpos($formatted, '.') === false) {
        $formatted .= '.0';
    }
    return $formatted;
}

    public static function guardBuildServiceSummaryLabel(array $service)
{
    $label = \Faoxima\Services\Panels\Drivers\Guard::guardServiceLabel($service);
    $rateLabel = self::guardFormatUsageRateLabel(self::guardExtractUsageRateValue($service));
    return "{$label} [{$rateLabel}x]";
}

    public static function guardResolveUserPanelName(array $user)
{
    if (!isset($user['Processing_value'])) {
        return null;
    }
    $raw = $user['Processing_value'];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        foreach (['panel', 'panel_name', 'namepanel'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                return trim($decoded[$key]);
            }
        }
        if (!empty($decoded['guard_svc']['panel'])) {
            return trim((string) $decoded['guard_svc']['panel']);
        }
    }

    $trimmed = trim((string) $raw);
    return $trimmed === '' || $trimmed === '0' ? null : $trimmed;
}

    public static function guardSvcRenderText(array $services, array $selectedIds)
{
    $lines = [
        "⚙️ سرویس‌های قابل فروش روی این پنل",
        "روی هر سرویس بزنید تا انتخاب/لغو شود. حداقل یک سرویس باید انتخاب باشد.",
        "",
    ];
    foreach ($services as $service) {
        if (!isset($service['id'])) {
            continue;
        }
        $id = intval($service['id']);
        $mark = in_array($id, $selectedIds, true) ? '✅' : '▫️';
        $lines[] = "{$mark} " . self::guardBuildServiceSummaryLabel($service);
    }
    $lines[] = "";
    $lines[] = count($selectedIds) . ' از ' . count($services) . ' سرویس انتخاب شده';
    return implode("\n", $lines);
}

    public static function guardSvcRenderKeyboard(array $services, array $selectedIds)
{
    $rows = [];
    foreach ($services as $service) {
        if (!isset($service['id'])) {
            continue;
        }
        $id = intval($service['id']);
        $isSelected = in_array($id, $selectedIds, true);
        $rows[] = [[
            'text' => ($isSelected ? '✅ ' : '▫️ ') . self::guardBuildServiceSummaryLabel($service),
            'callback_data' => "guardsvc:t:{$id}",
        ]];
    }
    $rows[] = [
        ['text' => '✅ انتخاب همه', 'callback_data' => 'guardsvc:a'],
        ['text' => '▫️ لغو همه', 'callback_data' => 'guardsvc:n'],
    ];
    $rows[] = [
        ['text' => '💾 ذخیره', 'callback_data' => 'guardsvc:s'],
        ['text' => '🏠 بازگشت به منوی مدیریت', 'callback_data' => 'guardsvc:x'],
    ];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

    public static function guardFormatConnectionResult(array $result)
{
    global $textbotlang;
    $statusCode = $result['response']['status'] ?? null;
    if (!empty($result['status'])) {
        $adminName = \Faoxima\Services\Panels\Drivers\Guard::guardExtractAdminName($result['data'] ?? []);
        $adminLabel = $adminName !== '' ? " ({$adminName})" : '';
        return trim(($textbotlang['Admin']['managepanel']['guard']['connection_ok'] ?? "✅ اتصال برقرار است") . $adminLabel);
    }
    if (in_array($statusCode, [401, 403], true)) {
        return "❌ عدم دسترسی (401/403) → API Key اشتباه";
    }
    $errorMsg = $result['msg'] ?? '';
    $prefix = $textbotlang['Admin']['managepanel']['guard']['connection_error'] ?? "❌ خطای اتصال";
    if ($errorMsg !== '') {
        return "{$prefix} → {$errorMsg}";
    }
    return $prefix;
}
}
