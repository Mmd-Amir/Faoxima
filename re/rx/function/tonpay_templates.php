<?php

if (!function_exists('tonpayTemplateDefinitions')) {
    function tonpayTemplateDefinitions()
    {
        $payment = ['purchase_amount', 'payable_amount', 'invoice_id', 'tracking_code'];
        $titled = array_merge($payment, ['gateway_title']);
        $status = array_merge($payment, ['review_deadline']);
        $card = array_merge($titled, ['card_number', 'cardholder_name', 'amount_warning']);
        $progress = array_merge($titled, ['review_deadline']);
        $statusDefaults = [
            'awaiting_payment' => ['📌 پرداخت شما به‌صورت خودکار در حال بررسی است. اگر تا ۱۰ دقیقه تأیید نشد، می‌توانید رسید خود را ارسال کنید.', 'وضعیت: در انتظار پرداخت و تأیید خودکار'],
            'receipt_available' => ['📌 پرداخت شما به‌صورت خودکار تأیید نشده است. اکنون می‌توانید رسید پرداخت را برای بررسی ارسال کنید.', 'وضعیت: امکان ارسال رسید'],
            'need_action' => ['📌 تون‌پی برای این پرداخت اقدام بیشتری از شما خواسته است. لطفاً رسید واضح‌تری ارسال کنید.', 'وضعیت: نیاز به اقدام'],
            'receipt_submitted' => ['📌 رسید شما با موفقیت ارسال شد و توسط تون‌پی در حال بررسی است. بررسی پرداخت ممکن است تا ۲۴ ساعت طول بکشد.', 'وضعیت: رسید ارسال‌شده و در حال بررسی'],
            'receipt_rejected' => ["⚠️ رسید پرداخت شما در بررسی اولیه رد شده است.\nدر صورتی که وجه را واریز کرده‌اید، تراکنش تا ۲۴ ساعت برای پیگیری و بررسی مجدد در دسترس خواهد بود.\nلطفاً در صورت نیاز با پشتیبانی تماس بگیرید.", 'وضعیت: رسید در بررسی اولیه رد شده و در حال پیگیری'],
            'review_timeout' => ["⌛️ مهلت بررسی ۲۴ ساعته به پایان رسید و پرداخت شما هنوز تأیید نشده است.\nاگر وجه را واریز کرده‌اید با پشتیبانی تماس بگیرید و شناسه فاکتور {invoice_id} را اعلام کنید.\nدر صورت تأیید بعدی تون‌پی، پرداخت شما به‌صورت خودکار ثبت می‌شود.", 'وضعیت: پایان مهلت بررسی'],
            'confirming' => ['📌 پرداخت شما تأیید شد و در حال نهایی‌سازی است.', 'وضعیت: تأیید پرداخت و نهایی‌سازی'],
            'paid' => ['✅ پرداخت شما با موفقیت تأیید شد.', 'وضعیت: پرداخت تأییدشده'],
            'rejected' => ["⚠️ رسید پرداخت در بررسی تون‌پی رد شده است.\nدر صورت واریز وجه با پشتیبانی تماس بگیرید و شناسه فاکتور {invoice_id} را اعلام کنید.\nدر صورت تأیید بعدی تون‌پی، پرداخت شما به‌صورت خودکار ثبت می‌شود.", 'وضعیت: رسید ردشده'],
            'canceled' => ['⚠️ این فاکتور لغو شده است. اگر قبلاً وجه واریز کرده‌اید و تون‌پی آن را تأیید کند، پرداخت شما همچنان ثبت می‌شود.', 'وضعیت: فاکتور لغو‌شده'],
            'expired' => ["⌛️ این فاکتور منقضی شده است.\nدر صورت واریز وجه با پشتیبانی تماس بگیرید و شناسه فاکتور {invoice_id} را اعلام کنید.", 'وضعیت: فاکتور منقضی‌شده'],
        ];
        $definitions = [
            'dyn_tonpay_invoice_tpl' => [
                'label' => 'پیام اولیه فاکتور (با اطلاعات کارت)',
                'default' => "💠 <b>{gateway_title}</b>\n\n💰 مبلغ شارژ / خرید: <b>{purchase_amount}</b> تومان\n💳 مبلغ قابل پرداخت: <b>{payable_amount}</b> ریال\n\n{amount_warning}\n\n🏦 شماره کارت:\n<code>{card_number}</code>\n👤 به نام: {cardholder_name}\n\n🧾 شناسه فاکتور: <code>{invoice_id}</code>\n🛒 کد پیگیری: <code>{tracking_code}</code>",
                'allowed' => $card,
                'required' => ['payable_amount', 'amount_warning', 'card_number', 'invoice_id'],
            ],
            'dyn_tonpay_progress_tpl' => [
                'label' => 'پیام وضعیت فاکتور (بدون اطلاعات کارت)',
                'default' => "💠 <b>{gateway_title}</b>\n\n💰 مبلغ شارژ / خرید: <b>{purchase_amount}</b> تومان\n💳 مبلغ قابل پرداخت: <b>{payable_amount}</b> ریال\n\n🧾 شناسه فاکتور: <code>{invoice_id}</code>\n🛒 کد پیگیری: <code>{tracking_code}</code>",
                'allowed' => $progress,
                'required' => ['payable_amount', 'invoice_id'],
            ],
            'dyn_tonpay_amount_warning' => [
                'label' => 'هشدار واریز دقیق مبلغ',
                'default' => '⚠️ لطفاً مبلغ پرداخت را با استفاده از دکمه «{copy_button_name}» کپی کرده و دقیقاً همان مبلغ را واریز کنید. از گرد کردن یا تغییر مبلغ خودداری کنید.',
                'allowed' => ['copy_button_name'],
                'required' => [],
            ],
            'dyn_tonpay_card_only_warning' => [
                'label' => 'هشدار فقط کارت‌به‌کارت',
                'default' => '🚫 پرداخت فقط از طریق کارت‌به‌کارت مجاز است. از واریز با شماره شبا، پایا، ساتنا یا سایر روش‌های انتقال بانکی خودداری کنید.',
                'allowed' => [],
                'required' => [],
            ],
            'dyn_tonpay_countdown_wait' => [
                'label' => 'شمارش معکوس تأیید خودکار تا امکان ارسال رسید',
                'default' => '⏱ زمان باقی‌مانده تا امکان ارسال رسید: <b>{remaining_time}</b>',
                'allowed' => ['remaining_time'],
                'required' => ['remaining_time'],
            ],
            'dyn_tonpay_countdown_review' => [
                'label' => 'شمارش معکوس مهلت بررسی ۲۴ ساعته',
                'default' => "⏱ زمان باقی‌مانده بررسی: <b>{remaining_time}</b>\n🕒 پایان مهلت بررسی: {review_deadline}",
                'allowed' => ['remaining_time', 'review_deadline'],
                'required' => ['remaining_time'],
            ],
            'dyn_tonpay_receipt_prompt' => [
                'label' => 'راهنمای ارسال رسید',
                'default' => "📤 تصویر رسید پرداخت را ارسال کنید.\n\nفقط تصاویر JPG، PNG یا WEBP تا حجم ۱۰ مگابایت پذیرفته می‌شود.",
                'allowed' => [],
                'required' => [],
            ],
            'dyn_tonpay_receipt_submitted' => [
                'label' => 'پیام ثبت موفق رسید',
                'default' => "✅ رسید پرداخت شما با موفقیت ثبت شد.\n\n⏳ رسید شما توسط تون‌پی در حال بررسی است.\n\n🕒 زمان بررسی: حداکثر ۲۴ ساعت\n\n🔄 نتیجه پرداخت پس از تأیید نهایی ثبت خواهد شد.",
                'allowed' => [],
                'required' => [],
            ],
        ];
        foreach ($statusDefaults as $phase => $pair) {
            $definitions['dyn_tonpay_status_' . $phase] = [
                'label' => $pair[1],
                'default' => $pair[0],
                'allowed' => $status,
                'required' => [],
            ];
        }
        return $definitions;
    }
}

