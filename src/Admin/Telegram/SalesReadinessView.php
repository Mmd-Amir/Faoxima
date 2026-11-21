<?php

namespace Faoxima\Admin\Telegram;

final class SalesReadinessView
{
    public static function rx_sr_label()
    {
        return '🚦 آمادگی فروش';
    }

    public static function rx_sr_fa($value)
    {
        return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    public static function rx_sr_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function rx_sr_icon($status)
    {
        if ($status === 'ok') {
            return '✅';
        }
        if ($status === 'fail') {
            return '❌';
        }
        return '⏳';
    }

    public static function rx_sr_style($status)
    {
        if ($status === 'ok') {
            return 'success';
        }
        if ($status === 'fail') {
            return 'danger';
        }
        return 'primary';
    }

    public static function rx_sr_button($text, $callback, $status = null)
    {
        $btn = ['text' => $text, 'callback_data' => $callback];
        if ($status === null) {
            $btn['_rxap'] = 1;
        } else {
            $btn['style'] = self::rx_sr_style($status);
            $btn['_rxkeep'] = 1;
        }
        return $btn;
    }

    public static function rx_sr_render_list(array $result)
    {
        $verdictLine = [
            'ready' => '🟢 <b>ربات آماده فروش است.</b>',
            'not_ready' => '🔴 <b>ربات هنوز آماده فروش نیست.</b>',
            'unknown' => '🔵 <b>نتیجه نهایی هنوز قطعی نیست.</b>',
        ][$result['verdict']] ?? '';
        $lines = [];
        $lines[] = '🚦 <b>آمادگی فروش</b>';
        $lines[] = '';
        $lines[] = $verdictLine;
        $lines[] = '📊 <b>' . self::rx_sr_fa($result['completed']) . ' از ' . self::rx_sr_fa(6) . ' مورد تکمیل</b>';
        $lines[] = '🛤 مسیر فروش کامل: <b>' . self::rx_sr_fa($result['ready_paths']) . ' از ' . self::rx_sr_fa($result['active']) . '</b> پنل فعال';
        if (!empty($result['pending'])) {
            $lines[] = '⏳ بررسی‌نشده یا نامشخص: ' . self::rx_sr_fa($result['pending']) . ' مورد';
        }
        if (!empty($result['blocking'])) {
            $lines[] = '';
            $lines[] = '⛔️ <b>موارد مسدودکننده:</b>';
            foreach ($result['blocking'] as $item) {
                $lines[] = '• ' . self::rx_sr_h($item);
            }
        }
        $problemPanels = [];
        foreach ($result['paths'] as $path) {
            if (!$path['ready']) {
                foreach ([1, 2, 3, 4] as $i) {
                    if (($path['stages'][$i]['s'] ?? '') !== 'ok') {
                        $problemPanels[] = '• ' . self::rx_sr_h($path['panel']) . ' — ' . self::rx_sr_h(\Faoxima\Services\Panels\SalesReadiness::rx_sr_defs()[$i]['label']) . ': ' . self::rx_sr_h($path['stages'][$i]['m']);
                        break;
                    }
                }
            }
        }
        if (!empty($problemPanels) && $result['verdict'] === 'ready') {
            $lines[] = '';
            $lines[] = '⚠️ <b>پنل‌هایی که مسیر کامل ندارند (مانع فروش نیستند):</b>';
        } elseif (!empty($problemPanels)) {
            $lines[] = '';
            $lines[] = '🔎 <b>وضعیت پنل‌ها:</b>';
        }
        foreach (array_slice($problemPanels, 0, 6) as $line) {
            $lines[] = $line;
        }
        if (count($problemPanels) > 6) {
            $lines[] = '… و ' . self::rx_sr_fa(count($problemPanels) - 6) . ' پنل دیگر';
        }
        $lines[] = '';
        $lines[] = '👇 برای دیدن جزئیات هر مورد روی آن بزنید.';
        $lines[] = '🕒 آخرین بررسی: ' . self::rx_sr_fa(date('H:i:s', (int) $result['time'])) . (empty($result['live']) ? ' (بدون بررسی زنده اتصال)' : '');
        $rows = [];
        foreach ($result['checks'] as $i => $check) {
            $rows[] = [self::rx_sr_button(self::rx_sr_icon($check['s']) . ' ' . $check['label'], 'srd_i' . $i, $check['s'])];
        }
        $rows[] = [self::rx_sr_button('🔄 بررسی مجدد', 'srd_refresh'), self::rx_sr_button('↩️ بازگشت', 'adm_hub_main')];
        return [implode("\n", $lines), json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
    }

    public static function rx_sr_render_detail(array $result, $index)
    {
        $defs = \Faoxima\Services\Panels\SalesReadiness::rx_sr_defs();
        $check = $result['checks'][$index] ?? null;
        if ($check === null) {
            return self::rx_sr_render_list($result);
        }
        $statusText = ['ok' => '✅ تکمیل شده', 'fail' => '❌ ناقص', 'pending' => '⏳ بررسی نشده / نامشخص'][$check['s']] ?? '';
        $lines = [];
        $lines[] = self::rx_sr_icon($check['s']) . ' <b>' . self::rx_sr_h($check['label']) . '</b>';
        $lines[] = 'وضعیت: ' . $statusText;
        $lines[] = self::rx_sr_h($check['m']);
        $lines[] = '';
        if ($index >= 5) {
            foreach ($check['lines'] as $line) {
                $lines[] = self::rx_sr_h($line);
            }
        } else {
            if (empty($result['paths'])) {
                $lines[] = 'پنل فعالی برای بررسی وجود ندارد.';
            }
            foreach ($result['paths'] as $path) {
                $blockedAt = null;
                for ($i = 1; $i < $index; $i++) {
                    if (($path['stages'][$i]['s'] ?? '') !== 'ok') {
                        $blockedAt = $i;
                        break;
                    }
                }
                $head = '🔹 <b>' . self::rx_sr_h($path['panel']) . '</b> (' . self::rx_sr_h($path['type']) . ')';
                if ($blockedAt !== null) {
                    $lines[] = $head;
                    $lines[] = '   ⏭ به‌دلیل مشکل در «' . self::rx_sr_h($defs[$blockedAt]['label']) . '» این مرحله برای این پنل بررسی نشد.';
                } else {
                    $stage = $path['stages'][$index];
                    $lines[] = $head;
                    $lines[] = '   ' . self::rx_sr_icon($stage['s']) . ' ' . self::rx_sr_h($stage['m']);
                }
            }
            if ($index === 1 && !empty($result['inactive_names'])) {
                $lines[] = '';
                $lines[] = '🚫 پنل‌های غیرفعال (برای مشتری نمایش داده نمی‌شوند): ' . self::rx_sr_h(implode('، ', array_slice($result['inactive_names'], 0, 8)));
            }
        }
        $lines[] = '';
        $lines[] = '🕒 آخرین بررسی: ' . self::rx_sr_fa(date('H:i:s', (int) $result['time']));
        $text = implode("\n", $lines);
        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 3800) {
            $text = mb_substr($text, 0, 3800, 'UTF-8') . '…';
        }
        $rows = [];
        if ($check['s'] !== 'ok') {
            $routes = [];
            foreach ($defs[$index]['route'] as $route) {
                $routes[] = self::rx_sr_button($route[0], $route[1]);
            }
            foreach (array_chunk($routes, 2) as $chunk) {
                $rows[] = $chunk;
            }
        }
        $rows[] = [self::rx_sr_button('🔄 بررسی مجدد', 'srd_r' . $index), self::rx_sr_button('↩️ بازگشت به چک‌لیست', 'srd_list')];
        return [$text, json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
    }

    public static function rx_sr_answer($text = '', $alert = false)
    {
        $callbackId = $GLOBALS['callback_query_id'] ?? '';
        if ($callbackId === '' || $callbackId === null || !function_exists('telegram')) {
            return;
        }
        $payload = ['callback_query_id' => $callbackId, 'cache_time' => 0];
        if ($text !== '') {
            $payload['text'] = $text;
            $payload['show_alert'] = $alert;
        }
        telegram('answerCallbackQuery', $payload);
    }

    public static function rx_sr_show($chatId, $messageId, array $result, $detailIndex = null)
    {
        $view = $detailIndex === null ? self::rx_sr_render_list($result) : self::rx_sr_render_detail($result, $detailIndex);
        if ((int) $messageId > 0) {
            Editmessagetext($chatId, $messageId, $view[0], $view[1], 'HTML');
            return;
        }
        sendmessage($chatId, $view[0], $view[1], 'HTML');
    }

    public static function rx_sr_handle_admin_update()
    {
        $datain = isset($GLOBALS['datain']) ? (string) $GLOBALS['datain'] : '';
        $text = isset($GLOBALS['text']) && is_string($GLOBALS['text']) ? trim($GLOBALS['text']) : '';
        $fromId = $GLOBALS['from_id'] ?? null;
        $messageId = (int) ($GLOBALS['message_id'] ?? 0);
        $isCallback = !empty($GLOBALS['callback_query_id']);

        $isEntry = $datain === 'admin_readiness' || ($datain === '' && $text === self::rx_sr_label());
        $isAction = $datain !== '' && strpos($datain, 'srd_') === 0;
        if (!$isEntry && !$isAction) {
            return false;
        }

        $adminIds = array_map('strval', (array) ($GLOBALS['admin_ids'] ?? []));
        $rule = (string) (($GLOBALS['adminrulecheck']['rule'] ?? ''));
        if ($fromId === null || !in_array((string) $fromId, $adminIds, true) || $rule !== 'administrator') {
            if ($isCallback) {
                self::rx_sr_answer('⛔️ شما به این بخش دسترسی ندارید.', true);
            } elseif ($fromId !== null && function_exists('sendmessage')) {
                sendmessage($fromId, '⛔️ شما به این بخش دسترسی ندارید.', null, 'HTML');
            }
            return true;
        }

        if ($isEntry) {
            if (method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'step')) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $fromId);
            }
            if (isset($GLOBALS['user']) && is_array($GLOBALS['user'])) {
                $GLOBALS['user']['step'] = 'home';
            }
            if ($isCallback) {
                self::rx_sr_answer('در حال بررسی…');
                $targetMessage = $messageId;
            } else {
                $placeholder = sendmessage($fromId, '⏳ در حال بررسی آمادگی فروش؛ ممکن است چند ثانیه طول بکشد…', null, 'HTML');
                $targetMessage = is_array($placeholder) ? (int) ($placeholder['result']['message_id'] ?? 0) : 0;
            }
            $result = \Faoxima\Services\Panels\SalesReadiness::rx_sr_evaluate(true);
            \Faoxima\Services\Panels\SalesReadiness::rx_sr_cache_write($result);
            self::rx_sr_show($fromId, $targetMessage, $result);
            return true;
        }

