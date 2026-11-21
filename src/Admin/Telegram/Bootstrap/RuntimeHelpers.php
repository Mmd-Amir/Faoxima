<?php

namespace Faoxima\Admin\Telegram\Bootstrap;

final class RuntimeHelpers
{
    public static function rxBuildMiniAppInstructionText($domainhostsEscaped, $cronAutoStatus)
    {
        $cronState = is_array($cronAutoStatus) ? (string) ($cronAutoStatus['status'] ?? 'error') : 'error';

        if ($cronState === 'success') {
            $cronSectionText = "➖➖➖➖➖➖➖➖➖➖➖➖\n✅ تابع exec فعال است و Cron Job با موفقیت به‌صورت خودکار تنظیم شد.";
        } else {
            if ($cronState === 'disabled') {
                $cronReasonText = "⚠️ افزونه/تابع <b>exec</b> در PHP این هاست غیرفعال است، به همین دلیل کرون‌جاب به‌صورت خودکار تنظیم نشد.";
            } elseif ($cronState === 'no_binary') {
                $cronReasonText = "⚠️ باینری <b>crontab</b> روی این هاست پیدا نشد، به همین دلیل کرون‌جاب به‌صورت خودکار تنظیم نشد.";
            } else {
                $cronReasonText = "⚠️ تنظیم خودکار کرون‌جاب با خطا مواجه شد.";
            }

            $cronSectionText = <<<HTML
➖➖➖➖➖➖➖➖➖➖➖➖
{$cronReasonText}

لطفاً کرون‌جاب زیر را به‌صورت دستی از پنل هاست خود تنظیم کنید:

⚙️ تنظیم کرون‌جاب در هاست

فقط <b>یک کرون</b> کافی است — بقیه فرآیندها به‌صورت خودکار از همین کرون اجرا می‌شوند:

<b>⏱ هر ۱ دقیقه یک بار</b>
<code>curl -s https://{$domainhostsEscaped}/cron/cron.php &gt;/dev/null 2&gt;&amp;1</code>
HTML;
        }

        return <<<HTML
📌 آموزش فعالسازی مینی اپ در ربات BotFather

/mybots > Select Bot > Bot Setting >  Configure Mini App > Enable Mini App  > Edit Mini App URL

مراحل بالا را طی کنید سپس آدرس زیر را ارسال نمایید :

<code>https://{$domainhostsEscaped}/app/</code>

{$cronSectionText}
HTML;
    }

    public static function nm_getBroadcastStatus() {
        $infoFile      = 'cronbot/info';
        $usersFileTxt  = 'cronbot/users.txt';
        $usersFileJson = 'cronbot/users.json';
        if (!is_file($infoFile)) {
            return null;
        }
        $infoContent = @file_get_contents($infoFile);
        if ($infoContent === false || $infoContent === '') {
            return null;
        }
        $info = json_decode($infoContent, true);
        if (!is_array($info)) {
            return null;
        }

        $remaining = 0;
        if (is_file($usersFileTxt)) {
            $fh = @fopen($usersFileTxt, 'r');
            if ($fh) {
                while (!feof($fh)) {
                    $chunk = fread($fh, 65536);
                    if ($chunk === false) break;
                    $remaining += substr_count($chunk, "\n");
                }
                fclose($fh);
            }
        } elseif (is_file($usersFileJson)) {
            $raw = @file_get_contents($usersFileJson);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $remaining = count($decoded);
            }
        }
        $bcToken = substr(md5(
            ($info['id_admin'] ?? '') . '|' . ($info['id_message'] ?? '') . '|' .
            ($info['type'] ?? '') . '|' . ($info['message'] ?? '')
        ), 0, 12);
        $inflight = 0;
        foreach (glob($usersFileTxt . '.w*.' . $bcToken . '.inflight') ?: [] as $inf) {
            $fh = @fopen($inf, 'r');
            if ($fh) {
                while (!feof($fh)) {
                    $chunk = fread($fh, 65536);
                    if ($chunk === false) break;
                    $inflight += substr_count($chunk, "\n");
                }
                fclose($fh);
            }
        }
        $remaining += $inflight;
        $stats = isset($info['stats']) && is_array($info['stats']) ? $info['stats'] : [];
        $stats += [
            'total'          => 0,
            'success'        => 0,
            'blocked'        => 0,
            'deleted'        => 0,
            'failed'         => 0,
            'chat_not_found' => 0,
            'started_at'     => 0,
        ];
        $totalSent = (int) $stats['success']
                   + (int) $stats['blocked']
                   + (int) $stats['failed']
                   + (int) $stats['chat_not_found'];
        $total = (int) $stats['total'];
        if ($total <= 0) {

            $total = $totalSent + $remaining;
        }