if (!function_exists('tonpayTemplateKeys')) {
    function tonpayTemplateKeys()
    {
        return array_keys(tonpayTemplateDefinitions());
    }
}

if (!function_exists('tonpayTemplateDefaults')) {
    function tonpayTemplateDefaults()
    {
        $defaults = [];
        foreach (tonpayTemplateDefinitions() as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }
        return $defaults;
    }
}

if (!function_exists('tonpayTemplateIsKnown')) {
    function tonpayTemplateIsKnown($key)
    {
        return array_key_exists((string) $key, tonpayTemplateDefinitions());
    }
}

if (!function_exists('tonpayTemplatePlaceholderHint')) {
    function tonpayTemplatePlaceholderHint($key)
    {
        $definitions = tonpayTemplateDefinitions();
        if (!isset($definitions[$key])) {
            return '';
        }
        $tokens = array_map(static function ($name) {
            return '{' . $name . '}';
        }, $definitions[$key]['allowed']);
        return implode(' ', $tokens);
    }
}

if (!function_exists('tonpayTemplateHtmlIssue')) {
    function tonpayTemplateHtmlIssue($text)
    {
        $allowedTags = ['b', 'strong', 'i', 'em', 'u', 'ins', 's', 'strike', 'del', 'code', 'pre', 'a', 'blockquote', 'tg-spoiler', 'span', 'tg-emoji'];
        $stack = [];
        $stripped = preg_replace_callback('/<(\/?)([a-zA-Z][a-zA-Z0-9\-]*)([^<>]*)>/', static function ($match) use (&$stack, $allowedTags) {
            $name = strtolower($match[2]);
            if (!in_array($name, $allowedTags, true)) {
                $stack[] = '!' . $name;
                return '';
            }
            if ($match[1] === '/') {
                if (empty($stack) || array_pop($stack) !== $name) {
                    $stack[] = '!mismatch';
                }
                return '';
            }
            $stack[] = $name;
            return '';
        }, (string) $text);
        if ($stripped === null) {
            return 'ساختار HTML متن قابل بررسی نیست.';
        }
        foreach ($stack as $entry) {
            if ($entry[0] === '!') {
                return 'تگ HTML نامعتبر یا بسته‌نشده در متن وجود دارد.';
            }
        }
        if (!empty($stack)) {
            return 'تگ HTML بسته‌نشده در متن وجود دارد.';
        }
        if (strpos($stripped, '<') !== false || strpos($stripped, '>') !== false) {
            return 'کاراکترهای < و > باید به‌صورت تگ معتبر یا &lt; و &gt; نوشته شوند.';
        }
        return '';
    }
}

