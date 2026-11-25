<?php

namespace Faoxima\Telegram\Support;

final class MessageTemplates
{
    private static ?array $definitions = null;

    public static function categories(): array
    {
        $titles = [
            'msg_pay_card' => ['پیام‌های کارت‌به‌کارت', 'wallet'],
            'msg_pay_crypto' => ['پیام‌های ارز دیجیتال و هش', 'coins'],
            'msg_pay_forapp' => ['تأیید خودکار واریز (ForApp)', 'wallet'],
            'msg_pay_gateways' => ['پیام‌های درگاه‌ها (ریالیت و اطلس‌پی)', 'wallet'],
            'msg_pay_atlaspay' => ['اطلس‌پی: صفحهٔ پرداخت و وضعیت‌ها', 'credit-card'],
            'msg_pay_tonpay' => ['تون‌پی: وضعیت‌ها و هشدارها', 'wallet'],
            'msg_pay_invoice' => ['پیام ایجاد تراکنش درگاه‌ها', 'wallet'],
            'msg_wallet_tx' => ['تراکنش‌های کیف پول', 'wallet'],
            'msg_account' => ['حساب کاربری', 'user'],
            'msg_onboarding' => ['ورود و احراز کاربر', 'user'],
            'msg_notify' => ['اعلان‌های خودکار به کاربر', 'bell'],
            'msg_pay_pending' => ['لیست تراکنش‌های در انتظار', 'wallet'],
            'msg_pay_followup' => ['پیگیری، لغو و هشدارهای تراکنش', 'wallet'],
            'msg_btn_payment' => ['برچسب دکمه‌های پرداخت', 'wallet'],
            'msg_pay_refund' => ['بازگشت وجه و اعتبار کیف پول', 'coins'],
            'msg_support' => ['پشتیبانی و تیکت', 'ticket'],
            'msg_service_info' => ['اطلاعات تکمیلی سرویس', 'circle-info'],
            'msg_btn_common' => ['برچسب دکمه‌های عمومی', 'gear'],
            'msg_btn_service' => ['برچسب دکمه‌های سرویس', 'server'],
            'msg_service_buy' => ['خرید و ساخت سرویس', 'cart-shopping'],
            'msg_service_renew' => ['تمدید و افزایش سرویس', 'server'],
            'msg_service_manage' => ['مدیریت سرویس و کانفیگ', 'gear'],
        ];
        $categories = [];
        foreach ($titles as $category => [$title, $icon]) {
            $categories[$category] = ['title' => $title, 'icon' => $icon, 'keys' => []];
        }
        foreach (self::definitions() as $key => $definition) {
            $categories[$definition['category']]['keys'][] = $key;
        }
        return $categories;
    }

    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::definitions() as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }
        return $defaults;
    }

    public static function has(string $key): bool
    {
        return isset(self::definitions()[$key]);
    }

    public static function variables(string $key): array
    {
        return self::definitions()[$key]['vars'] ?? [];
    }

    public static function label(string $key): string
    {
        return self::definitions()[$key]['label'] ?? '';
    }

    public static function get(string $key, array $vars = [], array $raw = []): string
    {
        return self::resolve($key, $vars, $raw, true);
    }

    public static function plain(string $key, array $vars = []): string
    {
        return self::resolve($key, $vars, [], false);
    }

    public static function template(string $key): string
    {
        $definition = self::definitions()[$key] ?? null;
        if ($definition === null) {
            return '';
        }
        return (string) Texts::faoxima_textbot_get($key, $definition['default']);
    }

    private static function resolve(string $key, array $vars, array $raw, bool $escape): string
    {
        $definition = self::definitions()[$key] ?? null;
        if ($definition === null) {
            return '';
        }
        $template = (string) Texts::faoxima_textbot_get($key, $definition['default']);
        $replacements = [];
        foreach (array_keys($definition['vars']) as $name) {
            if (array_key_exists($name, $raw)) {
                $replacements['{' . $name . '}'] = (string) $raw[$name];
            } elseif (array_key_exists($name, $vars)) {
                $value = (string) $vars[$name];
                $replacements['{' . $name . '}'] = $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
            } else {
                $replacements['{' . $name . '}'] = '';
            }
        }
        return strtr($template, $replacements);
    }

    private static function def(string $category, string $label, string $default, array $vars = []): array
    {
        return ['category' => $category, 'label' => $label, 'default' => $default, 'vars' => $vars];
    }

    public static function definitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }
        $amount = ['amount' => 'مبلغ به تومان با جداکنندهٔ هزارگان'];
        $d = [];

        $d['payment_card_receipt_rate_limited'] = self::def('msg_pay_card', 'محدودیت ارسال پیاپی رسید', "❗ شما در ۲ دقیقه اخیر رسید ارسال کرده اید لطفا ۲ دقیقه دیگر رسید جدید را ارسال نمایید.");
        $d['payment_already_confirmed'] = self::def('msg_pay_card', 'تراکنش قبلاً تأیید شده', "❗️ تراکنش شما توسط ربات تایید گردیده است.");
        $d['payment_expired_no_receipt'] = self::def('msg_pay_card', 'پایان مهلت تراکنش', "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.");
        $d['payment_card_ask_receipt_image'] = self::def('msg_pay_card', 'درخواست تصویر رسید', "🖼 تصویر رسید خود را ارسال نمایید");
        $d['payment_internal_error_restart'] = self::def('msg_pay_card', 'خطای داخلی پرداخت', "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.");
        $d['payment_card_invalid_selection'] = self::def('msg_pay_card', 'کارت انتخاب‌شده نامعتبر', "❌ کارت انتخاب‌شده معتبر نیست. لطفاً دوباره تلاش کنید.");
        $d['payment_card_none_active_contact_support'] = self::def('msg_pay_card', 'نبود کارت فعال', "❌ کارت بانکی فعالی یافت نشد. با پشتیبانی تماس بگیرید.");
        $d['payment_card_single_photo_required'] = self::def('msg_pay_card', 'ارسال تصویر تکی کارت', "❌ لطفاً یک تصویر تکی از کارت خود ارسال کنید.");
        $d['payment_record_not_found_restart'] = self::def('msg_pay_card', 'رکورد پرداخت یافت نشد', "❌ رکورد پرداخت یافت نشد. مراحل را از ابتدا انجام دهید.");
        $d['payment_card_ask_last4'] = self::def('msg_pay_card', 'درخواست ۴ رقم آخر کارت', "✅ عکس کارت دریافت شد.\n\n🔢 لطفاً ۴ رقم آخر شماره کارت خود را وارد کنید:");
        $d['payment_card_last4_invalid'] = self::def('msg_pay_card', 'خطای ۴ رقم آخر کارت', "❌ لطفاً دقیقاً ۴ رقم آخر کارت را وارد کنید (مثال: 1234).");
        $d['payment_card_btn_send_receipt'] = self::def('msg_pay_card', 'برچسب دکمهٔ ارسال رسید', "✅ پرداخت کردم | ارسال رسید.");
        $d['payment_card_btn_copy_amount'] = self::def('msg_pay_card', 'برچسب دکمهٔ کپی مبلغ', "کپی مبلغ");

        $d['payment_crypto_ask_hash_or_image'] = self::def('msg_pay_crypto', 'درخواست تصویر یا لینک تراکنش', "📌 تصویر واریزی خود یا لینک تراکنش ترون را ارسال نمایید.");
        $d['payment_crypto_hash_mode_exited'] = self::def('msg_pay_crypto', 'خروج از حالت ارسال هش', "🏠 از حالت ارسال هش خارج شدید. اکنون می‌توانید از منو استفاده کنید.");
        $d['payment_crypto_internal_error_retry'] = self::def('msg_pay_crypto', 'خطای داخلی پرداخت ارز دیجیتال', "❌ خطای داخلی، لطفاً مجدداً از منوی پرداخت اقدام کنید.");
        $d['payment_crypto_hashchecker_unavailable'] = self::def('msg_pay_crypto', 'ماژول بررسی هش در دسترس نیست', "❌ ماژول هش‌چکر بارگذاری نشده است.");
        $d['payment_crypto_hash_not_found'] = self::def('msg_pay_crypto', 'هش معتبر پیدا نشد', "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش تراکنش (Hash / TxID) یا لینک آن را ارسال کنید — نه آدرس کیف‌پول.");
        $d['payment_crypto_receipt_mode_exited'] = self::def('msg_pay_crypto', 'خروج از حالت ارسال رسید', "🏠 از حالت ارسال رسید خارج شدید.");
        $d['payment_crypto_receipt_photo_required'] = self::def('msg_pay_crypto', 'الزام ارسال عکس رسید', "❌ عکس دریافت نشد. لطفاً <b>عکس رسید تراکنش</b> را ارسال کنید (اجباری — فاکتور بدون عکس رسید برای ادمین ثبت نمی‌شود).");
        $d['payment_invoice_canceled'] = self::def('msg_pay_crypto', 'لغو فاکتور', "✅ فاکتور لغو شد.");

        $d['payment_forapp_pending_exists'] = self::def('msg_pay_forapp', 'وجود پرداخت ناتمام', "شما یک پرداخت ناتمام دارید. برای ایجاد پرداخت جدید، ابتدا پرداخت قبلی را تکمیل یا لغو کنید.");
        $d['payment_forapp_auto_verification'] = self::def('msg_pay_forapp', 'راهنمای پرداخت با تأیید خودکار', "🔐 <b>تأیید خودکار واریز فعال است</b>\n<blockquote>💰 مبلغ سفارش/شارژ: {principal} تومان\n🏷 کارمزد شناسایی: {fee} تومان\n✅ مبلغ دقیق قابل واریز: <b>{payable} تومان</b>\n🔢 معادل ریالی: <code>{payable_rial}</code> ریال</blockquote>\n⚠️ دقیقاً همین مبلغ را یکجا واریز کنید؛ مبلغ کمتر، بیشتر یا چند واریز جداگانه به‌صورت خودکار تأیید نمی‌شود.\nℹ️ کارمزد شناسایی جزو شارژ کیف پول یا مبلغ سفارش نیست و بازگردانده نمی‌شود.\n⏳ مهلت پرداخت تا ساعت <b>{expires_at}</b>\n🤖 پس از واریز، پرداخت به‌صورت خودکار بررسی و تأیید می‌شود.\n📤 اگر تا ساعت {receipt_after} تأیید نشد، می‌توانید رسید را ارسال کنید.", [
            'principal' => 'مبلغ اصلی سفارش یا شارژ (تومان)',
            'fee' => 'کارمزد شناسایی (تومان)',
            'payable' => 'مبلغ دقیق قابل واریز (تومان)',
            'payable_rial' => 'معادل ریالی مبلغ قابل واریز',
            'expires_at' => 'ساعت پایان مهلت پرداخت',
            'receipt_after' => 'ساعت امکان ارسال رسید',
        ]);
        $d['payment_forapp_btn_send_receipt'] = self::def('msg_pay_forapp', 'برچسب دکمهٔ ارسال رسید', "📤 ارسال رسید (از ساعت {receipt_after})", ['receipt_after' => 'ساعت امکان ارسال رسید']);
        $d['payment_forapp_btn_copy_amount'] = self::def('msg_pay_forapp', 'برچسب دکمهٔ کپی مبلغ ریالی', "کپی مبلغ به ریال");

        $d['payment_rialit_processing'] = self::def('msg_pay_gateways', 'ریالیت: درخواست در حال پردازش', "⏳ درخواست پرداخت شما در حال پردازش است؛ کمی بعد دوباره تلاش کنید.");
        $d['payment_rialit_gateway_disabled'] = self::def('msg_pay_gateways', 'ریالیت: درگاه غیرفعال', "❌ درگاه ریالیت در حال حاضر غیرفعال است.");
        $d['payment_rialit_cancel_confirm'] = self::def('msg_pay_gateways', 'ریالیت: تأیید انصراف', "از این پرداخت انصراف می‌دهید؟ پس از انصراف می‌توانید پرداخت جدید بسازید. این کار لینک قبلی ریالیت را باطل نمی‌کند؛ اگر پرداخت قبلی بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود و سفارش قبلی اجرا نمی‌شود.");
        $d['payment_rialit_choose_new_method'] = self::def('msg_pay_gateways', 'ریالیت: انتخاب روش پرداخت جدید', "💳 روش پرداخت جدید را انتخاب کنید:");
        $d['payment_rialit_status_waiting'] = self::def('msg_pay_gateways', 'ریالیت: وضعیت در انتظار نتیجه', "⏳ در انتظار نتیجه");
        $d['payment_rialit_status_abandoned'] = self::def('msg_pay_gateways', 'ریالیت: وضعیت انصراف', "🚫 از این پرداخت انصراف داده‌اید. اگر بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود.");
        $d['payment_rialit_status_completed'] = self::def('msg_pay_gateways', 'ریالیت: وضعیت تکمیل سفارش', "✅ پرداخت و سفارش تکمیل شد");
        $d['payment_rialit_status_paid_finalizing'] = self::def('msg_pay_gateways', 'ریالیت: وضعیت تأیید و تکمیل', "⏳ پرداخت تأیید شد؛ در حال تکمیل سفارش");
        $d['payment_rialit_status_expired'] = self::def('msg_pay_gateways', 'ریالیت: وضعیت منقضی', "⌛ پرداخت منقضی شده است");
        $d['payment_rialit_status_canceled'] = self::def('msg_pay_gateways', 'ریالیت: وضعیت لغو', "❌ پرداخت در ریالیت لغو شده است");
        $d['payment_rialit_status_recovering_link'] = self::def('msg_pay_gateways', 'ریالیت: بازیابی لینک', "⏳ در حال بازیابی لینک پرداخت");
        $d['payment_tetraminator_processing'] = self::def('msg_pay_gateways', 'تترامیناتور: درخواست در حال پردازش', "⏳ درخواست پرداخت شما در حال پردازش است؛ کمی بعد دوباره تلاش کنید.");
        $d['payment_tetraminator_active_invoice_blocked'] = self::def('msg_pay_gateways', 'تترامیناتور: وجود فاکتور فعال', "شما یک فاکتور پرداخت فعال در تترامیناتور دارید. برای ایجاد پرداخت جدید، ابتدا پرداخت قبلی را تکمیل یا از آن انصراف دهید.\n\nانصراف فقط در سیستم ما ثبت می‌شود و لینک قبلی را در تترامیناتور باطل نمی‌کند.");
        $d['payment_tetraminator_active_invoice_unverifiable'] = self::def('msg_pay_gateways', 'تترامیناتور: وضعیت فاکتور فعال قابل بررسی نیست', "وضعیت فاکتور فعال تترامیناتور شما هنوز قابل بررسی نیست. چند لحظه بعد با دکمه «بررسی وضعیت» دوباره تلاش کنید؛ تا آن زمان پرداخت جدید ایجاد نمی‌شود.");
        $d['payment_tetraminator_gateway_disabled'] =self::def('msg_pay_gateways', 'تترامیناتور: درگاه غیرفعال', "❌ درگاه تترامیناتور در حال حاضر غیرفعال است.");
        $d['payment_tetraminator_cancel_confirm'] = self::def('msg_pay_gateways', 'تترامیناتور: تأیید انصراف', "از این پرداخت انصراف می‌دهید؟ پس از انصراف می‌توانید پرداخت جدید بسازید. این کار لینک قبلی تترامیناتور را باطل نمی‌کند؛ اگر پرداخت قبلی بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود و سفارش قبلی اجرا نمی‌شود.");
        $d['payment_tetraminator_choose_new_method'] = self::def('msg_pay_gateways', 'تترامیناتور: انتخاب روش پرداخت جدید', "💳 روش پرداخت جدید را انتخاب کنید:");
        $d['payment_tetraminator_status_waiting'] = self::def('msg_pay_gateways', 'تترامیناتور: وضعیت در انتظار نتیجه', "⏳ در انتظار نتیجه");
        $d['payment_tetraminator_status_abandoned'] = self::def('msg_pay_gateways', 'تترامیناتور: وضعیت انصراف', "🚫 از این پرداخت انصراف داده‌اید. اگر بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود.");
        $d['payment_tetraminator_status_completed'] = self::def('msg_pay_gateways', 'تترامیناتور: وضعیت تکمیل سفارش', "✅ پرداخت و سفارش تکمیل شد");
        $d['payment_tetraminator_status_paid_finalizing'] = self::def('msg_pay_gateways', 'تترامیناتور: وضعیت تأیید و تکمیل', "⏳ پرداخت تأیید شد؛ در حال تکمیل سفارش");
        $d['payment_tetraminator_status_expired'] = self::def('msg_pay_gateways', 'تترامیناتور: وضعیت منقضی', "⌛ پرداخت منقضی شده است");
        $d['payment_tetraminator_status_recovering_link'] = self::def('msg_pay_gateways', 'تترامیناتور: بازیابی لینک', "⏳ در حال ساخت لینک پرداخت");
        $d['payment_tetraminator_status_link_expired'] = self::def('msg_pay_gateways', 'تترامیناتور: پایان مهلت ورود', "⏳ مهلت ورود به پرداخت تمام شده؛ در انتظار نتیجه");
        $d['payment_rialit_status_link_expired'] = self::def('msg_pay_gateways', 'ریالیت: پایان مهلت ورود', "⏳ مهلت ورود به پرداخت تمام شده؛ در انتظار نتیجه");
        $d['payment_atlaspay_cancel_confirm'] = self::def('msg_pay_gateways', 'اطلس‌پی: تأیید لغو تراکنش', "آیا از لغو این تراکنش اطمینان دارید؟\n\n⚠️ لغو فاکتور به‌معنی بازگشت وجه واریزشده نیست. اطلس‌پی فقط تا زمانی که هیچ پرداخت یا رسیدی برای این سفارش ثبت نشده باشد اجازه لغو می‌دهد.");
        $d['payment_atlaspay_cancel_blocked_paid'] = self::def('msg_pay_gateways', 'اطلس‌پی: لغو ممکن نیست (پرداخت‌شده)', "پرداخت شما در اطلس‌پی تأیید شده است و فاکتور قابل لغو نیست.");
        $d['payment_atlaspay_cancel_blocked_review'] = self::def('msg_pay_gateways', 'اطلس‌پی: لغو ممکن نیست (در بررسی)', "برای این سفارش پرداخت یا رسید ثبت شده و اطلس‌پی اجازه لغو نمی‌دهد.");
        $d['payment_atlaspay_cancel_blocked_generic'] = self::def('msg_pay_gateways', 'اطلس‌پی: لغو ممکن نیست', "این فاکتور دیگر قابل لغو نیست.");
        $d['payment_atlaspay_amount_warning'] = self::def('msg_pay_gateways', 'اطلس‌پی: هشدار کپی دقیق مبلغ', "⚠️ لطفاً مبلغ پرداخت را حتماً با استفاده از دکمه «{copy_button_name}» کپی کرده و دقیقاً همان مبلغ را واریز کنید. از گرد کردن یا تغییر مبلغ خودداری کنید.", ['copy_button_name' => 'نام دکمهٔ کپی مبلغ']);
        $d['payment_atlaspay_card_only_warning'] = self::def('msg_pay_gateways', 'اطلس‌پی: هشدار فقط کارت‌به‌کارت', "🚫 پرداخت فقط از طریق کارت‌به‌کارت مجاز است. از واریز با شماره شبا، پایا، ساتنا یا سایر روش‌های انتقال بانکی خودداری کنید.");

        $d['payment_rialit_late_approved_wallet'] = self::def('msg_pay_refund', 'ریالیت: تأیید پس از انصراف و شارژ کیف پول', "💚 پرداخت قبلی ریالیت شما پس از انصراف تأیید شد. مبلغ {amount} تومان به کیف پول اضافه شد؛ سفارش قبلی اجرا نشد.", $amount);
        $d['payment_tetraminator_late_approved_wallet'] = self::def('msg_pay_refund', 'تترامیناتور: تأیید پس از انصراف و شارژ کیف پول', "💎 پرداخت قبلی تترامیناتور شما پس از انصراف تأیید شد. مبلغ {amount} تومان به کیف پول اضافه شد؛ سفارش قبلی اجرا نشد.", $amount);
        $d['payment_wallet_insufficient_credited'] = self::def('msg_pay_refund', 'کیف پول ناکافی؛ مبلغ پرداختی شارژ شد', "پرداخت شما تأیید شد، اما موجودی کیف پول برای تکمیل خرید کافی نبود. مبلغ پرداختی شما، معادل {amount} تومان، به کیف پولتان اضافه شد. لطفاً دوباره خرید کنید.", $amount);
        $d['payment_order_unfulfillable_refunded'] = self::def('msg_pay_refund', 'سفارش قابل تکمیل نیست؛ وجه بازگشت', "❌ این سفارش دیگر قابل تکمیل نیست (لغو یا قبلاً تسویه شده است). مبلغ پرداختی شما به کیف پول بازگردانده شد.");
        $d['payment_order_unfulfillable_refund_failed'] = self::def('msg_pay_refund', 'سفارش قابل تکمیل نیست؛ بازگشت وجه ناموفق', "❌ این سفارش دیگر قابل تکمیل نیست و بازگشت وجه با خطا مواجه شد؛ لطفاً با پشتیبانی در ارتباط باشید.");
        $d['service_purchase_stock_empty_refunded'] = self::def('msg_pay_refund', 'خرید: اتمام موجودی انبار ملی؛ وجه بازگشت', "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. مبلغ پرداختی به کیف پول برگشت خورد.");
        $d['service_purchase_stock_empty_refund_failed'] = self::def('msg_pay_refund', 'خرید: اتمام موجودی؛ بازگشت وجه ناموفق', "❌ موجودی انبار برای این محصول تمام شده است. بازگشت وجه با خطا مواجه شد؛ لطفاً با پشتیبانی در ارتباط باشید.");
        $d['service_purchase_failed_refunded'] = self::def('msg_pay_refund', 'خرید: ساخت سرویس ناموفق؛ وجه بازگشت', "💎  کاربر عزیز بدلیل ساخته نشدن سرویس مبلغ {amount} تومان به کیف پول شما اضافه گردید.", $amount);
        $d['service_purchase_failed_refund_failed'] = self::def('msg_pay_refund', 'خرید: ساخت ناموفق؛ بازگشت وجه ناموفق', "❌ ساخت سرویس با خطا مواجه شد و بازگشت وجه نیز انجام نشد؛ لطفاً با پشتیبانی در ارتباط باشید.");
        $d['service_renewal_error_contact_support'] = self::def('msg_pay_refund', 'تمدید: خطا و تماس با پشتیبانی', "❌ خطایی در هنگام تمدید رخ داده با پشتیبانی در ارتباط باشید");
        $d['service_renewal_product_unavailable_refunded'] = self::def('msg_pay_refund', 'تمدید: محصول در دسترس نیست؛ وجه بازگشت', "❌ محصول این تمدید دیگر در دسترس نیست؛ مبلغ پرداختی به کیف پول شما بازگردانده شد.");
        $d['service_renewal_product_unavailable_refund_failed'] = self::def('msg_pay_refund', 'تمدید: محصول در دسترس نیست؛ بازگشت ناموفق', "❌ محصول این تمدید دیگر در دسترس نیست و بازگشت وجه با خطا مواجه شد؛ لطفاً با پشتیبانی در ارتباط باشید.");
        $d['service_renewal_stock_empty_refunded'] = self::def('msg_pay_refund', 'تمدید: اتمام موجودی؛ وجه بازگشت', "❌ موجودی انبار برای این محصول تمام شده است؛ مبلغ پرداختی به کیف پول شما بازگردانده شد.");
        $d['service_renewal_stock_empty_refund_failed'] = self::def('msg_pay_refund', 'تمدید: اتمام موجودی؛ بازگشت ناموفق', "❌ موجودی انبار برای این محصول تمام شده است و بازگشت وجه با خطا مواجه شد؛ لطفاً با پشتیبانی در ارتباط باشید.");
        $d['service_renewal_failed_refunded'] = self::def('msg_pay_refund', 'تمدید: ناموفق؛ وجه بازگشت', "💎  کاربر عزیز بدلیل تمدید نشدن سرویس مبلغ {amount} تومان به کیف پول شما اضافه گردید.", $amount);
        $d['service_renewal_failed_refund_failed'] = self::def('msg_pay_refund', 'تمدید: ناموفق؛ بازگشت ناموفق', "❌ تمدید سرویس با خطا مواجه شد و بازگشت وجه نیز انجام نشد؛ لطفاً با پشتیبانی در ارتباط باشید.");
        $d['service_renewal_cashback_gift'] = self::def('msg_pay_refund', 'هدیهٔ بازگشت وجه تمدید', "تبریک 🎉\n📌 به عنوان هدیه تمدید مبلغ {amount} تومان حساب شما شارژ گردید", $amount);
        $d['wallet_transfer_canceled'] = self::def('msg_pay_refund', 'انتقال موجودی لغو شد', "❌ انتقال موجودی لغو شد.");

        $d['service_select_category'] = self::def('msg_service_buy', 'انتخاب دسته‌بندی', "📌 دسته بندی خود را انتخاب نمایید!");
        $d['service_provisioning_progress'] = self::def('msg_service_buy', 'در حال ساخت سرویس', "♻️ در حال ساختن سرویس شما...");
        $d['service_provision_result_unknown'] = self::def('msg_service_buy', 'نتیجهٔ ساخت سرویس نامشخص', "⏳ نتیجه ساخت اشتراک هنوز مشخص نیست و برای پشتیبانی ثبت شد. تا تعیین نتیجه دوباره خرید نکنید.");
        $d['service_bulk_provision_result_unknown'] = self::def('msg_service_buy', 'نتیجهٔ ساخت در خرید انبوه نامشخص', "⏳ نتیجه ساخت این مورد از خرید انبوه نامشخص است و برای پشتیبانی ثبت شد. هزینه این مورد کسر نشد؛ تا تعیین نتیجه دوباره خرید نکنید.");
        $d['service_stock_empty_choose_another'] = self::def('msg_service_buy', 'اتمام موجودی انبار ملی', "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. لطفاً محصول دیگری انتخاب کنید یا با پشتیبانی ارتباط بگیرید.");
        $d['service_bulk_stock_empty_not_charged'] = self::def('msg_service_buy', 'اتمام موجودی در خرید انبوه', "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. خرید انجام نشد و مبلغی از کیف پول کسر نشد.");

        $d['service_select_duration'] = self::def('msg_service_renew', 'انتخاب مدت سرویس', "مدت سرویس را انتخاب کنید:");
        $d['service_renewal_already_registered'] = self::def('msg_service_renew', 'درخواست تمدید قبلاً ثبت شده', "درخواست تمدید قبلاً ثبت شده است.");
        $d['service_renewal_registration_pending'] = self::def('msg_service_renew', 'وضعیت ثبت تمدید در حال بررسی', "وضعیت ثبت تمدید در حال بررسی است.");
        $d['service_extra_request_already_registered'] = self::def('msg_service_renew', 'درخواست خرید قبلاً ثبت شده', "درخواست خرید قبلاً ثبت شده است.");

        $d['service_usage_30d_report'] = self::def('msg_service_manage', 'گزارش مصرف ۳۰ روز اخیر', "📊 مصرف ۳۰ روز اخیر به‌تفکیک سرور:\n\n{usage}", ['usage' => 'جدول مصرف به‌تفکیک سرور (متن آمادهٔ برنامه)']);
        $d['service_server_change_success'] = self::def('msg_service_manage', 'تغییر سرور موفق', "✅ سرور با موفقیت تغییر کرد.");
        $d['service_server_change_failed'] = self::def('msg_service_manage', 'تغییر سرور ناموفق', "❌ تغییر سرور ناموفق بود.");
        $d['service_connect_first'] = self::def('msg_service_manage', 'ابتدا به کانفیگ متصل شوید', "ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.");
        $d['service_config_file_prepare_failed'] = self::def('msg_service_manage', 'خطا در آماده‌سازی فایل کانفیگ', "❌ خطا در آماده‌سازی فایل کانفیگ.");
        $d['service_config_file_panel_failed'] = self::def('msg_service_manage', 'خطا در دریافت فایل کانفیگ از پنل', "❌ خطا در دریافت فایل کانفیگ از پنل.");
        $d['service_link_change_failed'] = self::def('msg_service_manage', 'خطا در تغییر لینک', "❌ خطایی در تغییر لینک رخ داده است.");

        $trackingVar = ['tracking_code' => 'شناسه پیگیری'];
        $d['payment_atlaspay_line_amount'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: سطر مبلغ شارژ / خرید', "💰 مبلغ شارژ / خرید: {amount} تومان", ['amount' => 'مبلغ به تومان']);
        $d['payment_atlaspay_line_payable'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: سطر مبلغ قابل پرداخت', "💳 مبلغ قابل پرداخت: {payable_rial} ریال", ['payable_rial' => 'مبلغ قابل پرداخت به ریال']);
        $d['payment_atlaspay_line_card_number'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: سطر شماره کارت', "🏦 شماره کارت: <code>{card_number}</code>", ['card_number' => 'شماره کارت']);
        $d['payment_atlaspay_line_card_holder'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: سطر نام صاحب کارت', "👤 به نام: {card_holder}", ['card_holder' => 'نام صاحب کارت']);
        $d['payment_atlaspay_line_bank'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: سطر نام بانک', "🏦 بانک: {bank_name}", ['bank_name' => 'نام بانک']);
        $d['payment_atlaspay_card_incomplete_with_link'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: کارت ناقص با لینک پرداخت', "⚠️ شماره کارت به‌صورت کامل دریافت نشد؛ برای مشاهده اطلاعات کارت و پرداخت، از دکمه «مشاهده اطلاعات پرداخت» استفاده کنید.");
        $d['payment_atlaspay_card_incomplete_no_link'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: کارت ناقص بدون لینک پرداخت', "⚠️ شماره کارت به‌صورت کامل دریافت نشد؛ برای دریافت اطلاعات پرداخت با پشتیبانی فروشگاه در ارتباط باشید.");
        $d['payment_atlaspay_line_tracking'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: سطر شناسه پیگیری', "🧾 شناسه پیگیری: <code>{tracking_code}</code>", $trackingVar);
        $d['payment_atlaspay_line_remaining_time'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: سطر زمان باقی‌مانده', "⏱ زمان باقی‌مانده تا پایان مهلت پرداخت: <b>{remaining}</b>", ['remaining' => 'زمان باقی‌مانده']);
        $d['payment_atlaspay_phase_label_awaiting_payment'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت در انتظار پرداخت', "در انتظار پرداخت");
        $d['payment_atlaspay_phase_label_deadline_check'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت پایان مهلت', "پایان مهلت پرداخت؛ در حال بررسی وضعیت نهایی");
        $d['payment_atlaspay_phase_label_under_review'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت رسید در بررسی', "رسید در حال بررسی اطلس‌پی");
        $d['payment_atlaspay_phase_label_underpaid_review'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت بررسی مغایرت', "بررسی مغایرت مبلغ واریزی");
        $d['payment_atlaspay_phase_label_underpaid_remainder'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت تکمیل مبلغ باقی‌مانده', "در انتظار تکمیل مبلغ باقی‌مانده");
        $d['payment_atlaspay_phase_label_confirming'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت نهایی‌سازی', "تأیید شده و در حال نهایی‌سازی");
        $d['payment_atlaspay_phase_label_manual_review'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت تأیید با کسری', "تأیید با کسری مبلغ؛ منتظر تصمیم مدیر");
        $d['payment_atlaspay_phase_label_paid'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت پرداخت‌شده', "پرداخت تأییدشده");
        $d['payment_atlaspay_phase_label_rejected'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت ردشده', "ردشده");
        $d['payment_atlaspay_phase_label_expired'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت منقضی', "منقضی‌شده");
        $d['payment_atlaspay_phase_label_canceled'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: برچسب وضعیت لغوشده', "لغو‌شده");
        $d['payment_atlaspay_phase_detail_awaiting_payment'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت در انتظار پرداخت', "📌 پرداخت شما به‌صورت خودکار بررسی می‌شود.");
        $d['payment_atlaspay_phase_detail_deadline_check'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت پایان مهلت', "⏱ مهلت پرداخت به پایان رسید و وضعیت نهایی سفارش در اطلس‌پی در حال بررسی است. سفارش منقضی‌شده رسید جدید نمی‌پذیرد؛ اگر مبلغ را واریز کرده‌اید، با پشتیبانی فروشگاه تماس بگیرید.");
        $d['payment_atlaspay_phase_detail_under_review'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت رسید در بررسی', "🔎 رسید شما در اطلس‌پی در حال بررسی است. پس از تأیید، پرداخت به‌صورت خودکار ثبت می‌شود.");
        $d['payment_atlaspay_phase_detail_underpaid_review'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت بررسی مغایرت', "⚠️ مبلغ واریزی کمتر از مبلغ سفارش تشخیص داده شده و مغایرت در حال بررسی است.");
        $d['payment_atlaspay_phase_detail_underpaid_remainder'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت مبلغ باقی‌مانده', "⚠️ مبلغ واریزی کمتر از مبلغ سفارش بوده است. برای تکمیل مبلغ باقی‌مانده یا تصمیم نهایی، با پشتیبانی فروشگاه در ارتباط باشید.");
        $d['payment_atlaspay_phase_detail_confirming'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت نهایی‌سازی', "✅ پرداخت شما تأیید شد و در حال نهایی‌سازی است.");
        $d['payment_atlaspay_phase_detail_manual_review'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت تأیید با کسری', "⚠️ پرداخت شما با مبلغ کمتر از مبلغ کامل تأیید شد و برای تصمیم نهایی به مدیریت ارجاع شد. لطفاً با پشتیبانی در ارتباط باشید.");
        $d['payment_atlaspay_phase_detail_paid'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت پرداخت‌شده', "✅ پرداخت شما با موفقیت تأیید شد.");
        $d['payment_atlaspay_phase_detail_rejected'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت ردشده', "❌ پرداخت شما توسط اطلس‌پی رد شد.{reason_line}\nاگر مبلغ را واریز کرده‌اید، با پشتیبانی تماس بگیرید.", ['reason_line' => 'دلیل رد (در صورت وجود، همراه با خط جدید)']);
        $d['payment_atlaspay_phase_detail_expired'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت منقضی', "⏰ مهلت پرداخت این فاکتور به پایان رسید و سفارش منقضی شده است؛ برای سفارش منقضی‌شده رسید پذیرفته نمی‌شود. اگر مبلغ را واریز کرده‌اید، با پشتیبانی فروشگاه تماس بگیرید تا از گزارش واریز دیرهنگام استفاده شود.");
        $d['payment_atlaspay_phase_detail_canceled'] = self::def('msg_pay_atlaspay', 'اطلس‌پی: توضیح وضعیت لغوشده', "🚫 این تراکنش لغو شد. لغو فاکتور به‌معنی بازگشت وجه واریزشده نیست.");

        $d['payment_tonpay_phase_label_awaiting_payment'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت در انتظار پرداخت', "در انتظار پرداخت و تأیید خودکار");
        $d['payment_tonpay_phase_label_receipt_available'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت امکان ارسال رسید', "امکان ارسال رسید");
        $d['payment_tonpay_phase_label_action_required_less'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت واریز مبلغ باقی‌مانده', "نیاز به اقدام: واریز مبلغ باقی‌مانده");
        $d['payment_tonpay_phase_label_action_required_more'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت ثبت کارت عودت', "نیاز به اقدام: ثبت کارت عودت مبلغ مازاد");
        $d['payment_tonpay_phase_label_local_timeout'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت پایان مهلت محلی', "پایان مهلت محلی؛ در انتظار بررسی نهایی تون‌پی");
        $d['payment_tonpay_phase_label_cancel_pending'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت لغو در انتظار', "لغو محلی؛ در انتظار لغو در تون‌پی");
        $d['payment_tonpay_phase_label_unknown'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت نامشخص', "وضعیت فاکتور در حال بررسی");
        $d['payment_tonpay_phase_label_receipt_submitted'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت رسید ارسال‌شده', "رسید در حال بررسی تون‌پی");
        $d['payment_tonpay_phase_label_receipt_rejected'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت رسید ردشده اولیه', "رسید در بررسی اولیه رد شده (در حال پیگیری)");
        $d['payment_tonpay_phase_label_review_timeout'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت پایان مهلت بررسی', "پایان مهلت بررسی");
        $d['payment_tonpay_phase_label_confirming'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت نهایی‌سازی', "تأیید شده و در حال نهایی‌سازی");
        $d['payment_tonpay_phase_label_paid'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت پرداخت‌شده', "پرداخت تأییدشده");
        $d['payment_tonpay_phase_label_rejected'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت ردشده', "رسید ردشده");
        $d['payment_tonpay_phase_label_canceled'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت لغوشده', "لغو‌شده");
        $d['payment_tonpay_phase_label_expired'] = self::def('msg_pay_tonpay', 'تون‌پی: برچسب وضعیت منقضی', "منقضی‌شده");
        $d['payment_tonpay_cancel_blocked_paid'] = self::def('msg_pay_tonpay', 'تون‌پی: لغو ممکن نیست (پرداخت‌شده)', "پرداخت شما در تون‌پی تأیید شده است و فاکتور قابل لغو نیست.");
        $d['payment_tonpay_cancel_blocked_remainder'] = self::def('msg_pay_tonpay', 'تون‌پی: لغو ممکن نیست (مبلغ باقی‌مانده)', "برای این فاکتور مبلغ باقی‌مانده در انتظار واریز است و تا تعیین وضعیت نهایی قابل لغو نیست.");
        $d['payment_tonpay_cancel_blocked_refund_card'] = self::def('msg_pay_tonpay', 'تون‌پی: لغو ممکن نیست (کارت عودت)', "برای این فاکتور باید کارت مقصد عودت مبلغ مازاد ثبت شود و فاکتور تا تعیین وضعیت نهایی قابل لغو نیست.");
        $d['payment_tonpay_cancel_blocked_processing'] = self::def('msg_pay_tonpay', 'تون‌پی: لغو ممکن نیست (در بررسی)', "پرداخت شما در حال بررسی تون‌پی است و تا مشخص شدن نتیجه امکان لغو فاکتور وجود ندارد.");
        $d['payment_tonpay_cancel_blocked_rejected'] = self::def('msg_pay_tonpay', 'تون‌پی: لغو ممکن نیست (رسید ردشده)', "رسید شما رد شده یا در حال پیگیری است و امکان لغو فاکتور وجود ندارد.");
        $d['payment_tonpay_cancel_blocked_unknown'] = self::def('msg_pay_tonpay', 'تون‌پی: لغو ممکن نیست (وضعیت نامشخص)', "وضعیت فاکتور هنوز از تون‌پی تأیید نشده است و امکان لغو آن وجود ندارد.");
        $d['payment_tonpay_cancel_blocked_not_pending'] = self::def('msg_pay_tonpay', 'تون‌پی: لغو ممکن نیست (غیر در انتظار)', "تنها فاکتورهای در انتظار پرداخت در تون‌پی قابل لغو هستند.");
        $d['payment_tonpay_cancel_blocked_generic'] = self::def('msg_pay_tonpay', 'تون‌پی: لغو ممکن نیست', "این فاکتور دیگر قابل لغو نیست.");
        $d['payment_tonpay_card_incomplete'] = self::def('msg_pay_tonpay', 'تون‌پی: شماره کارت ناقص', "⚠️ شماره کارت به‌صورت کامل دریافت نشد؛ لطفاً با پشتیبانی تماس بگیرید.");
        $d['payment_tonpay_remaining_card_incomplete'] = self::def('msg_pay_tonpay', 'تون‌پی: شماره کارت مابقی ناقص', "⚠️ شماره کارت مبلغ باقی‌مانده به‌صورت کامل دریافت نشد؛ لطفاً با پشتیبانی تماس بگیرید.");
        $d['payment_tonpay_refund_card_registered'] = self::def('msg_pay_tonpay', 'تون‌پی: کارت عودت قبلاً ثبت شده', "✅ کارت مقصد عودت مبلغ مازاد قبلاً ثبت شده است و مبلغ پس از بررسی تون‌پی واریز می‌شود.");
        $d['payment_tonpay_remaining_receipt_received'] = self::def('msg_pay_tonpay', 'تون‌پی: رسید مابقی قبلاً ارسال شده', "✅ رسید مبلغ باقی‌مانده قبلاً ارسال شده است و در انتظار بررسی تون‌پی است.");
        $d['payment_tonpay_line_invoice_notice'] = self::def('msg_pay_tonpay', 'تون‌پی: سطر شناسه فاکتور در اعلان', "🧾 شناسه فاکتور: <code>{invoice_id}</code>", ['invoice_id' => 'شناسه فاکتور']);
        $d['payment_tonpay_cancel_confirm'] = self::def('msg_pay_tonpay', 'تون‌پی: تأیید لغو تراکنش', "آیا از لغو این تراکنش اطمینان دارید؟\n\n⚠️ لغو فاکتور به‌معنای بازگشت وجه واریزشده نیست. اگر مبلغ را واریز کرده‌اید و تون‌پی آن را تأیید کند، پرداخت شما همچنان ثبت خواهد شد.");
        $d['payment_tonpay_refund_prompt'] = self::def('msg_pay_tonpay', 'تون‌پی: درخواست ثبت کارت عودت', "💳 ثبت کارت عودت مبلغ مازاد\n\nشماره کارت ۱۶ رقمی و نام صاحب کارت را در دو خط ارسال کنید:\n<code>6037991234567890</code>\n<code>نام و نام خانوادگی</code>\n\n⚠️ مبلغ مازاد پس از بررسی تون‌پی به این کارت واریز می‌شود و ثبت کارت به‌تنهایی به‌معنی واریز فوری نیست.");
        $d['payment_tonpay_line_remaining_amount'] = self::def('msg_pay_tonpay', 'تون‌پی: سطر مبلغ باقی‌مانده', "💸 مبلغ باقی‌مانده: <b>{amount}</b> تومان", ['amount' => 'مبلغ به تومان']);
        $d['payment_tonpay_line_remaining_card'] = self::def('msg_pay_tonpay', 'تون‌پی: سطر کارت مقصد مابقی', "💳 کارت مقصد مابقی: <code>{card_number}</code>", ['card_number' => 'شماره کارت']);
        $d['payment_tonpay_line_remaining_receipt'] = self::def('msg_pay_tonpay', 'تون‌پی: سطر وضعیت رسید مابقی', "🧾 رسید مابقی: {state}", ['state' => 'وضعیت رسید (ارسال شده / ارسال نشده)']);
        $d['payment_tonpay_state_receipt_sent'] = self::def('msg_pay_tonpay', 'تون‌پی: عبارت «ارسال شده»', "ارسال شده");
        $d['payment_tonpay_state_receipt_not_sent'] = self::def('msg_pay_tonpay', 'تون‌پی: عبارت «ارسال نشده»', "ارسال نشده");
        $d['payment_tonpay_line_refund_amount'] = self::def('msg_pay_tonpay', 'تون‌پی: سطر مبلغ مازاد قابل عودت', "💸 مبلغ مازاد قابل عودت: <b>{amount}</b> تومان", ['amount' => 'مبلغ به تومان']);
        $d['payment_tonpay_line_refund_card'] = self::def('msg_pay_tonpay', 'تون‌پی: سطر وضعیت کارت عودت', "💳 وضعیت کارت عودت: {state}", ['state' => 'وضعیت کارت عودت']);
        $d['payment_tonpay_state_refund_card_submitted'] = self::def('msg_pay_tonpay', 'تون‌پی: عبارت «ثبت شده و در انتظار واریز»', "ثبت شده و در انتظار واریز");
        $d['payment_tonpay_state_refund_card_missing'] = self::def('msg_pay_tonpay', 'تون‌پی: عبارت «ثبت نشده»', "ثبت نشده");
        $d['payment_tonpay_line_review_remaining'] = self::def('msg_pay_tonpay', 'تون‌پی: سطر زمان باقی‌مانده بررسی', "⏱ زمان باقی‌مانده بررسی: <b>{remaining}</b>", ['remaining' => 'زمان باقی‌مانده']);
        $d['payment_tonpay_line_receipt_wait'] = self::def('msg_pay_tonpay', 'تون‌پی: سطر زمان باقی‌مانده تا ارسال رسید', "⏱ زمان باقی‌مانده تا امکان ارسال رسید: <b>{remaining}</b>", ['remaining' => 'زمان باقی‌مانده']);
        $d['payment_tonpay_receipt_download_unavailable'] = self::def('msg_pay_tonpay', 'تون‌پی: دریافت فایل ممکن نیست', "دریافت فایل از تلگرام ممکن نیست.");
        $d['payment_tonpay_receipt_file_not_found'] = self::def('msg_pay_tonpay', 'تون‌پی: فایل رسید پیدا نشد', "فایل در تلگرام پیدا نشد.");
        $d['payment_tonpay_receipt_file_too_large'] = self::def('msg_pay_tonpay', 'تون‌پی: حجم رسید زیاد است', "حجم رسید نباید بیشتر از ۱۰ مگابایت باشد.");
        $d['payment_tonpay_receipt_temp_failed'] = self::def('msg_pay_tonpay', 'تون‌پی: ذخیرهٔ موقت فایل ممکن نیست', "ذخیره موقت فایل ممکن نیست.");
        $d['payment_tonpay_receipt_download_failed'] = self::def('msg_pay_tonpay', 'تون‌پی: دریافت فایل ناموفق', "دریافت فایل از تلگرام ناموفق بود.");
        $d['payment_tonpay_receipt_image_only'] = self::def('msg_pay_tonpay', 'تون‌پی: فقط تصویر رسید پذیرفته می‌شود', "فقط تصویر رسید پذیرفته می‌شود. لطفاً عکس رسید را ارسال کنید.");
        $d['payment_tonpay_receipt_photo_required'] = self::def('msg_pay_tonpay', 'تون‌پی: الزام ارسال عکس رسید', "لطفاً رسید را به‌صورت عکس ارسال کنید.");

        $d['payment_pending_list_title'] = self::def('msg_pay_pending', 'عنوان لیست تراکنش‌های در انتظار', "⏳ <b>تراکنش‌های در انتظار</b>");
        $d['payment_pending_list_empty_note'] = self::def('msg_pay_pending', 'متن نبود تراکنش در انتظار', "در حال حاضر تراکنش پرداخت نیمه‌تمامی ندارید.");
        $d['payment_pending_line_invoice'] = self::def('msg_pay_pending', 'سطر شناسه فاکتور', "🧾 شناسه فاکتور: <code>{invoice_id}</code>", ['invoice_id' => 'شناسه فاکتور']);
        $d['payment_pending_line_gateway'] = self::def('msg_pay_pending', 'سطر درگاه', "🏷 درگاه: {gateway}", ['gateway' => 'نام درگاه']);
        $d['payment_pending_line_kind'] = self::def('msg_pay_pending', 'سطر نوع تراکنش', "🛍 نوع تراکنش: {kind}", ['kind' => 'نوع تراکنش']);
        $d['payment_pending_line_amount'] = self::def('msg_pay_pending', 'سطر مبلغ', "💰 مبلغ: <b>{amount}</b> تومان", ['amount' => 'مبلغ به تومان']);
        $d['payment_pending_line_status'] = self::def('msg_pay_pending', 'سطر وضعیت', "📌 وضعیت: {status}", ['status' => 'برچسب وضعیت']);
        $d['payment_pending_line_created'] = self::def('msg_pay_pending', 'سطر تاریخ ایجاد', "📅 تاریخ ایجاد: {created_at}", ['created_at' => 'تاریخ ایجاد']);
        $d['payment_pending_line_card_holder'] = self::def('msg_pay_pending', 'سطر نام صاحب کارت', "👤 به نام: {card_holder}", ['card_holder' => 'نام صاحب کارت']);

        $d['payment_rialit_line_tracking'] = self::def('msg_pay_gateways', 'ریالیت: سطر کد پیگیری', "🛒 کد پیگیری: <code>{order_id}</code>", ['order_id' => 'کد پیگیری']);
        $d['payment_rialit_line_amount'] = self::def('msg_pay_gateways', 'ریالیت: سطر مبلغ سفارش', "💰 مبلغ سفارش: <b>{amount}</b> تومان", ['amount' => 'مبلغ به تومان']);
        $d['payment_tetraminator_line_tracking'] = self::def('msg_pay_gateways', 'تترامیناتور: سطر کد پیگیری', "🛒 کد پیگیری: <code>{order_id}</code>", ['order_id' => 'کد پیگیری']);
        $d['payment_tetraminator_line_amount'] = self::def('msg_pay_gateways', 'تترامیناتور: سطر مبلغ سفارش', "💰 مبلغ سفارش: <b>{amount}</b> تومان", ['amount' => 'مبلغ به تومان']);
        $d['payment_rialit_line_payer_amount'] =self::def('msg_pay_gateways', 'ریالیت: سطر مبلغ پرداختی با کارمزد', "💳 مبلغ پرداختی با کارمزد: <b>{amount}</b> تومان", ['amount' => 'مبلغ به تومان']);

        $d['payment_btn_view_details'] = self::def('msg_btn_payment', 'دکمه: مشاهده جزئیات و وضعیت', "🔍 مشاهده جزئیات و وضعیت");
        $d['payment_btn_support'] = self::def('msg_btn_payment', 'دکمه: پشتیبانی', "📞 پشتیبانی");
        $d['payment_btn_pending_list'] = self::def('msg_btn_payment', 'دکمه: تراکنش‌های در انتظار', "⏳ تراکنش‌های در انتظار");
        $d['payment_btn_cancel_transaction'] = self::def('msg_btn_payment', 'دکمه: لغو تراکنش', "❌ لغو تراکنش");
        $d['payment_btn_back_main'] = self::def('msg_btn_payment', 'دکمه: بازگشت به منوی اصلی', "🔙 بازگشت به منوی اصلی");
        $d['payment_btn_back'] = self::def('msg_btn_payment', 'دکمه: بازگشت', "🔙 بازگشت");
        $d['payment_btn_check_status'] = self::def('msg_btn_payment', 'دکمه: بررسی وضعیت پرداخت', "🔄 بررسی وضعیت پرداخت");
        $d['payment_btn_copy_card'] = self::def('msg_btn_payment', 'دکمه: کپی شماره کارت', "📋 کپی شماره کارت");
        $d['payment_btn_copy_amount'] = self::def('msg_btn_payment', 'دکمه: کپی مبلغ پرداخت', "📋 کپی مبلغ پرداخت");
        $d['payment_btn_view_payment_info'] = self::def('msg_btn_payment', 'دکمه: مشاهده اطلاعات پرداخت', "💳 مشاهده اطلاعات پرداخت");
        $d['payment_btn_cancel_confirm_yes'] = self::def('msg_btn_payment', 'دکمه: بله، لغو تراکنش', "✅ بله، لغو تراکنش");
        $d['payment_btn_copy_remaining_card'] = self::def('msg_btn_payment', 'دکمه: کپی کارت مابقی', "📋 کپی کارت مابقی");
        $d['payment_btn_copy_remaining_amount'] = self::def('msg_btn_payment', 'دکمه: کپی مبلغ مابقی', "📋 کپی مبلغ مابقی");
        $d['payment_btn_change_card'] = self::def('msg_btn_payment', 'دکمه: تعویض کارت', "🔁 تعویض کارت");
        $d['payment_btn_change_card_wait'] = self::def('msg_btn_payment', 'دکمه: تعویض کارت با زمان انتظار', "🔁 تعویض کارت ({seconds} ثانیه)", ['seconds' => 'ثانیه‌های باقی‌مانده تا فعال‌شدن']);
        $d['payment_btn_refund_card'] = self::def('msg_btn_payment', 'دکمه: ثبت کارت عودت مبلغ مازاد', "💳 ثبت کارت عودت مبلغ مازاد");
        $d['payment_btn_refund_card_short'] = self::def('msg_btn_payment', 'دکمه: ثبت کارت عودت (لیست)', "💳 ثبت کارت عودت");
        $d['payment_btn_send_receipt_remaining'] = self::def('msg_btn_payment', 'دکمه: ارسال رسید مابقی', "📤 ارسال رسید مابقی");
        $d['payment_btn_send_receipt_payment'] = self::def('msg_btn_payment', 'دکمه: ارسال رسید پرداخت', "📤 ارسال رسید پرداخت");
        $d['payment_btn_tetraminator_continue'] = self::def('msg_btn_payment', 'دکمه تترامیناتور: ادامه پرداخت', "💳 ادامه پرداخت");
        $d['payment_btn_tetraminator_check_status'] = self::def('msg_btn_payment', 'دکمه تترامیناتور: بررسی وضعیت', "🔄 بررسی وضعیت");
        $d['payment_btn_tetraminator_cancel_new'] = self::def('msg_btn_payment', 'دکمه تترامیناتور: انصراف و پرداخت جدید', "🚫 انصراف و پرداخت جدید");
        $d['payment_btn_tetraminator_pending_list'] = self::def('msg_btn_payment', 'دکمه تترامیناتور: تراکنش‌های در انتظار', "🔙 تراکنش‌های در انتظار");
        $d['payment_btn_tetraminator_follow'] = self::def('msg_btn_payment', 'دکمه تترامیناتور: پیگیری سفارش', "💎 پیگیری تترامیناتور · {order_id}", ['order_id' => 'کد پیگیری']);
        $d['payment_btn_tetraminator_confirm_abandon'] = self::def('msg_btn_payment', 'دکمه تترامیناتور: تأیید انصراف', "🚫 تأیید انصراف");
        $d['payment_btn_tetraminator_retry'] = self::def('msg_btn_payment', 'دکمه تترامیناتور: انتخاب روش پرداخت جدید', "💳 انتخاب روش پرداخت جدید");
        $d['payment_tetraminator_cancel_first_alert'] = self::def('msg_pay_followup', 'هشدار تترامیناتور: ابتدا انصراف دهید', "ابتدا از پرداخت قبلی انصراف دهید.");
        $d['payment_btn_rialit_continue'] =self::def('msg_btn_payment', 'دکمه ریالیت: ادامه پرداخت', "💳 ادامه پرداخت");
        $d['payment_btn_rialit_check_status'] = self::def('msg_btn_payment', 'دکمه ریالیت: بررسی وضعیت', "🔄 بررسی وضعیت");
        $d['payment_btn_rialit_cancel_new'] = self::def('msg_btn_payment', 'دکمه ریالیت: انصراف و پرداخت جدید', "🚫 انصراف و پرداخت جدید");
        $d['payment_btn_rialit_pending_list'] = self::def('msg_btn_payment', 'دکمه ریالیت: تراکنش‌های در انتظار', "🔙 تراکنش‌های در انتظار");
        $d['payment_btn_rialit_follow'] = self::def('msg_btn_payment', 'دکمه ریالیت: پیگیری سفارش', "💚 پیگیری ریالیت · {order_id}", ['order_id' => 'کد پیگیری']);

        $d['payment_invoice_not_found_alert'] = self::def('msg_pay_followup', 'هشدار: فاکتور پیدا نشد', "❌ این فاکتور پیدا نشد.");
        $d['payment_status_refreshed_alert'] = self::def('msg_pay_followup', 'هشدار: وضعیت به‌روزرسانی شد', "🔄 وضعیت به‌روزرسانی شد");
        $d['payment_transaction_canceled_alert'] = self::def('msg_pay_followup', 'هشدار: تراکنش لغو شد', "✅ تراکنش لغو شد");
        $d['payment_cancel_failed_alert'] = self::def('msg_pay_followup', 'هشدار: لغو فاکتور انجام نشد', "لغو فاکتور انجام نشد.");
        $d['payment_rialit_cancel_first_alert'] = self::def('msg_pay_followup', 'هشدار ریالیت: ابتدا انصراف دهید', "ابتدا از پرداخت قبلی انصراف دهید.");
        $d['payment_tonpay_refund_not_allowed_alert'] = self::def('msg_pay_followup', 'هشدار تون‌پی: ثبت کارت عودت مجاز نیست', "ثبت کارت عودت برای این فاکتور مجاز نیست.");
        $d['payment_tonpay_change_card_failed_alert'] = self::def('msg_pay_followup', 'هشدار تون‌پی: تعویض کارت انجام نشد', "تعویض کارت انجام نشد.");
        $d['payment_tonpay_card_changed_alert'] = self::def('msg_pay_followup', 'هشدار تون‌پی: کارت تعویض شد', "✅ کارت تعویض شد");
        $d['payment_tonpay_receipt_submit_failed_default'] = self::def('msg_pay_followup', 'تون‌پی: پیام پیش‌فرض ناموفق بودن ارسال رسید', "ارسال رسید ناموفق بود.");
        $d['payment_tonpay_refund_invalid_format'] = self::def('msg_pay_followup', 'تون‌پی: قالب نامعتبر اطلاعات کارت عودت', "❌ شماره کارت و نام صاحب کارت را در دو خط معتبر ارسال کنید.");
        $d['payment_tonpay_refund_submit_failed_default'] = self::def('msg_pay_followup', 'تون‌پی: پیام پیش‌فرض ناموفق بودن ثبت کارت عودت', "ثبت کارت عودت ناموفق بود.");
        $d['payment_tonpay_refund_registered_default'] = self::def('msg_pay_followup', 'تون‌پی: پیام پیش‌فرض ثبت کارت عودت', "کارت عودت ثبت شد.");
        $d['payment_btn_rialit_confirm_abandon'] = self::def('msg_btn_payment', 'دکمه ریالیت: تأیید انصراف', "🚫 تأیید انصراف");
        $d['payment_btn_rialit_retry'] = self::def('msg_btn_payment', 'دکمه ریالیت: انتخاب روش پرداخت جدید', "💳 انتخاب روش پرداخت جدید");
        $d['payment_btn_back_account'] = self::def('msg_btn_payment', 'دکمه: حساب کاربری', "🔙 حساب کاربری");
        $d['payment_btn_back_to_status'] = self::def('msg_btn_payment', 'دکمه: بازگشت به وضعیت پرداخت', "🔙 بازگشت به وضعیت پرداخت");

        $d['payment_crypto_hash_input_exited'] = self::def('msg_pay_crypto', 'خروج از ارسال هش (راهنمای دکمهٔ فاکتور)', "🏠 از حالت ارسال هش خارج شدید. هر زمان خواستید می‌توانید روی دکمه «📨 ارسال هش تراکنش» در فاکتور خودتان کلیک کنید.");
        $d['payment_crypto_hash_error_invalid'] = self::def('msg_pay_crypto', 'خطای هش: نامعتبر', "هش وارد شده معتبر نیست.");
        $d['payment_crypto_hash_error_already_used'] = self::def('msg_pay_crypto', 'خطای هش: قبلاً استفاده شده', "این هش قبلاً برای فاکتور دیگری ثبت شده است.");
        $d['payment_crypto_hash_error_order_not_pending'] = self::def('msg_pay_crypto', 'خطای هش: فاکتور قابل پرداخت نیست', "این فاکتور در وضعیت قابل پرداخت نیست.");
        $d['payment_crypto_hash_error_no_db'] = self::def('msg_pay_crypto', 'خطای هش: پایگاه داده', "خطای داخلی در پایگاه داده.");
        $d['payment_crypto_hash_error_db_update'] = self::def('msg_pay_crypto', 'خطای هش: ذخیره‌سازی', "خطای داخلی در ذخیره‌سازی.");
        $d['payment_crypto_hash_error_unknown'] = self::def('msg_pay_crypto', 'خطای هش: ناشناخته', "خطای ناشناخته");
        $d['payment_crypto_hash_saved_need_receipt'] = self::def('msg_pay_crypto', 'ثبت هش: نیاز به عکس رسید (شبکهٔ دستی)', "✅ هش تراکنش شما ثبت شد.\n\n⚠️ این شبکه به‌صورت خودکار بررسی نمی‌شود. برای ثبت درخواست بررسی توسط ادمین، حالا <b>عکس رسید تراکنش</b> را ارسال کنید (اجباری).\n\n🛒 کد فاکتور: <code>{order_id}</code>\n🔗 هش ثبت‌شده: <code>{hash}</code>", ['order_id' => 'کد فاکتور', 'hash' => 'هش تراکنش']);
        $d['payment_crypto_hash_saved_auto_check'] = self::def('msg_pay_crypto', 'ثبت هش: بررسی خودکار شبکه', "✅ هش تراکنش شما ثبت شد.\n\nربات هر <b>۱ دقیقه</b> یک بار به‌صورت خودکار شبکه را بررسی می‌کند:\n\n✅ اگر همه چیز درست باشد → معمولاً <b>۱ تا ۲ دقیقه</b> طول می‌کشد و موجودی شارژ می‌شود.\n❌ اگر مبلغ یا آدرس مقصد اشتباه باشد → <b>در همان دقیقه‌ی اول</b> پیام رد دریافت می‌کنید.\n⏰ اگر هش روی شبکه پیدا نشود → پس از <b>۳۰ دقیقه</b> فاکتور لغو می‌شود.\n\n🛒 کد فاکتور: <code>{order_id}</code>\n🔗 هش ثبت‌شده: <code>{hash}</code>", ['order_id' => 'کد فاکتور', 'hash' => 'هش تراکنش']);
        $d['payment_crypto_receipt_saved'] = self::def('msg_pay_crypto', 'ثبت عکس رسید ارز دیجیتال', "✅ عکس رسید ثبت شد.\n\nدرخواست شما برای ادمین ارسال شد. نتیجه از طریق همین چت اعلام خواهد شد.\n\n🛒 کد فاکتور: <code>{order_id}</code>", ['order_id' => 'کد فاکتور']);
        $d['common_btn_cancel'] = self::def('msg_btn_common', 'دکمه: انصراف', "❌ انصراف");

        $d['service_btn_renew'] = self::def('msg_btn_service', 'دکمه: تمدید سرویس', "♻️ تمدید سرویس");
        $d['service_btn_buy_new'] = self::def('msg_btn_service', 'دکمه: خرید سرویس جدید', "🛒 خرید سرویس جدید");
        $d['service_btn_confirm_disable'] = self::def('msg_btn_service', 'دکمه: تایید و غیرفعال‌کردن کانفیگ', "✅ تایید و غیرفعال کانفیگ");
        $d['service_btn_confirm_enable'] = self::def('msg_btn_service', 'دکمه: تایید و فعال‌کردن کانفیگ', "✅ تایید و فعال کانفیگ");
        $d['service_btn_copy_config'] = self::def('msg_btn_service', 'دکمه: کپی کانفیگ', "📋 کپی کانفیگ");
        $d['service_btn_config_info'] = self::def('msg_btn_service', 'دکمه: اطلاعات کانفیگ', "⚙️ اطلاعات کانفیگ");
        $d['service_select_squad'] = self::def('msg_service_manage', 'انتخاب سرور (Squad)', "🌍 یک سرور (Squad) انتخاب کنید:");
        $d['service_disable_confirm'] = self::def('msg_service_manage', 'تأیید خاموش‌کردن کانفیگ', "📌 با تایید گزینه زیر کانفیگ شما خاموش و دیگر امکان اتصال به کانفیگ وجود ندارد.\n⚠️ در صورتی که میخواهید مجدد کانفیگ فعال شود باید از بخش مدیریت سرویس دکمه <u>💡 روشن کردن اکانت</u> را کلیک کنید");
        $d['service_enable_confirm'] = self::def('msg_service_manage', 'تأیید روشن‌کردن کانفیگ', "📌 با تایید گزینه زیر کانفیگ شما روشن خواهد شد. و می توانید به کانفیگ خود متصل شوید\n⚠️ در صورتی که میخواهید مجدد کانفیگ غیرفعال شود باید از بخش مدیریت سرویس دکمه <u>❌ خاموش کردن اکانت</u>را کلیک کنید");
        $d['service_select_config_from_list'] = self::def('msg_service_manage', 'انتخاب کانفیگ از لیست', "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.");
        $d['service_config_file_fetch_failed_default'] = self::def('msg_service_manage', 'خطای پیش‌فرض دریافت فایل کانفیگ', "خطا در دریافت فایل کانفیگ؛ دوباره تلاش کنید.");

        $d['common_btn_back'] = self::def('msg_btn_common', 'دکمه: بازگشت (ساده)', "بازگشت");
        $d['common_btn_prev'] = self::def('msg_btn_common', 'دکمه: قبلی', "◀️ قبلی");
        $d['common_btn_next'] = self::def('msg_btn_common', 'دکمه: بعدی', "بعدی ▶️");
        $d['common_btn_close_list'] = self::def('msg_btn_common', 'دکمه: بستن لیست', "❌ بستن لیست");
        $d['common_btn_accept_rules'] = self::def('msg_btn_common', 'دکمه: پذیرش قوانین', "✅ قوانین را می پذیرم");
        $d['common_btn_send_phone'] = self::def('msg_btn_common', 'دکمه: ارسال شماره تلفن', "☎️ ارسال شماره تلفن");
        $d['common_btn_custom_amount'] = self::def('msg_btn_common', 'دکمه: رقم دلخواه', "✍️ رقم دلخواه");
        $d['common_btn_confirm_balance_transfer'] = self::def('msg_btn_common', 'دکمه: تایید و انتقال موجودی', "✅ تایید و انتقال");
        $d['wallet_btn_tx_24h'] = self::def('msg_btn_common', 'دکمه تراکنش‌ها: ۲۴ ساعت گذشته', "🕐 ۲۴ ساعت گذشته");
        $d['wallet_btn_tx_3d'] = self::def('msg_btn_common', 'دکمه تراکنش‌ها: ۳ روز گذشته', "📅 ۳ روز گذشته");
        $d['wallet_btn_tx_7d'] = self::def('msg_btn_common', 'دکمه تراکنش‌ها: ۷ روز گذشته', "🗓 ۷ روز گذشته");
        $d['wallet_btn_tx_30d'] = self::def('msg_btn_common', 'دکمه تراکنش‌ها: ۳۰ روز گذشته', "📆 ۳۰ روز گذشته");
        $d['referral_btn_gift'] = self::def('msg_btn_common', 'دکمه: هدیه عضویت', "🎁 هدیه عضویت");
        $d['referral_btn_share_link'] = self::def('msg_btn_common', 'دکمه: اشتراک لینک', "🔗 اشتراک لینک");
        $d['service_btn_pay_and_get'] = self::def('msg_btn_service', 'دکمه: پرداخت و دریافت سرویس', "💰 پرداخت و دریافت سرویس");
        $d['service_btn_discount_code'] = self::def('msg_btn_service', 'دکمه: ثبت کد تخفیف', "🎁 ثبت کد تخفیف");
        $d['service_btn_renew_current_plan'] = self::def('msg_btn_service', 'دکمه: تمدید پلن فعلی', "♻️ تمدید پلن فعلی");
        $d['service_btn_back_prev_menu'] = self::def('msg_btn_service', 'دکمه: بازگشت به منوی قبل', "▶️ بازگشت به منوی قبل");
        $d['service_btn_buy_subscription'] = self::def('msg_btn_service', 'دکمه: خرید اشتراک', "🛍خرید اشتراک");
        $d['service_btn_manage'] = self::def('msg_btn_service', 'دکمه: مدیریت سرویس', "🔧 مدیریت سرویس");
        $d['service_btn_usage_by_server'] = self::def('msg_btn_service', 'دکمه: مصرف به‌تفکیک سرور', "📊 مصرف به‌تفکیک سرور");
        $d['service_btn_choose_server'] = self::def('msg_btn_service', 'دکمه: انتخاب سرور', "🌍 انتخاب سرور");
        $d['service_btn_get_config'] = self::def('msg_btn_service', 'دکمه: دریافت کانفیگ (جزئیات سرویس)', "🔐 دریافت کانفیگ");
        $d['service_btn_get_sub_link'] = self::def('msg_btn_service', 'دکمه: دریافت لینک اشتراک', "🔗 دریافت لینک اشتراک");
        $d['service_btn_delete'] = self::def('msg_btn_service', 'دکمه: حذف سرویس', "❌ حذف سرویس");
        $d['service_btn_back_to_info'] = self::def('msg_btn_service', 'دکمه: بازگشت به اطلاعات سرویس', "🏠 بازگشت به اطلاعات سرویس");
        $d['service_btn_pick_current'] = self::def('msg_btn_service', 'دکمه: انتخاب سرویس فعلی', "📍 انتخاب سرویس فعلی");
        $d['service_btn_stock_back_to_list'] = self::def('msg_btn_service', 'دکمه انبار: بازگشت به لیست سرویس‌ها', "🏠 بازگشت به لیست سرویس ها");
        $d['service_btn_stock_extend'] = self::def('msg_btn_service', 'دکمه انبار: تمدید سرویس', "🔄 تمدید سرویس");
        $d['service_btn_stock_refund'] = self::def('msg_btn_service', 'دکمه انبار: بازگشت وجه', "💎 بازگشت وجه");
        $d['service_btn_stock_get_config'] = self::def('msg_btn_service', 'دکمه انبار: دریافت کانفیگ', "📥 دریافت کانفیگ");
        $d['service_btn_stock_sub_link'] = self::def('msg_btn_service', 'دکمه انبار: لینک اشتراک', "🔗 لینک اشتراک");
        $d['service_btn_request_delete_confirm'] = self::def('msg_btn_service', 'دکمه: درخواست حذف سرویس', "✅  درخواست حذف سرویس را دارم");
        $d['service_btn_confirm_transfer'] = self::def('msg_btn_service', 'دکمه: تایید انتقال سرویس', "✅ تایید انتقال سرویس");
        $d['service_btn_send_dispute_confirm'] = self::def('msg_btn_service', 'دکمه: تایید ارسال گزارش اختلال', "✅ ارسال گزارش اختلال");
        $d['service_btn_report_issue'] = self::def('msg_btn_service', 'دکمه: ارسال گزارش اختلال', "⚠️ ارسال گزارش اختلال");
        $d['service_btn_refresh_info'] = self::def('msg_btn_service', 'دکمه: بروزرسانی اطلاعات', "♻️ بروزرسانی اطلاعات");
        $d['service_btn_edit_note'] = self::def('msg_btn_service', 'دکمه: تغییر یادداشت', "📝 تغییر یادداشت");
        $d['config_btn_download'] = self::def('msg_btn_service', 'دکمه جدول کانفیگ: دریافت کانفیگ', "دریافت کانفیگ");
        $d['config_btn_header_action'] = self::def('msg_btn_service', 'سرستون جدول کانفیگ: کانفیگ', "⚙️ کانفیگ");
        $d['config_btn_header_protocol'] = self::def('msg_btn_service', 'سرستون جدول کانفیگ: پروتکل', "🔌 پروتکل");
        $d['config_btn_header_name'] = self::def('msg_btn_service', 'سرستون جدول کانفیگ: نام کانفیگ', "✏️نام کانفیگ");
        $d['config_btn_download_all'] = self::def('msg_btn_service', 'دکمه: دریافت همه کانفیگ‌ها', "⚙️ دریافت همه کانفیگ ها");

        $d['ticket_list_header'] = self::def('msg_support', 'سرتیتر لیست تیکت‌ها', "🎫 تیکت‌های پشتیبانی شما\n\nیک تیکت را برای ادامه انتخاب کنید یا تیکت جدید بسازید.");
        $d['ticket_miniapp_only_notice'] = self::def('msg_support', 'اعلان فقط‌مینی‌اپ بودن تیکت', "🎫 ارسال و پیگیری تیکت‌های پشتیبانی فقط از طریق مینی‌اپ امکان‌پذیر است.");
        $d['ticket_detail_header'] = self::def('msg_support', 'سرتیتر جزئیات تیکت', "🎫 تیکت <code>{ticket_id}</code>\nدپارتمان : {department}\nموضوع : {subject}\nوضعیت : {state}\n\n{lines}", ['ticket_id' => 'کد پیگیری تیکت', 'department' => 'نام دپارتمان', 'subject' => 'موضوع', 'state' => 'برچسب وضعیت (باز / بسته)', 'lines' => 'متن گفتگوی تیکت']);
        $d['ticket_who_support'] = self::def('msg_support', 'برچسب فرستنده: پشتیبانی', "👨‍💻 پشتیبانی");
        $d['ticket_who_user'] = self::def('msg_support', 'برچسب فرستنده: کاربر', "👤 شما");
        $d['ticket_media_placeholder'] = self::def('msg_support', 'جایگزین متن برای رسانه', "🖼 رسانه");
        $d['ticket_state_closed'] = self::def('msg_support', 'برچسب وضعیت: بسته', "🔒 بسته");
        $d['ticket_state_open'] = self::def('msg_support', 'برچسب وضعیت: باز', "🟢 باز");
        $d['ticket_btn_new'] = self::def('msg_support', 'دکمه: تیکت جدید', "➕ تیکت جدید");
        $d['ticket_btn_media'] = self::def('msg_support', 'دکمه: مشاهده رسانه‌ها', "📎 مشاهده رسانه‌ها");
        $d['ticket_btn_send_message'] = self::def('msg_support', 'دکمه: ارسال پیام', "✍️ ارسال پیام");
        $d['ticket_btn_close'] = self::def('msg_support', 'دکمه: بستن تیکت', "🔒 بستن تیکت");

        $d['service_info_line_connection'] = self::def('msg_service_info', 'سطر وضعیت اتصال', "📶 وضعیت اتصال: {status}", ['status' => 'وضعیت اتصال (آنلاین / آفلاین)']);
        $d['service_info_online'] = self::def('msg_service_info', 'عبارت آنلاین', "🟢 آنلاین");
        $d['service_info_offline'] = self::def('msg_service_info', 'عبارت آفلاین', "🔴 آفلاین");
        $d['service_info_offline_gray'] = self::def('msg_service_info', 'عبارت آفلاین (خاکستری)', "⚪ آفلاین");
        $d['service_info_line_active_server'] = self::def('msg_service_info', 'سطر سرور فعال', "🌍 سرور فعال: {server}", ['server' => 'نام سرور']);
        $d['service_info_line_user_limit'] = self::def('msg_service_info', 'سطر تعداد کاربر مجاز', "👥 تعداد کاربر مجاز: {limit}", ['limit' => 'تعداد کاربر مجاز']);
        $d['service_info_user_count'] = self::def('msg_service_info', 'عبارت تعداد کاربر', "{count} کاربر", ['count' => 'تعداد']);
        $d['service_unlimited'] = self::def('msg_service_info', 'عبارت نامحدود', "نامحدود");
        $d['service_info_line_ip_count'] = self::def('msg_service_info', 'سطر تعداد IP مشاهده‌شده', "🌐 تعداد IP مشاهده‌شده: {count}", ['count' => 'تعداد']);
        $d['service_info_line_connected_ip'] = self::def('msg_service_info', 'سطر IP متصل', "📍 IP متصل: {ip}", ['ip' => 'آدرس IP']);
        $d['service_info_ip_unavailable'] = self::def('msg_service_info', 'اطلاعات IP در دسترس نیست', "🌐 اطلاعات IP متصل در حال حاضر در دسترس نیست.");
        $d['service_info_line_devices'] = self::def('msg_service_info', 'سطر دستگاه‌های متصل', "👥 دستگاه‌های متصل: {count} از {limit} دستگاه", ['count' => 'تعداد دستگاه متصل', 'limit' => 'سقف مجاز دستگاه']);
        $d['service_info_line_device'] = self::def('msg_service_info', 'سطر دستگاه', "📱 دستگاه: {device}{last_seen}", ['device' => 'نام دستگاه', 'last_seen' => 'آخرین اتصال']);
        $d['service_info_device_last_seen'] = self::def('msg_service_info', 'عبارت آخرین اتصال دستگاه', " — آخرین اتصال: {time}", ['time' => 'زمان آخرین اتصال']);
        $d['service_info_device_unknown'] = self::def('msg_service_info', 'عبارت دستگاه نامشخص', "نامشخص");
        $d['service_info_connect_sub'] = self::def('msg_service_info', 'بلوک اتصال و آپدیت لینک', "\n📶 اخرین زمان اتصال  : {last_online}\n🔄 اخرین زمان آپدیت لینک اشتراک  : {last_update}{client_line}", ['last_online' => 'آخرین زمان اتصال', 'last_update' => 'آخرین آپدیت لینک اشتراک', 'client_line' => 'سطر کلاینت متصل']);
        $d['service_info_connect_simple'] = self::def('msg_service_info', 'بلوک آخرین اتصال', "📶 اخرین زمان اتصال شما : {last_online}", ['last_online' => 'آخرین زمان اتصال']);
        $d['service_info_client_line'] = self::def('msg_service_info', 'سطر کلاینت متصل', "\n#️⃣ کلاینت متصل شده :<code>{client}</code>", ['client' => 'نام کلاینت']);
        $d['service_info_changelink_hint'] = self::def('msg_service_info', 'راهنمای تغییر لینک', "💡 برای قطع دسترسی دیگران کافیست روی گزینه \"تغییر لینک\" کلیک کنید.");
        $d['service_not_found_alert'] = self::def('msg_service_info', 'هشدار: سرویس پیدا نشد', "❌ سرویس پیدا نشد");
        $d['service_info_refreshed_alert'] = self::def('msg_service_info', 'هشدار: اطلاعات بروز شد', "♻️ اطلاعات بروز شد");
        $d['service_btn_account_off'] = self::def('msg_btn_service', 'دکمه: خاموش کردن اکانت', "❌ خاموش کردن اکانت");
        $d['service_btn_account_on'] = self::def('msg_btn_service', 'دکمه: روشن کردن اکانت', "💡 روشن کردن اکانت");

        $cryptoInvoiceVars = ['order_id' => 'کد فاکتور', 'currency' => 'ارز', 'network' => 'شبکه', 'amount_irt' => 'مبلغ قابل پرداخت به تومان', 'amount' => 'مقدار دقیق ارز', 'wallet' => 'آدرس کیف پول مقصد', 'memo_block' => 'بلوک ممو (در صورت نیاز)', 'expire_min' => 'مدت اعتبار به دقیقه', 'explorer_hint' => 'راهنمای لینک اکسپلورر'];
        $d['payment_crypto_invoice_manual'] = self::def('msg_pay_crypto', 'فاکتور کریپتو: بررسی دستی', "🛠 <b>فاکتور پرداخت کریپتو (بررسی دستی)</b>\n\n🛒 کد فاکتور: <code>{order_id}</code>\n💎 ارز: <b>{currency}</b>  •  🌐 شبکه: <b>{network}</b>\n💸 مبلغ قابل پرداخت: {amount_irt} تومان\n\n🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{amount}</code>\n(معادل {amount_irt} تومان)\n\n📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{wallet}</blockquote>\n\n{memo_block}❗️ <b>دقیقاً همین مقدار</b> را ارسال کنید. تفاوت در ارقام باعث می‌شود ربات تراکنش شما را شناسایی نکند.\n⏰ مدت اعتبار: حدود {expire_min} دقیقه.\n\n⚠️ <b>این شبکه به‌صورت خودکار بررسی نمی‌شود.</b> بعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید، هش/لینک تراکنش را ارسال کنید و سپس <b>عکس رسید تراکنش را نیز ارسال کنید</b> — بدون عکس رسید، درخواست شما برای ادمین ثبت نمی‌شود.\n{explorer_hint}", $cryptoInvoiceVars);
        $d['payment_crypto_invoice_auto'] = self::def('msg_pay_crypto', 'فاکتور کریپتو: بررسی خودکار', "💎 <b>فاکتور پرداخت کریپتو</b>\n\n🛒 کد فاکتور: <code>{order_id}</code>\n💎 ارز: <b>{currency}</b>  •  🌐 شبکه: <b>{network}</b>\n💸 مبلغ قابل پرداخت: {amount_irt} تومان\n\n🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{amount}</code>\n(معادل {amount_irt} تومان)\n\n📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{wallet}</blockquote>\n\n{memo_block}❗️ <b>دقیقاً همین مقدار</b> را ارسال کنید. تفاوت در ارقام باعث می‌شود ربات تراکنش شما را شناسایی نکند.\n⏰ مدت اعتبار: حدود {expire_min} دقیقه.\n\nبعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید و هش/لینک تراکنش را ارسال کنید.\n{explorer_hint}", $cryptoInvoiceVars);
        $d['payment_crypto_memo_block'] = self::def('msg_pay_crypto', 'فاکتور کریپتو: بلوک ممو', "🏷 <b>ممو (Memo / Comment) — اجباری:</b>\n<code>{memo}</code>\n⚠️ <b>بدون ممو، تراکنش شما به فاکتور وصل نمی‌شود.</b>\n\n", ['memo' => 'مقدار ممو']);
        $d['payment_crypto_hint_manual_network'] = self::def('msg_pay_crypto', 'فاکتور کریپتو: راهنمای شبکهٔ دستی', "<i>(این شبکه به‌صورت خودکار بررسی نمی‌شود؛ هش تراکنش پس از ارسال توسط ادمین بررسی خواهد شد)</i>");
        $d['payment_crypto_hint_tronscan'] = self::def('msg_pay_crypto', 'فاکتور کریپتو: راهنمای Tronscan', "<i>(لینک‌های Tronscan قابل قبول است)</i>");
        $d['payment_crypto_hint_tonviewer'] = self::def('msg_pay_crypto', 'فاکتور کریپتو: راهنمای Tonviewer', "<i>(لینک‌های Tonviewer / Tonscan قابل قبول است)</i>");
        $d['payment_crypto_create_error_currency'] = self::def('msg_pay_crypto', 'خطای ساخت فاکتور: ارز غیرفعال', "این ارز فعال نیست.");
        $d['payment_crypto_create_error_wallet'] = self::def('msg_pay_crypto', 'خطای ساخت فاکتور: کیف پول تنظیم نشده', "آدرس کیف پول این ارز توسط ادمین تنظیم نشده است.");
        $d['payment_crypto_create_error_below_min'] = self::def('msg_pay_crypto', 'خطای ساخت فاکتور: کمتر از حداقل', "مبلغ کمتر از حداقل مجاز است.");
        $d['payment_crypto_create_error_above_max'] = self::def('msg_pay_crypto', 'خطای ساخت فاکتور: بیشتر از حداکثر', "مبلغ بیشتر از حداکثر مجاز است.");
        $d['payment_crypto_create_error_rate'] = self::def('msg_pay_crypto', 'خطای ساخت فاکتور: نرخ در دسترس نیست', "دریافت نرخ لحظه‌ای ممکن نبود؛ لطفاً دقایقی دیگر تلاش کنید.");
        $d['payment_crypto_create_error_db'] = self::def('msg_pay_crypto', 'خطای ساخت فاکتور: ثبت ناموفق', "خطای داخلی در ثبت فاکتور.");
        $d['payment_btn_crypto_copy_wallet'] = self::def('msg_btn_payment', 'دکمه کریپتو: کپی آدرس ولت', "🔗 کپی آدرس ولت");
        $d['payment_btn_crypto_copy_amount'] = self::def('msg_btn_payment', 'دکمه کریپتو: کپی مقدار', "🪙 کپی مقدار");
        $d['payment_btn_crypto_paid_submit'] = self::def('msg_btn_payment', 'دکمه کریپتو: پرداخت کردم و ارسال هش', "✅ پرداخت کردم | ارسال هش (TXID) 🧾");
        $d['payment_btn_crypto_copy_memo'] = self::def('msg_btn_payment', 'دکمه کریپتو: کپی ممو', "🏷 کپی ممو");
        $d['payment_btn_crypto_invoice_back'] = self::def('msg_btn_payment', 'دکمه کریپتو: بازگشت و لغو فاکتور', "🔙 بازگشت (لغو فاکتور)");
        $gwVars = ['tracking_code' => 'کد پیگیری', 'amount' => 'مبلغ تراکنش به تومان'];
        $d['payment_invoice_created_tronado'] = self::def('msg_pay_invoice', 'ترونادو: پیام ایجاد تراکنش', "✅ تراکنش شما ایجاد شد\n\n🛒 کد پیگیری:  <code>{tracking_code}</code>\n💲 مبلغ تراکنش به تومان  : <code>{amount}</code>\n\n💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇\n\n🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .\n❌ پس از تراکنش 15 تا یک ساعت زمان میبرد تا تراکنش تایید شود\n\n✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید", $gwVars);
        $d['payment_invoice_created_variza'] = self::def('msg_pay_invoice', 'واریزا: پیام ایجاد تراکنش', "✅ تراکنش شما ایجاد شد\n\n🛒 کد پیگیری:  <code>{tracking_code}</code>\n💲 مبلغ تراکنش به تومان  : <code>{amount}</code>\n\n💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇\n\n🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .\n\n✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید", $gwVars);
        $d['payment_invoice_created_blupal'] = self::def('msg_pay_invoice', 'بلوپال: پیام ایجاد تراکنش', "✅ تراکنش شما ایجاد شد\n\n🛒 کد پیگیری:  <code>{tracking_code}</code>\n💲 مبلغ تراکنش به تومان  : <code>{amount}</code>\n\n💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇\n\n🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .\n\n✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید", $gwVars);
        $d['payment_invoice_created_tonpay'] = self::def('msg_pay_invoice', 'تون‌پی: پیام ایجاد تراکنش', "✅ تراکنش شما ایجاد شد\n\n🛒 کد پیگیری:  <code>{tracking_code}</code>\n💲 مبلغ تراکنش به تومان  : <code>{amount}</code>\n\n💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇\n\n🔹 تراکنش تا ۲۴ ساعت اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .\n\n✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید", $gwVars);
        $d['payment_invoice_created_atlaspay'] = self::def('msg_pay_invoice', 'اطلس‌پی: پیام ایجاد تراکنش', "✅ تراکنش شما ایجاد شد\n\n🛒 کد پیگیری:  <code>{tracking_code}</code>\n💲 مبلغ تراکنش به تومان  : <code>{amount}</code>\n\n💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇\n\n🔹 تراکنش تا ۲۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .{receipt_block}\n\n✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید", $gwVars + ['receipt_block' => 'راهنمای ارسال رسید (در صورت وجود)']);
        $d['payment_invoice_created_rialit'] = self::def('msg_pay_invoice', 'ریالیت: پیام ایجاد تراکنش', "✅ تراکنش شما ایجاد شد\n\n🛒 کد پیگیری:  <code>{tracking_code}</code>\n💲 مبلغ تراکنش به تومان  : <code>{amount}</code>\n\n💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇\n\n🔹 لینک پرداخت تا ۳۰ دقیقه باز است. اگر پس از پایان این مهلت، پرداخت هنوز در انتظار باشد، نتیجه از سمت ریالیت بررسی و در صورت تأیید به‌صورت خودکار اعمال می‌شود.\n\n✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید", $gwVars);
        $d['payment_invoice_created_tetraminator'] = self::def('msg_pay_invoice', 'تترامیناتور: پیام ایجاد تراکنش', "✅ تراکنش شما ایجاد شد\n\n🛒 کد پیگیری:  <code>{tracking_code}</code>\n💲 مبلغ تراکنش به تومان  : <code>{amount}</code>\n\n💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇\n\n🔹 لینک پرداخت تا ۶۰ دقیقه باز است. پس از پرداخت، تأیید به‌صورت خودکار انجام می‌شود و نیازی به ارسال رسید نیست.\n\n✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید", $gwVars);
        $cryptoGwVars = ['order_id' => 'شماره فاکتور', 'price' => 'مبلغ فاکتور به تومان', 'usd_rate' => 'قیمت دلار به تومان'];
        $d['payment_invoice_created_plisio'] = self::def('msg_pay_invoice', 'Plisio: پیام ایجاد فاکتور', "<b>💲 جهت افزایش اعتبار کیف پول خود از طریق ارز دیجیتال روی دکمه پرداخت در انتهای پیام کلیک کنید</b>\n\n⚠️ توجه:  زمان پرداخت 30 دقیقه می باشد پس از 30 دقیقه تراکنش لغو خواهد شد\n\n🌐 برخی از سایت های داخلی جهت خرید ارز دیجیتال 👇\n🔸 nikpardakht.com\n🔹 webpurse.org\n🔸 bitpin.ir\n🔹 sarmayex.com\n🔸 ok-ex.io\n🔹 nobitex.ir\n🔸 bitbarg.com\n🔹 cafearz.com\n🔸 pay98.app\n🔢 شماره فاکتور : {order_id}\n💰 مبلغ فاکتور : {price} تومان\n📊 قیمت دلار: {usd_rate} تومان تا این لحظه\n\nجهت پرداخت از دکمه زیر استفاده👇🏻", $cryptoGwVars);
        $d['payment_invoice_created_nowpayments'] = self::def('msg_pay_invoice', 'NowPayments: پیام ایجاد فاکتور', "<b>💲 جهت افزایش اعتبار کیف پول خود از طریق ارز دیجیتال روی دکمه پرداخت در انتهای پیام کلیک کنید</b>\n\n⚠️ توجه:  زمان پرداخت 30 دقیقه می باشد پس از 30 دقیقه تراکنش لغو خواهد شد\n\n🌐 برخی از سایت های داخلی جهت خرید ارز دیجیتال 👇\n🔸 nikpardakht.com\n🔹 webpurse.org\n🔸 bitpin.ir\n🔹 sarmayex.com\n🔸 ok-ex.io\n🔹 nobitex.ir\n🔸 bitbarg.com\n🔹 cafearz.com\n🔸 pay98.app\n🔢 شماره فاکتور : {order_id}\n💰 مبلغ فاکتور : {price} تومان\n📊 قیمت دلار: {usd_rate} تومان تا این لحظه\n\n<blockquote>⚠️ پس از پرداخت، در صورتی که مبلغ تراکنش به‌درستی واریز شده باشد، موجودی شما حداکثر تا ۱۵ دقیقه آینده به‌صورت خودکار شارژ خواهد شد.</blockquote>\n\nجهت پرداخت از دکمه زیر استفاده👇🏻", $cryptoGwVars);
        $d['payment_invoice_created_zarinpal'] = self::def('msg_pay_invoice', 'زرین‌پال: پیام ایجاد فاکتور', "✅ فاکتور پرداخت ایجاد شد.\n\n🔢 شماره فاکتور : {order_id}\n💰 مبلغ فاکتور : {price} تومان\n\n❌ این تراکنش به مدت ۳۰ دقیقه اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.\n\n📌لطفاً پس از پرداخت و موفق بودن تراکنش ، کمی صبر کنید تا پیام پرداخت موفق در سایت ما دریافت کنید. در غیراینصورت اکانت شما شارژ نخواهد شد.\n\nجهت پرداخت از دکمه زیر استفاده کنید👇🏻", ['order_id' => 'شماره فاکتور', 'price' => 'مبلغ فاکتور به تومان']);

        $d['service_transfer_received_notice'] = self::def('msg_service_manage', 'اعلان دریافت سرویس منتقل‌شده', "✅ کاربر گرامی  سرویس با نام کاربری {username} از طرف کاربر با شناسه کاربری {from_id}  به حساب کاربری شما منتقل گردید.", ['username' => 'نام کاربری سرویس', 'from_id' => 'شناسه کاربر فرستنده']);
        $d['service_dispute_ask_reason'] = self::def('msg_service_manage', 'درخواست علت اختلال', "❓ علت اختلال خود را بنویسید\n\n🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )");
        $d['service_dispute_confirm'] = self::def('msg_service_manage', 'تأیید ارسال گزارش اختلال', "❓ آیا از ارسال گزارش اختلال اطمینان دارید\n\n🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )");
        $d['wallet_tx_prompt'] = self::def('msg_wallet_tx', 'درخواست انتخاب بازهٔ تراکنش‌ها', "📑 تراکنش‌های من\n\nبازه‌ی زمانی مورد نظر را برای مشاهده‌ی تراکنش‌های کیف پول خود انتخاب کنید:");
        $d['wallet_tx_range_24h'] = self::def('msg_wallet_tx', 'عنوان بازه: ۲۴ ساعت گذشته', "۲۴ ساعت گذشته");
        $d['wallet_tx_range_3d'] = self::def('msg_wallet_tx', 'عنوان بازه: ۳ روز گذشته', "۳ روز گذشته");
        $d['wallet_tx_range_7d'] = self::def('msg_wallet_tx', 'عنوان بازه: ۷ روز گذشته', "۷ روز گذشته");
        $d['wallet_tx_range_30d'] = self::def('msg_wallet_tx', 'عنوان بازه: ۳۰ روز گذشته', "۳۰ روز گذشته");
        $d['wallet_tx_cat_topup_card'] = self::def('msg_wallet_tx', 'دسته تراکنش: شارژ کارت به کارت', "شارژ کارت به کارت");
        $d['wallet_tx_cat_topup_crypto'] = self::def('msg_wallet_tx', 'دسته تراکنش: شارژ ارز دیجیتال', "شارژ ارز دیجیتال");
        $d['wallet_tx_cat_topup_gateway'] = self::def('msg_wallet_tx', 'دسته تراکنش: شارژ درگاه پرداخت', "شارژ درگاه پرداخت");
        $d['wallet_tx_cat_admin_credit'] = self::def('msg_wallet_tx', 'دسته تراکنش: افزایش موجودی توسط ادمین', "افزایش موجودی توسط ادمین");
        $d['wallet_tx_cat_admin_debit'] = self::def('msg_wallet_tx', 'دسته تراکنش: کاهش موجودی توسط ادمین', "کاهش موجودی توسط ادمین");
        $d['wallet_tx_cat_purchase'] = self::def('msg_wallet_tx', 'دسته تراکنش: خرید سرویس', "خرید سرویس");
        $d['wallet_tx_cat_refund'] = self::def('msg_wallet_tx', 'دسته تراکنش: بازگشت وجه', "بازگشت وجه");
        $d['wallet_tx_cat_affiliate_commission'] = self::def('msg_wallet_tx', 'دسته تراکنش: پورسانت زیرمجموعه', "پورسانت زیرمجموعه");
        $d['wallet_tx_cat_gift_code'] = self::def('msg_wallet_tx', 'دسته تراکنش: کد هدیه', "کد هدیه");
        $d['wallet_tx_cat_lottery'] = self::def('msg_wallet_tx', 'دسته تراکنش: جایزه قرعه‌کشی', "جایزه قرعه‌کشی");
        $d['wallet_tx_cat_cashback'] = self::def('msg_wallet_tx', 'دسته تراکنش: هدیه بازگشت وجه', "هدیه بازگشت وجه");
        $d['wallet_tx_cat_service_action'] = self::def('msg_wallet_tx', 'دسته تراکنش: عملیات سرویس', "عملیات سرویس");
        $d['wallet_tx_cat_transfer_out'] = self::def('msg_wallet_tx', 'دسته تراکنش: انتقال موجودی به کاربر دیگر', "انتقال موجودی به کاربر دیگر");
        $d['wallet_tx_cat_transfer_in'] = self::def('msg_wallet_tx', 'دسته تراکنش: انتقال موجودی از کاربر دیگر', "انتقال موجودی از کاربر دیگر");
        $d['wallet_tx_cat_referral_gift'] = self::def('msg_wallet_tx', 'دسته تراکنش: هدیه عضویت زیرمجموعه', "هدیه عضویت زیرمجموعه");
        $d['wallet_tx_empty'] = self::def('msg_wallet_tx', 'نبود تراکنش در بازه', "📑 تراکنش‌های من ({range})\n\nدر این بازه‌ی زمانی، تراکنشی یافت نشد.", ['range' => 'عنوان بازهٔ زمانی']);
        $d['wallet_tx_line'] = self::def('msg_wallet_tx', 'قالب هر سطر تراکنش', "{sign} {amount} تومان — {label}\n🕐 {date}", ['sign' => 'علامت (➕ / ➖)', 'amount' => 'مبلغ', 'label' => 'عنوان دسته', 'date' => 'تاریخ']);
        $d['wallet_tx_list'] = self::def('msg_wallet_tx', 'قالب فهرست تراکنش‌ها', "📑 تراکنش‌های من ({range})\n\n{lines}{page_info}", ['range' => 'عنوان بازهٔ زمانی', 'lines' => 'سطرهای تراکنش', 'page_info' => 'اطلاعات صفحه‌بندی']);
        $d['wallet_tx_page_info'] = self::def('msg_wallet_tx', 'عبارت شماره صفحه', "\n\n📄 صفحه {page} از {total}", ['page' => 'صفحهٔ فعلی', 'total' => 'تعداد کل صفحات']);

        $d['account_phone_missing'] = self::def('msg_account', 'حساب کاربری: شماره ارسال نشده', "🔴 ارسال نشده است 🔴");
        $d['account_phone_admin_confirmed'] = self::def('msg_account', 'حساب کاربری: شماره تأییدشده توسط ادمین', "✅ تایید شده توسط ادمین");
        $d['account_group_normal'] = self::def('msg_account', 'حساب کاربری: گروه عادی', "عادی");
        $d['account_group_agent'] = self::def('msg_account', 'حساب کاربری: گروه نماینده', "نماینده");
        $d['account_group_agent_advanced'] = self::def('msg_account', 'حساب کاربری: گروه نمایندگی پیشرفته', "نمایندگی پیشرفته");
        $d['account_score_line'] = self::def('msg_account', 'حساب کاربری: سطر امتیاز', "🥅 امتیاز حساب کاربری شما : {score}", ['score' => 'امتیاز کاربر']);
        $d['account_referral_verify_line'] = self::def('msg_account', 'حساب کاربری: لینک ریفرال جهت احراز', "\n\n🔗 لینک ریفرال جهت احراز زیر مجموعه :\nhttps://t.me/{bot}?start={code}", ['bot' => 'نام کاربری ربات', 'code' => 'کد دعوت کاربر']);

        $d['service_renewal_success'] = self::def('msg_service_renew', 'تمدید موفق: خلاصهٔ تمدید', "✅ تمدید برای سرویس شما با موفقیت صورت گرفت\n\n▫️نام سرویس : {service}\n▫️نام محصول : {product}\n▫️مبلغ تمدید {price} تومان\n", ['service' => 'نام کاربری سرویس', 'product' => 'نام محصول', 'price' => 'مبلغ تمدید']);
        $d['service_renewal_queued_summary'] = self::def('msg_service_renew', 'تمدید در صف: خلاصهٔ تمدید', "{confirmation}\n\n▫️نام سرویس : {service}\n▫️نام محصول : {product}\n▫️مبلغ تمدید {price} تومان\n", ['confirmation' => 'متن تأیید ثبت در صف', 'service' => 'نام کاربری سرویس', 'product' => 'نام محصول', 'price' => 'مبلغ تمدید']);
        $d['service_renewal_restart_error'] = self::def('msg_service_renew', 'خطا: مراحل را از اول طی کنید', "❌ خطایی رخ داده است مراحل را از اول طی کنید");
        $d['service_location_change_confirm'] = self::def('msg_service_manage', 'تأیید تغییر موقعیت سرویس', "📍 با  تایید کردن انتقال موقعیت سرویس شما در این موقعیت حذف و به موقعیت جدید منتقل خواهد شد.\n💰 هزینه انتقال {price} تومان می باشد\n📌 محدودیت باقی مانده شما : {limit_left} عدد (تعداد محدودیت رایگان باقی مانده :‌{free_left} عدد)\n\n✅ برای تایید انتقال روی دکمه زیر کلیک کنید", ['price' => 'هزینهٔ انتقال', 'limit_left' => 'محدودیت باقی‌مانده', 'free_left' => 'تعداد رایگان باقی‌مانده']);
        $d['service_location_change_success'] = self::def('msg_service_manage', 'تغییر موقعیت موفق', "✅ کانفیگ شما باموفقیت به سرور ({panel}) انتقال یافت.\n\n🖥 نام سرویس : {username}\n💠 حجم سرویس : {volume}\n⏳ زمان انقضا :  {expire} | {days_left}\n\n🔗 لینک اشتراک شما:\n\n<code>{link}</code>", ['panel' => 'نام سرور جدید', 'username' => 'نام سرویس', 'volume' => 'حجم باقی‌مانده', 'expire' => 'تاریخ انقضا', 'days_left' => 'روزهای باقی‌مانده', 'link' => 'لینک اشتراک']);

        $d['wallet_transfer_invalid_id'] = self::def('msg_wallet_tx', 'انتقال موجودی: آیدی نامعتبر', "❌ آیدی عددی نامعتبر است. آیدی عددی تلگرام کاربر مقصد را مجددا ارسال کنید:");
        $d['wallet_transfer_self'] = self::def('msg_wallet_tx', 'انتقال موجودی: انتقال به خود', "❌ نمی‌توانید به خودتان موجودی انتقال دهید. آیدی عددی دیگری ارسال کنید:");
        $d['wallet_transfer_recipient_not_found'] = self::def('msg_wallet_tx', 'انتقال موجودی: گیرنده یافت نشد', "❌ کاربری با این آیدی یافت نشد. آیدی عددی تلگرام کاربر مقصد را مجددا ارسال کنید:");
        $d['wallet_transfer_ask_amount'] = self::def('msg_wallet_tx', 'انتقال موجودی: درخواست مبلغ', "💸 مبلغ مورد نظر برای انتقال را به تومان وارد کنید:\n✅ حداقل مبلغ {min} تومان می‌باشد", ['min' => 'حداقل مبلغ انتقال']);
        $d['wallet_transfer_amount_invalid'] = self::def('msg_wallet_tx', 'انتقال موجودی: مبلغ نامعتبر', "❌ مبلغ نامعتبر است. مبلغ را به تومان و به صورت عدد ارسال کنید:");
        $d['wallet_transfer_amount_below_min'] = self::def('msg_wallet_tx', 'انتقال موجودی: کمتر از حداقل', "❌ مبلغ باید حداقل {min} تومان باشد. مجددا ارسال کنید:", ['min' => 'حداقل مبلغ انتقال']);
        $d['wallet_transfer_insufficient_balance'] = self::def('msg_wallet_tx', 'انتقال موجودی: موجودی ناکافی', "❌ موجودی کیف پول شما کافی نیست.");
        $d['wallet_transfer_recipient_invalid_canceled'] = self::def('msg_wallet_tx', 'انتقال موجودی: گیرنده نامعتبر و لغو', "❌ کاربر مقصد دیگر معتبر نیست. عملیات لغو شد.");
        $d['wallet_transfer_confirm'] = self::def('msg_wallet_tx', 'انتقال موجودی: تأیید', "🔄 تایید انتقال موجودی\n\n👤 گیرنده: {recipient}\n💰 مبلغ: {amount} تومان\n\nآیا از انجام این تراکنش اطمینان دارید؟", ['recipient' => 'گیرنده', 'amount' => 'مبلغ']);
        $d['wallet_transfer_operation_invalid'] = self::def('msg_wallet_tx', 'انتقال موجودی: اطلاعات تراکنش نامعتبر', "❌ اطلاعات تراکنش نامعتبر است. مجددا تلاش کنید.");
        $d['wallet_transfer_incomplete_contact_support'] = self::def('msg_wallet_tx', 'انتقال موجودی: تکمیل‌نشده', "❌ انتقال تکمیل نشد؛ با شناسه {operation_id} با پشتیبانی در ارتباط باشید.", ['operation_id' => 'شناسه عملیات']);
        $d['wallet_transfer_success'] = self::def('msg_wallet_tx', 'انتقال موجودی: موفق (برای فرستنده)', "✅ انتقال موجودی با موفقیت انجام شد.\n\n💰 مبلغ: {amount} تومان\n👤 گیرنده: {recipient}\n🧾 شناسه انتقال: <code>{operation_id}</code>", ['amount' => 'مبلغ', 'recipient' => 'گیرنده', 'operation_id' => 'شناسه انتقال']);
        $d['wallet_transfer_sender_username_line'] = self::def('msg_wallet_tx', 'انتقال موجودی: سطر نام کاربری فرستنده', "نام کاربری: @{username}\n", ['username' => 'نام کاربری فرستنده']);
        $d['wallet_transfer_sender_id_line'] = self::def('msg_wallet_tx', 'انتقال موجودی: سطر آیدی فرستنده', "آیدی عددی: <code>{id}</code>", ['id' => 'آیدی فرستنده']);
        $d['wallet_transfer_received_notice'] = self::def('msg_wallet_tx', 'انتقال موجودی: اعلان واریز به گیرنده', "مبلغ {amount} تومان به کیف پول شما واریز شد.\n\n<blockquote>{sender}</blockquote>", ['amount' => 'مبلغ', 'sender' => 'مشخصات فرستنده']);

        $d['onboarding_verify_pending'] = self::def('msg_onboarding', 'حساب احراز هویت نشده', "⚠️ حساب شما احراز هویت نشده است پیام  شما  به ادمین ارسال شده\n    در صورت پیگیری  سریع تر می توانید به آیدی زیر پیام دهید\n    @{support}", ['support' => 'آیدی پشتیبانی (بدون @)']);
        $d['onboarding_miniapp_not_configured'] = self::def('msg_onboarding', 'آدرس مینی‌اپ تنظیم نشده', "آدرس مینی‌اپ تنظیم نشده است. لطفاً با پشتیبانی تماس بگیرید.");
        $d['onboarding_miniapp_open_hint'] = self::def('msg_onboarding', 'راهنمای ورود به مینی‌اپ', "برای ورود به مینی‌اپ، دکمهٔ زیر را بزنید.");
        $d['onboarding_payment_already_confirmed_alert'] = self::def('msg_onboarding', 'هشدار: پرداخت قبلاً تأیید شده', "✅ این پرداخت قبلاً تأیید شده است.");
        $d['onboarding_channel_check_failed_alert'] = self::def('msg_onboarding', 'هشدار: خطا در بررسی عضویت کانال', "⚠️ بررسی عضویت شما در کانال در حال حاضر با خطا مواجه شد؛ لطفاً چند لحظه دیگر دوباره تلاش کنید.");

        $d['notify_renewal_activated'] = self::def('msg_notify', 'اعلان: اشتراک رزروشده فعال شد', "✅ اشتراک شما فعال شد. کد پیگیری: <code>{invoice}</code>", ['invoice' => 'کد پیگیری سرویس']);
        $d['notify_btn_view_service'] = self::def('msg_notify', 'دکمه اعلان: مشاهده اشتراک', "مشاهده اشتراک");
        $d['notify_cashback_gift'] = self::def('msg_notify', 'اعلان: هدیهٔ بازگشت وجه کارت به کارت', "🎁 کاربر عزیز مبلغ {amount} تومان به عنوان هدیه واریز به حساب شما واریز گردید.", ['amount' => 'مبلغ هدیه']);
        $d['notify_ip_dropped'] = self::def('msg_notify', 'اعلان: قطع اتصال IP قبلی', "⚠️ چون یک دستگاه/IP جدید به کانفیگ شما وصل شد، اتصال قبلی از IP <code>{ips}</code> قطع شد.\nاین IP بعد از مدتی خودکار آزاد می‌شود.\nاگر این تغییر از سمت شما نبوده، کانفیگ خود را با کسی به اشتراک نگذارید.", ['ips' => 'فهرست IPهای قطع‌شده']);
        $d['notify_on_hold_reminder'] = self::def('msg_notify', 'اعلان: یادآوری عدم اتصال به کانفیگ', "سلام! 🌐\r\n\r\nدیدیم که شما هنوز به کانفیگ خود با نام کاربری {username} متصل نشده‌اید و بیش از {days} روز از فعال‌سازی آن گذشته است. اگر در راه‌اندازی یا استفاده از سرویس مشکلی دارید، لطفاً با تیم پشتیبانی ما  از طریق آیدی زیر در ارتباط باشید تا به شما کمک کنیم.\r\nما آماده‌ایم تا هر گونه سوال یا مشکلی را برطرف کنیم! 📞\r\n\r\nاکانت پشتیبانی : @{support}", ['username' => 'نام کاربری سرویس', 'days' => 'تعداد روز', 'support' => 'آیدی پشتیبانی (بدون @)']);

        $d['service_custom_volume_prompt'] = self::def('msg_service_buy', 'درخواست حجم دلخواه', "📌 حجم درخواستی خود را ارسال کنید.\n🔔قیمت هر گیگ حجم {price} تومان می باشد.\n🔔 حداقل حجم {min} گیگابایت و حداکثر {max} گیگابایت می باشد.", ['price' => 'قیمت هر گیگ به تومان', 'min' => 'حداقل حجم (گیگابایت)', 'max' => 'حداکثر حجم (گیگابایت)']);
        $d['service_custom_volume_invalid'] = self::def('msg_service_buy', 'حجم دلخواه نامعتبر', "❌ حجم نامعتبر است.\n🔔 حداقل حجم {min} گیگابایت و حداکثر {max} گیگابایت می باشد", ['min' => 'حداقل حجم (گیگابایت)', 'max' => 'حداکثر حجم (گیگابایت)']);
        $d['service_custom_volume_prompt_bulk'] = self::def('msg_service_buy', 'درخواست حجم دلخواه (خرید انبوه)', "🔋 لطفا مقدار حجم سرویس مورد نظر را وارد کنید ( برحسب گیگابایت ) :\n📌 تعرفه هر گیگ :  {price}\n🔔 حداقل حجم 1 گیگابایت و حداکثر 1000 گیگابایت می باشد.", ['price' => 'قیمت هر گیگ به تومان']);
        $d['service_custom_duration_prompt'] = self::def('msg_service_buy', 'درخواست مدت دلخواه', "⌛️ زمان سرویس خود را انتخاب نمایید\n📌 تعرفه هر روز  : {price}  تومان\n⚠️ حداقل زمان {min} روز  و حداکثر {max} روز  می توانید تهیه کنید", ['price' => 'تعرفهٔ هر روز به تومان', 'min' => 'حداقل روز', 'max' => 'حداکثر روز']);

        $d['service_extra_traffic_ask'] = self::def('msg_service_renew', 'حجم اضافه: درخواست مقدار', " ⭕️ مقدار حجمی که میخواهید خریداری کنید را ارسال کنید.\n❌ مبلغ را به انگلیسی ارسال نمایید.\n        ⚠️ هر گیگ  حجم اضافه {price} تومان  است.", ['price' => 'قیمت هر گیگ حجم اضافه']);
        $d['service_extra_traffic_invoice'] = self::def('msg_service_renew', 'حجم اضافه: فاکتور', "📜 فاکتور خرید حجم اضافه برای شما ایجاد شد.\n\n📌 تعرفه هر گیگابایت حجم اضافه : {price} تومان\n🔋 حجم اضافه درخواستی : {amount} گیگابایت\n💰 مبلغ فاکتور شما : {total} تومان\n\n✅ جهت پرداخت و اضافه شدن حجم، روی دکمه زیر کلیک کنید", ['price' => 'تعرفه هر گیگ', 'amount' => 'حجم درخواستی', 'total' => 'مبلغ فاکتور']);
        $d['service_extra_traffic_discount_invoice'] = self::def('msg_service_renew', 'حجم اضافه: فاکتور با کد تخفیف', "🤩 کد تخفیف {label} روی فاکتور شما اعمال شد.\n\n💰 مبلغ قبل از تخفیف : {before} تومان\n💸 مبلغ قابل پرداخت : {after} تومان\n\n✅ جهت پرداخت و اضافه شدن حجم، روی دکمه زیر کلیک کنید", ['label' => 'عنوان کد تخفیف', 'before' => 'مبلغ قبل از تخفیف', 'after' => 'مبلغ قابل پرداخت']);
        $d['service_extra_traffic_success'] = self::def('msg_service_renew', 'حجم اضافه: موفقیت', "✅ افزایش حجم برای سرویس شما با موفقیت صورت گرفت\n\n▫️نام سرویس  : {username}\n▫️حجم اضافه : {amount} گیگ\n\n▫️مبلغ افزایش حجم : {total} تومان", ['username' => 'نام سرویس', 'amount' => 'حجم اضافه', 'total' => 'مبلغ']);
        $d['service_extra_time_ask'] = self::def('msg_service_renew', 'زمان اضافه: درخواست تعداد روز', "📆 تعداد روز اضافه مورد نظر را وارد کنید ( برحسب روز ) :\n\n📌 تعرفه هر روز:  {price}", ['price' => 'تعرفه هر روز']);
        $d['service_extra_time_invoice'] = self::def('msg_service_renew', 'زمان اضافه: فاکتور', "📜 فاکتور خرید زمان اضافه برای شما ایجاد شد.\n\n📌 تعرفه هر روز زمان اضافه : {price} تومان\n📆 تعداد روز اضافه درخواستی : {days} روز\n💰 مبلغ فاکتور شما : {total} تومان\n\n✅ جهت پرداخت و اضافه شدن زمان، روی دکمه زیر کلیک کنید", ['price' => 'تعرفه هر روز', 'days' => 'تعداد روز', 'total' => 'مبلغ فاکتور']);
        $d['service_extra_time_discount_invoice'] = self::def('msg_service_renew', 'زمان اضافه: فاکتور با کد تخفیف', "🤩 کد تخفیف {label} روی فاکتور شما اعمال شد.\n\n💰 مبلغ قبل از تخفیف : {before} تومان\n💸 مبلغ قابل پرداخت : {after} تومان\n\n✅ جهت پرداخت و اضافه شدن زمان، روی دکمه زیر کلیک کنید", ['label' => 'عنوان کد تخفیف', 'before' => 'مبلغ قبل از تخفیف', 'after' => 'مبلغ قابل پرداخت']);
        $d['service_extra_time_success'] = self::def('msg_service_renew', 'زمان اضافه: موفقیت', "✅ افزایش زمان برای سرویس شما با موفقیت صورت گرفت\n\n▫️نام سرویس : {username}\n▫️زمان اضافه : {days} روز\n\n▫️مبلغ افزایش زمان : {total} تومان", ['username' => 'نام سرویس', 'days' => 'روز اضافه', 'total' => 'مبلغ']);

        $d['service_custom_duration_invalid'] = self::def('msg_service_buy', 'مدت دلخواه نامعتبر', "❌ زمان ارسال شده نامعتبر است . زمان باید بین {min} روز تا {max} روز باشد", ['min' => 'حداقل روز', 'max' => 'حداکثر روز']);
        $renewVars = ['username' => 'نام کاربری سرویس', 'product' => 'نام محصول', 'price' => 'مبلغ تمدید', 'days' => 'مدت تمدید (روز)', 'volume' => 'حجم تمدید', 'note' => 'توضیحات محصول', 'balance' => 'موجودی کیف پول'];
        $d['service_renewal_invoice_preview'] = self::def('msg_service_renew', 'پیش‌فاکتور تمدید (انتخاب محصول)', "📜 فاکتور تمدید شما برای نام کاربری {username} ایجاد شد.\n\n🛍 نام محصول :{product}\n💸 مبلغ تمدید : {price} تومان\n⏱ مدت زمان تمدید :{days} روز\n🔋 حجم تمدید :{volume}\n✍️ توضیحات : {note}\n💸 موجودی کیف پول : {balance}", $renewVars);
        $d['service_renewal_invoice_preview_alt'] = self::def('msg_service_renew', 'پیش‌فاکتور تمدید (تکرار سفارش)', "📜 فاکتور تمدید شما برای نام کاربری {username} ایجاد شد.\n\n🛍 نام محصول :{product}\n💸 مبلغ تمدید :{price}\n⏱ مدت زمان تمدید :{days} روز\n🔋 حجم تمدید :{volume} گیگ\n✍️ توضیحات : {note}\n💸 موجودی کیف پول : {balance}", $renewVars);
        $d['service_renewal_confirm_hint'] = self::def('msg_service_renew', 'راهنمای تأیید تمدید', "✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید");
        $d['service_bulk_invoice_preview'] = self::def('msg_service_buy', 'پیش‌فاکتور خرید انبوه', "\n📇 پیش فاکتور شما:\n👤 نام کاربری: <code>{username}</code>\n🔐 نام سرویس: {product}\n📆 مدت اعتبار: {days} روز\n💶 قیمت: {price}  تومان\n👥 حجم اکانت: {volume} گیگ\n💵 موجودی کیف پول شما : {balance}\n⭕️تعداد کانفیگ : {count}\n\n💰 سفارش شما آماده پرداخت است.  ", ['username' => 'نام کاربری', 'product' => 'نام سرویس', 'days' => 'مدت اعتبار', 'price' => 'قیمت', 'volume' => 'حجم', 'balance' => 'موجودی کیف پول', 'count' => 'تعداد کانفیگ']);
        $d['service_discount_invoice_preview'] = self::def('msg_service_buy', 'پیش‌فاکتور با کد تخفیف', "\n📇 پیش فاکتور شما:\n👤 نام کاربری: <code>{username}</code>\n🔐 نام سرویس: {product}\n📆 مدت اعتبار: {days} روز\n💶 قیمت اصلی : <del>{price_main} تومان</del>\n💶 قیمت با تخفیف: {price_discounted}  تومان\n👥 حجم اکانت: {volume} گیگ\n💵 موجودی کیف پول شما : {balance}\n\n        💰 سفارش شما آماده پرداخت است.  ", ['username' => 'نام کاربری', 'product' => 'نام سرویس', 'days' => 'مدت اعتبار', 'price_main' => 'قیمت اصلی', 'price_discounted' => 'قیمت با تخفیف', 'volume' => 'حجم', 'balance' => 'موجودی کیف پول']);
        $d['service_preview_line_user_count'] = self::def('msg_service_buy', 'پیش‌فاکتور: سطر تعداد کاربر', "👥 تعداد کاربر: {count}", ['count' => 'تعداد کاربر']);
        $d['service_preview_line_device_limit'] = self::def('msg_service_buy', 'پیش‌فاکتور: سطر محدودیت دستگاه', "🔒 محدودیت دستگاه: {count} دستگاه", ['count' => 'تعداد دستگاه']);

        $d['payment_card_manual_credited_full'] = self::def('msg_pay_card', 'تأیید دستی ادمین: کل مبلغ شارژ شد', "💎 کاربر عزیز، پرداخت کارت‌به‌کارت شما با کد پیگیری <code>{order_id}</code> بررسی شد و کل مبلغ دریافتی یعنی {amount} تومان به کیف پول شما اضافه گردید.\nℹ️ این یک تصمیم دستی ادمین است و کارمزد شناسایی از مبلغ واریز کسر نشده است.", ['order_id' => 'کد پیگیری', 'amount' => 'مبلغ به تومان']);
        $d['service_extra_traffic_failed_refunded'] = self::def('msg_pay_refund', 'حجم اضافه ناموفق؛ وجه بازگشت', "💎  کاربر عزیز بدلیل انجام نشدن خرید حجم اضافه مبلغ {amount} تومان به کیف پول شما اضافه گردید.", ['amount' => 'مبلغ به تومان']);
        $d['service_extra_time_failed_refunded'] = self::def('msg_pay_refund', 'زمان اضافه ناموفق؛ وجه بازگشت', "💎  کاربر عزیز بدلیل انجام نشدن خرید زمان اضافه مبلغ {amount} تومان به کیف پول شما اضافه گردید.", ['amount' => 'مبلغ به تومان']);
        $d['payment_tronado_manual_review'] = self::def('msg_pay_gateways', 'ترونادو: ارجاع به بررسی مدیریت', "⏳ پرداخت شما در ترونادو ثبت شده اما برای تأیید نهایی به بررسی مدیریت ارجاع شد. لطفاً با پشتیبانی در ارتباط باشید.\n\n🛒 کد فاکتور: <code>{order_id}</code>", ['order_id' => 'کد فاکتور']);
        $d['service_config_list_failed'] = self::def('msg_service_manage', 'نمایش لیست کانفیگ‌ها ناموفق', "❌ نمایش لیست کانفیگ‌ها ناموفق بود. لطفاً دوباره تلاش کنید.");
        $d['service_files_incomplete_contact_support'] = self::def('msg_service_manage', 'ارسال فایل‌های سرویس ناقص ماند', "⚠️ ارسال همه فایل‌های سرویس کامل نشد. برای پیگیری با پشتیبانی تماس بگیرید.");
        $d['service_files_pending_retry'] = self::def('msg_service_manage', 'ارسال فایل سرویس در انتظار تأیید', "⚠️ ارسال فایل سرویس هنوز تأیید نشده است. برای دریافت لینک اشتراک یا فایل‌های موجود به صفحه سرویس مراجعه کنید.");

        self::$definitions = $d;
        return $d;
    }
}
