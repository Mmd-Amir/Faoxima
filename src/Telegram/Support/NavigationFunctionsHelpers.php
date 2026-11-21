<?php

namespace Faoxima\Telegram\Support;

final class NavigationFunctionsHelpers
{
    public static function rxConfigDeliveryNotice(string $status, string $failure = '❌ ارسال فایل کانفیگ ناموفق بود.'): string
{
    return [
        'partial' => '⚠️ بخشی از فایل‌ها یا توضیحات ارسال نشد. برای پیگیری با پشتیبانی تماس بگیرید.',
        'unknown' => '⏳ ارسال همه فایل‌ها یا توضیحات تأیید نشد. برای پیگیری با پشتیبانی تماس بگیرید.',
    ][$status] ?? $failure;
}

    public static function rxBotCanPickDuration($fromId, $user, $duration): bool
{
    if (!in_array($user['step'] ?? '', ['getvolumecustomusername', 'getvolumecustomuser', 'getvolumecustomusernameom', 'getvolumecustomuserom', 'getvolumecustomuserforextend'], true)) {
        return false;
    }
    $state = json_decode((string) $user['Processing_value'], true);
    if (($user['step'] ?? '') === 'getvolumecustomuserforextend') {
        $invoice = \Faoxima\Core\Database\Db::select('invoice', '*', 'id_invoice', $state['id_invoice'] ?? '', 'select');
        if ((string) ($invoice['id_user'] ?? '') !== (string) $fromId) {
            return false;
        }
        $panelName = $invoice['Service_location'];
    } else {
        $panelName = is_array($state) ? ($state['name_panel'] ?? '') : $user['Processing_value'];
    }
    $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $panelName, 'select');
    return is_array($panel) && \Faoxima\Orders\Pricing\CustomDuration::fx_duration_config($panel, $user['agent'])['enabled']
        && isset(\Faoxima\Orders\Pricing\CustomDuration::fx_duration_tariffs($panel, $user['agent'])[(int) $duration]);
}

    public static function rxBotServiceProductButtons(array $products, $customProductLabel): array
{
    $buttons = [];
    foreach ($products as $product) {
        $value = $product['name_product'];
        $label = trim((string) $value);
        $key = md5($value === null ? 'NULL' : 'P:' . $value);
        $buttons[] = [[
            'text' => $label !== '' ? $label : $customProductLabel,
            'callback_data' => 'myservicescat_' . $key,
        ]];
    }
    return $buttons;
}

    public static function rxBotResetPendingTransfer($pdo, $fromId, array &$user): void
{
    $stmt = $pdo->prepare("UPDATE user SET Processing_value = '0', Processing_value_one = '', Processing_value_tow = '0' WHERE id = ?");
    $stmt->execute([$fromId]);
    if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
        \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $fromId);
    }
    $user['step'] = 'home';
    $user['Processing_value'] = '0';
    $user['Processing_value_one'] = '';
    $user['Processing_value_tow'] = '0';
    \Faoxima\Telegram\Navigation\Steps::step('home', $fromId);
}
}