if (!function_exists('tonpayTemplateValidate')) {
    function tonpayTemplateValidate($key, $text)
    {
        $definitions = tonpayTemplateDefinitions();
        if (!isset($definitions[$key])) {
            return '';
        }
        $text = (string) $text;
        if (trim($text) === '') {
            return 'متن نمی‌تواند خالی باشد.';
        }
        if (mb_strlen($text) > 1500) {
            return 'متن بیش از حد طولانی است (حداکثر ۱۵۰۰ کاراکتر).';
        }
        preg_match_all('/\{([^{}]*)\}/', $text, $found);
        $used = [];
        foreach ($found[1] as $name) {
            if (!in_array($name, $definitions[$key]['allowed'], true)) {
                return 'متغیر {' . $name . '} در این متن مجاز نیست.';
            }
            $used[$name] = true;
        }
        foreach ($definitions[$key]['required'] as $name) {
            if (!isset($used[$name])) {
                return 'متغیر {' . $name . '} باید در متن وجود داشته باشد.';
            }
        }
        return tonpayTemplateHtmlIssue($text);
    }
}

if (!function_exists('tonpayTemplateResolve')) {
    function tonpayTemplateResolve($key)
    {
        $definitions = tonpayTemplateDefinitions();
        if (!isset($definitions[$key])) {
            return '';
        }
        $stored = function_exists('faoxima_textbot_get') ? (string) faoxima_textbot_get($key, '') : '';
        if ($stored !== '' && tonpayTemplateValidate($key, $stored) === '') {
            return $stored;
        }
        return $definitions[$key]['default'];
    }
}