        if ($datain === 'srd_refresh' || preg_match('/^srd_r([1-6])$/', $datain, $refreshMatch)) {
            self::rx_sr_answer('در حال بررسی مجدد…');
            $result = \Faoxima\Services\Panels\SalesReadiness::rx_sr_evaluate(true);
            \Faoxima\Services\Panels\SalesReadiness::rx_sr_cache_write($result);
            self::rx_sr_show($fromId, $messageId, $result, isset($refreshMatch[1]) ? (int) $refreshMatch[1] : null);
            return true;
        }

        $result = \Faoxima\Services\Panels\SalesReadiness::rx_sr_cache_read();
        if ($datain === 'srd_list') {
            self::rx_sr_answer();
            if ($result === null) {
                $result = \Faoxima\Services\Panels\SalesReadiness::rx_sr_evaluate(true);
                \Faoxima\Services\Panels\SalesReadiness::rx_sr_cache_write($result);
            }
            self::rx_sr_show($fromId, $messageId, $result);
            return true;
        }
        if (preg_match('/^srd_i([1-6])$/', $datain, $m)) {
            self::rx_sr_answer();
            if ($result === null) {
                $result = \Faoxima\Services\Panels\SalesReadiness::rx_sr_evaluate(true);
                \Faoxima\Services\Panels\SalesReadiness::rx_sr_cache_write($result);
            }
            self::rx_sr_show($fromId, $messageId, $result, (int) $m[1]);
            return true;
        }
        self::rx_sr_answer();
        return true;
    }
}