        if ($remaining === 0 && $totalSent === 0 && $total === 0) {
            return null;
        }
        return [
            'type'           => isset($info['type']) ? (string) $info['type'] : '',
            'total'          => $total,
            'sent'           => $totalSent,
            'remaining'      => $remaining,
            'inflight'       => $inflight,
            'success'        => (int) $stats['success'],
            'blocked'        => (int) $stats['blocked'],
            'deleted'        => (int) $stats['deleted'],
            'failed'         => (int) $stats['failed'],
            'chat_not_found' => (int) $stats['chat_not_found'],
            'started_at'     => (int) $stats['started_at'],
            'finished'       => ($remaining === 0),
            'status'         => (string) ($info['status'] ?? 'queued'),
            'token'          => $bcToken,
            'info'           => $info,
        ];
    }

    public static function nm_buildBroadcastStatusText(array $status) {
        $typeMap = [
            'sendmessage'    => 'ارسال همگانی',
            'forwardmessage' => 'فوروارد همگانی',
            'xdaynotmessage' => 'پیام به کاربران غیرفعال',
            'unpinmessage'   => 'لغو پیام پین شده',
        ];
        $typeName  = isset($typeMap[$status['type']]) ? $typeMap[$status['type']] : $status['type'];
        $total     = (int) $status['total'];
        $sent      = (int) $status['sent'];
        $remaining = (int) $status['remaining'];
        $progress  = $total > 0 ? min(100, (int) floor(($sent / $total) * 100)) : 0;

        $cells  = 10;
        $filled = $total > 0 ? (int) floor(($sent / $total) * $cells) : 0;
        $bar    = str_repeat('█', $filled) . str_repeat('░', max(0, $cells - $filled));
        $isPaused = method_exists(\Faoxima\Admin\Broadcast\BroadcastQueue::class, 'rx_broadcast_is_paused') && \Faoxima\Admin\Broadcast\BroadcastQueue::rx_broadcast_is_paused($status['status'] ?? '');
        $t  = $isPaused
            ? "⏸️ <b>عملیات ارسال پیام موقتاً متوقف شده است</b>\n"
            : "⏳ <b>یک عملیات ارسال پیام در حال انجام است</b>\n";
        $t .= "—————————————————\n";
        $t .= "⚙️ نوع عملیات : <b>{$typeName}</b>\n\n";
        $t .= "👥 تعداد کل کاربران : <b>" . number_format($total)     . "</b>\n";
        $t .= "🚀 ارسال‌شده : <b>"        . number_format($sent)      . "</b>\n";
        $t .= "📊 باقی‌مانده در صف : <b>" . number_format($remaining) . "</b>\n";
        if (!empty($status['inflight'])) {
            $t .= "🔄 در حال پردازش : <b>" . number_format((int) $status['inflight']) . "</b>\n";
        }
        $t .= "\n";
        $t .= "📈 پیشرفت : <b>{$progress}%</b>\n<code>{$bar}</code>\n";
        $details = [];
        if ($status['success']        > 0) $details[] = '✅ موفق: '   . number_format($status['success']);
        if ($status['blocked']        > 0) $details[] = '🚫 بلاک: '    . number_format($status['blocked']);
        if ($status['chat_not_found'] > 0) $details[] = '📵 بدون چت: ' . number_format($status['chat_not_found']);
        if ($status['deleted']        > 0) $details[] = '🗑 حذف‌شده: '  . number_format($status['deleted']);
        if ($status['failed']         > 0) $details[] = '❌ خطا: '     . number_format($status['failed']);
        if (!empty($details)) {
            $t .= "\n📋 جزئیات : " . implode(' | ', $details) . "\n";
        }
        if ($status['started_at'] > 0) {
            $elapsed = max(0, time() - (int) $status['started_at']);
            $t .= "⏱ زمان سپری‌شده : <code>" . gmdate('H:i:s', $elapsed) . "</code>\n";
        }
        if ($isPaused && is_array($status['info'] ?? null)) {
            $t .= \Faoxima\Admin\Broadcast\BroadcastQueue::rx_broadcast_pause_text($status['info']);
        }
        $t .= "\n🕒 آخرین بروزرسانی : <code>" . date('H:i:s') . "</code>";
        $t .= "\n💡 برای دیدن آخرین آمار روی «🔄 بروزرسانی» بزنید.";
        return $t;
    }

    public static function nm_buildBroadcastStatusKeyboard($status = null) {
        $rows = [];
        if (is_array($status) && !empty($status['token']) && method_exists(\Faoxima\Admin\Broadcast\BroadcastQueue::class, 'rx_broadcast_is_paused') && \Faoxima\Admin\Broadcast\BroadcastQueue::rx_broadcast_is_paused($status['status'] ?? '')) {
            $rows[] = [\Faoxima\Admin\Broadcast\BroadcastQueue::rx_broadcast_resume_button((string) $status['token'])];
        }
        $rows[] = [['text' => "🔄 بروزرسانی",       'callback_data' => 'broadcast_status_refresh']];
        $rows[] = [['text' => "❌ لغو عملیات",       'callback_data' => 'cancel_sendmessage']];
        $rows[] = [['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser']];
        return json_encode(['inline_keyboard' => $rows]);
    }
}