if (!function_exists('tonpayTemplateRender')) {
    function tonpayTemplateRender($key, array $values, $html = true, array $rawNames = [])
    {
        $template = tonpayTemplateResolve($key);
        if (!$html) {
            $template = html_entity_decode(strip_tags($template), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $replacements = [];
        foreach ($values as $name => $value) {
            $value = (string) $value;
            if ($html && !in_array($name, $rawNames, true)) {
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $replacements['{' . $name . '}'] = $value;
        }
        return strtr($template, $replacements);
    }
}

if (!function_exists('tonpayButtonStyleLabels')) {
    function tonpayButtonStyleLabels()
    {
        return [
            'copy_card' => '📋 کپی شماره کارت',
            'copy_amount' => '📋 کپی مبلغ پرداخت',
            'change_card' => '🔁 تعویض کارت',
            'check_status' => '🔄 بررسی وضعیت پرداخت',
            'send_receipt' => '📤 ارسال رسید پرداخت',
            'cancel_transaction' => '❌ لغو تراکنش',
            'back_main' => '🔙 بازگشت به منوی اصلی',
            'cancel_confirm_approve' => '✅ بله، لغو تراکنش (تأیید لغو)',
            'cancel_confirm_back' => '🔙 بازگشت (تأیید لغو)',
        ];
    }
}

if (!function_exists('tonpayButtonStyleDefaults')) {
    function tonpayButtonStyleDefaults()
    {
        return [
            'copy_card' => 'primary',
            'copy_amount' => 'success',
            'change_card' => 'primary',
            'check_status' => 'primary',
            'send_receipt' => 'danger',
            'cancel_transaction' => 'danger',
            'back_main' => 'danger',
            'cancel_confirm_approve' => 'danger',
            'cancel_confirm_back' => 'default',
        ];
    }
}

if (!function_exists('tonpayButtonStyleAllowed')) {
    function tonpayButtonStyleAllowed()
    {
        return ['none', 'primary', 'success', 'danger'];
    }
}

if (!function_exists('tonpayButtonStyleMap')) {
    function tonpayButtonStyleMap()
    {
        $defaults = tonpayButtonStyleDefaults();
        $configured = [];
        $useDefaults = true;
        try {
            $row = function_exists('select') ? select('setting', 'keyboard_styles_all', null, null, 'select') : null;
            $all = is_array($row) && !empty($row['keyboard_styles_all']) ? json_decode((string) $row['keyboard_styles_all'], true) : null;
            if (is_array($all)) {
                if (array_key_exists('_use_defaults', $all)) {
                    $useDefaults = (bool) $all['_use_defaults'];
                }
                if (isset($all['tonpay_payment']) && is_array($all['tonpay_payment'])) {
                    $configured = $all['tonpay_payment'];
                }
            }
        } catch (Throwable $e) {
            $configured = [];
        }
        $map = [];
        foreach ($defaults as $key => $factory) {
            $value = $configured[$key] ?? null;
            if (is_string($value) && in_array($value, tonpayButtonStyleAllowed(), true)) {
                $map[$key] = $value;
            } elseif ($useDefaults && in_array($factory, tonpayButtonStyleAllowed(), true)) {
                $map[$key] = $factory;
            } else {
                $map[$key] = 'default';
            }
        }
        return $map;
    }
}

if (!function_exists('tonpayButtonStyle')) {
    function tonpayButtonStyle($key)
    {
        $map = tonpayButtonStyleMap();
        return $map[$key] ?? 'default';
    }
}

if (!function_exists('tonpayStyledButton')) {
    function tonpayStyledButton(array $button, $styleKey)
    {
        $style = tonpayButtonStyle($styleKey);
        if ($style !== 'default') {
            $button['style'] = $style;
            $button['_rxkeep'] = true;
        }
        return $button;
    }
}

if (!function_exists('tonpayLegacyAmountWarningDefault')) {
    function tonpayLegacyAmountWarningDefault()
    {
        return '⚠️ لطفاً مبلغ پرداخت را حتماً با استفاده از دکمه «کپی مبلغ پرداخت» کپی کرده و دقیقاً همان مبلغ را واریز کنید. از گرد کردن یا تغییر مبلغ خودداری کنید.';
    }
}

if (!function_exists('tonpayDefaultGatewayTitle')) {
    function tonpayDefaultGatewayTitle()
    {
        return 'پرداخت کارت‌به‌کارت (تون‌پی)';
    }
}

if (!function_exists('tonpayGatewayDisplayName')) {
    function tonpayGatewayDisplayName()
    {
        $stored = function_exists('faoxima_textbot_get') ? (string) faoxima_textbot_get('tonpay', '') : '';
        $name = preg_replace('/^[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]+|[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]+$/u', '', $stored);
        if (!is_string($name) || $name === '' || $name === '💠 تون‌پی') {
            return tonpayDefaultGatewayTitle();
        }
        return mb_substr($name, 0, 120);
    }
}

if (!function_exists('tonpayGatewayHeading')) {
    function tonpayGatewayHeading()
    {
        $name = tonpayGatewayDisplayName();
        $stripped = preg_replace('/^💠\s*/u', '', $name);
        return is_string($stripped) && $stripped !== '' ? $stripped : $name;
    }
}
