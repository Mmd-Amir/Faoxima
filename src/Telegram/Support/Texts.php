<?php

namespace Faoxima\Telegram\Support;

use PDO;

final class Texts
{
    /**
 * Resolves the "allowed users" label to show a customer, giving the symbolic
 * (admin-set, panel-agnostic) "Limiet" priority over the real 3x-ui/fail2ban
 * ip_limit. Works for any panel type. Returns null when neither applies.
 */
    public static function faoxima_symbolic_limit_label(array $row): ?string
    {
        if (($row['symbolic_limit_enabled'] ?? '0') == '1') {
            $count = (int)($row['symbolic_limit_users'] ?? 0);
            return $count > 0 ? "{$count} کاربر" : null;
        }
        return null;
    }

    public static function faoxima_cap_ip_summary(array $summary, int $ipLimit): array
    {
        if ($ipLimit <= 0) {
            return $summary;
        }
        if (isset($summary['raw']) && is_array($summary['raw'])) {
            $summary['raw'] = array_slice(array_values($summary['raw']), 0, $ipLimit);
        }
        if (isset($summary['masked']) && is_array($summary['masked'])) {
            $summary['masked'] = array_slice(array_values($summary['masked']), 0, $ipLimit);
        }
        if (isset($summary['count'])) {
            $summary['count'] = min((int)$summary['count'], $ipLimit);
        }
        return $summary;
    }

    public static function faoxima_render_text($template, array $vars)
    {
        if (!is_string($template) || $template === '') {
            return '';
        }
        $replacements = [];
        foreach ($vars as $rx_render_key => $rx_render_val) {
            $rx_render_token = (strpos((string) $rx_render_key, '{') === 0) ? $rx_render_key : '{' . $rx_render_key . '}';
            $replacements[$rx_render_token] = (string) $rx_render_val;
        }
        return strtr($template, $replacements);
    }

    public static function faoxima_textbot_get($id_text, $fallback = '')
    {
        if (!\Faoxima\Core\Database\Db::runtimeReady()) {
            return $fallback;
        }
        try {
            $rx_tb_row = \Faoxima\Core\Database\Db::select('textbot', 'text', 'id_text', $id_text);
        } catch (\Throwable $rx_tb_err) {
            return $fallback;
        }
        if (is_string($rx_tb_row)) {
            return $rx_tb_row !== '' ? $rx_tb_row : $fallback;
        }
        if (is_array($rx_tb_row) && isset($rx_tb_row['text']) && is_string($rx_tb_row['text']) && $rx_tb_row['text'] !== '') {
            return $rx_tb_row['text'];
        }
        return $fallback;
    }

    public static function faoxima_mask_user_id($userId): string
    {
        $userId = (string)$userId;
        $len = strlen($userId);
        if ($len <= 4) {
            return $userId === '' ? '' : str_repeat('*', $len);
        }
        $visible = (int)ceil($len / 2);
        return "\xE2\x80\x8E" . substr($userId, 0, $visible) . str_repeat('*', $len - $visible) . "\xE2\x80\x8E";
    }

    public static function faoxima_public_purchase_log_event(string $eventKey, array $vars, ?array $setting = null): void
    {
        if ($setting === null) {
            if (!\Faoxima\Core\Database\Db::runtimeReady()) return;
            try {
                $setting = \Faoxima\Core\Database\Db::select('setting', '*');
            } catch (\Throwable $rx_pl_err) {
                return;
            }
        }
        if (!is_array($setting)) return;
        if ((string)($setting['PublicLog_Status'] ?? '0') !== '1') return;

        $eventCols = [
            'new_sub'       => 'PublicLog_NewSub',
            'renewal'       => 'PublicLog_Renewal',
            'volume_topup'  => 'PublicLog_VolumeTopup',
            'time_extra'    => 'PublicLog_TimeExtra',
            'wallet_deposit'=> 'PublicLog_WalletDeposit',
        ];
        $configEvents = ['new_sub' => true, 'renewal' => true];
        $eventTpl = [
            'new_sub'       => ['✅ گزارش خرید #سفارش_جدید', 'dyn_public_broadcast_new_sub_tpl'],
            'renewal'       => ['✅ گزارش تمدید #تمدید_سرویس', 'dyn_public_broadcast_renewal_tpl'],
            'volume_topup'  => ['✅ گزارش خرید #خرید_حجم', 'dyn_public_broadcast_volume_topup_tpl'],
            'time_extra'    => ['✅ گزارش خرید #خرید_زمان', 'dyn_public_broadcast_time_extra_tpl'],
            'wallet_deposit'=> ['✅ گزارش تراکنش #شارژ_کیف_پول', 'dyn_public_broadcast_wallet_deposit_tpl'],
        ];
        if (!isset($eventCols[$eventKey])) return;
        if ((string)($setting[$eventCols[$eventKey]] ?? '0') !== '1') return;

        $channel = trim((string)($setting['PublicLog_Channel'] ?? ''));
        if ($channel === '') return;

        [$titleFallback, $tplKey] = $eventTpl[$eventKey];
        $footer = "📅 : {date} - ⏰ : {time}";
        $fallbackTpl = isset($configEvents[$eventKey])
            ? "{$titleFallback}\n\n<blockquote>👤 آیدی کاربر : {user_id}</blockquote>\n<blockquote>🖥️ پنل : {panel_name}</blockquote>\n<blockquote>🏷️ دسته‌بندی : {category}</blockquote>\n<blockquote>📦 سرویس : {amount}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n{$footer}"
            : "{$titleFallback}\n\n<blockquote>👤 آیدی کاربر : {user_id}</blockquote>\n<blockquote>📦 مقدار : {amount}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n{$footer}";
        $vars['user_id'] = self::faoxima_mask_user_id($vars['user_id'] ?? '');
        $now = time();
        $vars += [
            'date'        => function_exists('jdate') ? jdate('Y/m/d', $now) : date('Y/m/d', $now),
            'time'        => function_exists('jdate') ? jdate('H:i:s', $now) : date('H:i:s', $now),
            'when'        => function_exists('jdate') ? jdate('Y/m/d H:i:s', $now) : date('Y/m/d H:i:s', $now),
            'panel_name'  => '',
            'category'    => '',
        ];
        $text = self::faoxima_render_text(self::faoxima_textbot_get($tplKey, $fallbackTpl), $vars);
        if ($text === '') return;

        global $usernamebot;
        $botUsername = '';
        if (isset($usernamebot) && is_string($usernamebot)) {
            $botUsername = ltrim(trim($usernamebot), '@');
        }
        if ($botUsername === '' && function_exists('telegram')) {
            try {
                $me = @telegram('getMe', []);
                if (is_array($me) && isset($me['result']['username'])) {
                    $botUsername = (string)$me['result']['username'];
                }
            } catch (\Throwable $rx_pl_bot_err) {
            }
        }

        $btnLabel = self::faoxima_textbot_get('dyn_public_log_btn_label', '🛒 خرید خدمات از ربات');
        $reply = null;
        if ($botUsername !== '') {
            $reply = json_encode([
                'inline_keyboard' => [[
                    ['text' => $btnLabel, 'url' => 'https://t.me/' . $botUsername, 'style' => 'success'],
                ]],
            ]);
        }

        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) return;
        try {
            $stmt = $pdo->prepare('INSERT INTO PublicLog_Queue (chat_id, text, reply_markup) VALUES (:c, :t, :r)');
            $stmt->execute([
                ':c' => $channel,
                ':t' => $text,
                ':r' => $reply,
            ]);
        } catch (\Throwable $rx_pl_enqueue_err) {
        }
    }
}
