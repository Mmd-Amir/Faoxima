<?php

namespace Faoxima\Services\Panels;

use Throwable;

final class PanelMessages
{
    public static function rx_panel_http_status_fa($code)
    {
        $code = (int) $code;
        $map = [
            0   => 'اتصال به پنل برقرار نشد؛ پنل خاموش است، آدرس اشتباه است یا فایروال/SSL جلوی اتصال را گرفته',
            400 => 'درخواست نامعتبر؛ پنل داده‌های ارسالی ربات را نپذیرفت',
            401 => 'احراز هویت ناموفق؛ API Key یا نام کاربری/رمز پنل در ربات اشتباه یا منقضی است',
            403 => 'دسترسی غیرمجاز؛ ادمین پنل غیرفعال است، مجوز ساخت کاربر ندارد یا به سقف حجم/تعداد کاربر رسیده',
            404 => 'پیدا نشد؛ آدرس پنل، مسیر API یا گروه/اینباند انتخاب‌شده روی پنل وجود ندارد',
            405 => 'متد مجاز نیست؛ احتمالاً نوع یا نسخهٔ پنل در ربات اشتباه انتخاب شده',
            408 => 'پنل در زمان مقرر پاسخ نداد (Timeout)',
            409 => 'نام کاربری تکراری است؛ این یوزرنیم از قبل روی پنل وجود دارد',
            413 => 'حجم درخواست بیش از حد مجاز پنل است',
            422 => 'دادهٔ نامعتبر؛ فرمت نام کاربری، گروه/اینباند، حجم یا تاریخ انقضا مورد قبول پنل نیست',
            429 => 'تعداد درخواست‌ها زیاد است؛ پنل موقتاً درخواست‌ها را محدود کرده',
            500 => 'خطای داخلی پنل؛ لاگ پنل را بررسی کنید',
            502 => 'Bad Gateway؛ وب‌سرور/پروکسی جلوی پنل به خود پنل دسترسی ندارد',
            503 => 'سرویس پنل در دسترس نیست؛ پنل در حال ری‌استارت یا از کار افتاده است',
            504 => 'Gateway Timeout؛ پنل دیر پاسخ داد',
        ];
        if (isset($map[$code])) {
            return $map[$code];
        }
        if ($code >= 520 && $code <= 530) {
            return 'خطای Cloudflare؛ سرور پنل پشت Cloudflare پاسخ نداد یا در دسترس نیست';
        }
        if ($code >= 500) {
            return 'خطای سمت سرور پنل';
        }
        if ($code >= 400) {
            return 'پنل درخواست را رد کرد';
        }
        if ($code >= 300) {
            return 'ریدایرکت؛ آدرس پنل اشتباه است (http/https یا مسیر را بررسی کنید)';
        }
        if ($code >= 200) {
            return 'پنل عملیات را با موفقیت انجام داد اما پاسخ آن با انتظار ربات جور نبود؛ نوع پنل انتخاب‌شده در ربات را بررسی کنید';
        }
        return '';
    }

    public static function rx_panel_error_hint_fa($text)
    {
        $text = (string) $text;
        $patterns = [
            '/already\s+exist|duplicate|\bexists\b|تکراری/iu'              => 'نام کاربری تکراری است؛ این یوزرنیم از قبل روی پنل وجود دارد',
            '/timed?\s*out|timeout/i'                                     => 'پنل در زمان مقرر پاسخ نداد (Timeout)',
            '/could not resolve host|name or service not known/i'         => 'دامنهٔ پنل پیدا نشد (خطای DNS)',
            '/connection refused|failed to connect|couldn\'t connect/i'   => 'اتصال به پنل رد شد؛ پنل خاموش است یا پورت بسته است',
            '/ssl|certificate/i'                                          => 'خطای SSL؛ گواهی پنل نامعتبر یا منقضی است',
            '/unauthori[sz]ed|invalid username or password|login failed|not authenticated/i' => 'احراز هویت ناموفق؛ اطلاعات ورود یا API Key پنل را بررسی کنید',
            '/^panel not found$/i'                                        => 'پنل در دیتابیس ربات پیدا نشد',
            '/^user not found$/i'                                         => 'کاربر روی پنل پیدا نشد',
            '/manualsale stock not found/i'                               => 'موجودی فروش دستی این محصول تمام شده است',
            '/panel configuration not found/i'                            => 'تنظیمات پنل در ربات پیدا نشد',
        ];
        foreach ($patterns as $pattern => $hint) {
            if (preg_match($pattern, $text)) {
                return $hint;
            }
        }
        return '';
    }

    public static function rx_panel_error_detail_text($detail)
    {
        if ($detail === null || $detail === '' || $detail === []) {
            return '';
        }
        if (is_array($detail)) {
            $parts = [];
            foreach ($detail as $key => $item) {
                if (is_array($item) && isset($item['msg'])) {
                    $loc = isset($item['loc']) && is_array($item['loc']) ? implode('.', array_map('strval', $item['loc'])) : '';
                    $parts[] = ($loc !== '' ? $loc . ': ' : '') . (string) $item['msg'];
                } elseif (is_scalar($item)) {
                    $parts[] = (is_string($key) ? $key . ': ' : '') . (string) $item;
                } else {
                    $parts[] = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            $text = implode(' | ', $parts);
        } else {
            $text = (string) $detail;
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 300) {
            $text = mb_substr($text, 0, 300, 'UTF-8') . '…';
        }
        return $text;
    }

    public static function rx_panel_error_text($msg, $detail = null, $html = true)
    {
        if (is_array($msg) || is_object($msg)) {
            $raw = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $raw = trim((string) $msg);
        }
        $hint = '';
        if ($raw === '' || $raw === 'null') {
            $raw = 'نامشخص';
        } elseif (preg_match('/^\d{1,3}$/', $raw)) {
            $hint = self::rx_panel_http_status_fa((int) $raw);
        } else {
            $hint = self::rx_panel_error_hint_fa($raw);
        }
        $detailText = self::rx_panel_error_detail_text($detail);
        if ($hint === '' && $detailText !== '') {
            $hint = self::rx_panel_error_hint_fa($detailText);
        }
        $text = $hint !== '' ? $raw . ' — ' . $hint : $raw;
        if ($detailText !== '' && $detailText !== $raw) {
            $text .= "\nپاسخ پنل: " . $detailText;
        }
        return $html ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $text;
    }

    public static function rx_panel_username_variant($base)
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        $suffix = '';
        for ($i = 0; $i < 4; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $base = trim((string) $base);
        if (strlen($base) > 27) {
            $base = substr($base, 0, 27);
        }
        $base = rtrim($base, '_-.');
        if ($base === '') {
            $base = 'u';
        }
        return $base . '_' . $suffix;
    }

    public static function rx_adopt_created_username($output, $requestedUsername, $idInvoice = null)
    {
        $requestedUsername = (string) $requestedUsername;
        if (!is_array($output) || empty($output['username'])) {
            return $requestedUsername;
        }
        $created = (string) $output['username'];
        if ($created === $requestedUsername || empty($output['renamed_from'])) {
            return $created;
        }
        if ($idInvoice !== null && $idInvoice !== '' && \Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                \Faoxima\Core\Database\Db::update('invoice', 'username', $created, 'id_invoice', (string) $idInvoice);
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                    \Faoxima\Core\Database\SelectCache::clearSelectCache('invoice');
                }
            } catch (Throwable $e) {
                error_log('[rx_adopt_created_username] invoice update failed: ' . $e->getMessage());
            }
        }
        return $created;
    }

    public static function rx_notify_username_renamed($chatId, $output, $panel = null)
    {
        if (!is_array($output) || empty($output['renamed_from']) || empty($output['username']) || !function_exists('sendmessage')) {
            return;
        }
        $old = (string) $output['renamed_from'];
        $new = (string) $output['username'];
        if (is_array($panel) && method_exists(\Faoxima\Services\Panels\Drivers\Guard::class, 'guardDisplayUsername')) {
            $old = \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($old, $panel);
            $new = \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($new, $panel);
        }
        $template = "⚠️ نام کاربری «<code>{old}</code>» از قبل روی پنل وجود داشت؛ سرویس شما با نام کاربری «<code>{new}</code>» ساخته شد.";
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_textbot_get')) {
            $template = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_username_renamed_notice', $template);
        }
        $text = strtr($template, [
            '{old}' => htmlspecialchars($old, ENT_QUOTES, 'UTF-8'),
            '{new}' => htmlspecialchars($new, ENT_QUOTES, 'UTF-8'),
        ]);
        try {
            sendmessage($chatId, $text, null, 'HTML');
        } catch (Throwable $e) {
            error_log('[rx_notify_username_renamed] ' . $e->getMessage());
        }
    }
}
