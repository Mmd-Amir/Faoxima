<?php

namespace Faoxima\Admin\Telegram\Handlers;


/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $atlaspay, $atlaspayMode, $atlaspayModeMatch, $backadmin, $callback_query_id, $caption, $domainhosts, $from_id, $pdo, $photo, $photoid, $rialit, $text, $textbotlang, $video, $videoid.
 * Inherited or exported by reference: $data, $list, $result, $url.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RialitAndGatewayCredentials
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $atlaspay,
        $atlaspayMode,
        $atlaspayModeMatch,
        $backadmin,
        $callback_query_id,
        $caption,
        &$data,
        $domainhosts,
        $from_id,
        &$list,
        $pdo,
        $photo,
        $photoid,
        &$result,
        $rialit,
        $text,
        $textbotlang,
        &$url,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $currentKey = is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'maskSecret']) ? \Faoxima\Payments\Gateways\Rialit\Client::maskSecret(\Faoxima\Payments\Gateways\Rialit\Client::apiKey($pdo)) : '';
            if ($currentKey === '') {
                $currentKey = 'ثبت نشده';
            }
            $currentKey = htmlspecialchars($currentKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 کلید API ریالیت خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}\n\nکلید فقط در سرور ذخیره می‌شود و در مینی‌اپ یا کد سمت کاربر نمایش داده نمی‌شود.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apirialit', $from_id);
            break;
        case 2:
            $newRialitKey = trim((string) $text);
            if ($newRialitKey === '' || preg_match('/\s/', $newRialitKey)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کلید API نامعتبر است. کلید را بدون فاصله ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            if (!is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'saveSetting']) || !\Faoxima\Payments\Gateways\Rialit\Client::saveSetting($pdo, 'apirialit', $newRialitKey)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ذخیره کلید API ناموفق بود. دوباره تلاش کنید.", $rialit, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $rialit, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 3:
            $currentName = is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'displayName']) ? \Faoxima\Payments\Gateways\Rialit\Client::displayName() : '💚 ریالیت';
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🏷️ نام نمایشی فعلی: <b>" . htmlspecialchars($currentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b>\n\nنام نمایشی جدید را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('gettextrialit', $from_id);
            break;
        case 4:
            $newName = trim((string) $text);
            if ($newName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام نمایشی نمی‌تواند خالی باشد.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("textbot", "text", $newName, "id_text", "rialit");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ نام نمایشی ریالیت ذخیره شد.", $rialit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashrialit", $from_id);
            break;
        case 6:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackrialit");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "rialit");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 7:
            [$rialitMinNow, $rialitMaxNow] = is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'amountRange']) ? \Faoxima\Payments\Gateways\Rialit\Client::amountRange($pdo) : [50000, 10000000];
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی ریالیت را ارسال نمایید.\n\nمقدار فعلی: " . number_format($rialitMinNow) . " تومان\nحد مجاز API: از ۵۰,۰۰۰ تا ۱۰,۰۰۰,۰۰۰ تومان", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getmainrialit", $from_id);
            break;
        case 8:
            if (!ctype_digit($text) || (int) $text < 50000 || (int) $text > 10000000) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مقدار نامعتبر است. عددی بین ۵۰,۰۰۰ و ۱۰,۰۰۰,۰۰۰ تومان ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            $rialitMaxNow = is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'amountRange']) ? \Faoxima\Payments\Gateways\Rialit\Client::amountRange($pdo)[1] : 10000000;
            if ((int) $text > $rialitMaxNow) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ حداقل نمی‌تواند بیشتر از سقف فعلی (" . number_format($rialitMaxNow) . " تومان) باشد.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", (string) (int) $text, "NamePay", "minbalancerialit");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی ریالیت تنظیم گردید.", $rialit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 9:
            [$rialitMinNow, $rialitMaxNow] = is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'amountRange']) ? \Faoxima\Payments\Gateways\Rialit\Client::amountRange($pdo) : [50000, 10000000];
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی ریالیت را ارسال نمایید.\n\nمقدار فعلی: " . number_format($rialitMaxNow) . " تومان\nحد مجاز API: از ۵۰,۰۰۰ تا ۱۰,۰۰۰,۰۰۰ تومان", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getmaxrialit", $from_id);
            break;
        case 10:
            if (!ctype_digit($text) || (int) $text < 50000 || (int) $text > 10000000) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مقدار نامعتبر است. عددی بین ۵۰,۰۰۰ و ۱۰,۰۰۰,۰۰۰ تومان ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            $rialitMinNow = is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'amountRange']) ? \Faoxima\Payments\Gateways\Rialit\Client::amountRange($pdo)[0] : 50000;
            if ((int) $text < $rialitMinNow) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ سقف نمی‌تواند کمتر از کف فعلی (" . number_format($rialitMinNow) . " تومان) باشد.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", (string) (int) $text, "NamePay", "maxbalancerialit");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی ریالیت تنظیم گردید.", $rialit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 11:
            if (!method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitWebhookStatusSummary')) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ تابع ریالیت روی سرور موجود نیست.", $rialit, 'HTML');
                return;
            }
            $summary = \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitWebhookStatusSummary();
            $generated = $summary['generated_url'] !== '' ? $summary['generated_url'] : 'آدرس نصب تنظیم نشده است';
            $remote = $summary['remote_url'] !== '' ? $summary['remote_url'] : 'تنظیم نشده';
            $statusFa = $summary['status'] === 'ok' ? '✅ سالم' : ($summary['status'] === 'error' ? '❌ خطا' : '⚪ بررسی نشده');
            $textRialitWh = "🌐 آدرس وب‌هوک ریالیت\n\n🔗 آدرس تولیدشده از تنظیمات نصب:\n<code>" . htmlspecialchars($generated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>\n\n📌 آدرس ثبت‌شده روی ریالیت: <code>" . htmlspecialchars($remote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>\n📊 وضعیت: {$statusFa}";
            if ($summary['last_message'] !== '') {
                $textRialitWh .= "\n📝 آخرین نتیجه: " . htmlspecialchars($summary['last_message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $textRialitWh .= "\n\nبرای ثبت، از دکمه «✅ ثبت وب‌هوک ریالیت» استفاده کنید.";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textRialitWh, $rialit, 'HTML');
            break;
        case 12:
            $summary = method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitWebhookStatusSummary') ? \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitWebhookStatusSummary() : [];
            $statusFa = ($summary['status'] ?? '') === 'ok' ? '✅ سالم' : (($summary['status'] ?? '') === 'error' ? '❌ خطا' : '⚪ بررسی نشده');
            $checkedAt = (int) ($summary['checked_at'] ?? 0);
            $checkedFa = $checkedAt > 0 ? (function_exists('jdate') ? jdate('Y/m/d H:i', $checkedAt) : date('Y/m/d H:i', $checkedAt)) : '—';
            $lastMessage = trim((string) ($summary['last_message'] ?? ''));
            $textRialitStatus = "📊 وضعیت وب‌هوک ریالیت\n\n📌 وضعیت: {$statusFa}\n🕒 آخرین بررسی: {$checkedFa}\n🔗 آدرس ثبت‌شده: <code>" . htmlspecialchars((string) ($summary['remote_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>";
            if ($lastMessage !== '') {
                $textRialitStatus .= "\n📝 نتیجه: " . htmlspecialchars($lastMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textRialitStatus, $rialit, 'HTML');
            break;
        case 13:
            $result = is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'readWebhook']) ? \Faoxima\Payments\Gateways\Rialit\Client::readWebhook($pdo) : ['ok' => false, 'message' => 'تابع ریالیت موجود نیست', 'webhook_url' => ''];
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => !empty($result['ok']) ? '✅ وضعیت وب‌هوک خوانده شد' : '❌ خواندن وب‌هوک ناموفق بود', 'show_alert' => false]);
            $url = trim((string) ($result['webhook_url'] ?? ''));
            $textRialitRead = "🔍 نتیجه خواندن وب‌هوک ریالیت\n\n" . htmlspecialchars((string) ($result['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($url !== '') {
                $textRialitRead .= "\n\n🔗 آدرس فعلی: <code>" . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>";
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textRialitRead, $rialit, 'HTML');
            break;
        case 14:
            $generated = method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitWebhookUrl') ? \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitWebhookUrl() : '';
            $result = method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitRegisterWebhook') ? \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitRegisterWebhook($generated) : ['ok' => false, 'message' => 'تابع ریالیت موجود نیست', 'webhook_url' => ''];
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => !empty($result['ok']) ? '✅ وب‌هوک ثبت شد' : '❌ ثبت وب‌هوک ناموفق بود', 'show_alert' => false]);
            $textRialitRegister = "✅ ثبت وب‌هوک ریالیت\n\n" . htmlspecialchars((string) ($result['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($generated !== '') {
                $textRialitRegister .= "\n\n🔗 آدرس تولیدشده:\n<code>" . htmlspecialchars($generated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>";
            }
            if (!empty($result['webhook_url'])) {
                $textRialitRegister .= "\n\n📌 آدرس ثبت‌شده: <code>" . htmlspecialchars((string) $result['webhook_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>";
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textRialitRegister, $rialit, 'HTML');
            break;
        case 15:
            $currentIps = is_callable([\Faoxima\Payments\Gateways\Rialit\Access::class, 'trustedIps']) ? implode(', ', \Faoxima\Payments\Gateways\Rialit\Access::trustedIps($pdo)) : '';
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🛡 آدرس‌های IP مورد اعتماد ریالیت را وارد کنید.\n\nمی‌توانید چند آدرس یا محدوده CIDR را با کاما یا خط جدید جدا کنید.\n\nمقدار فعلی: <code>" . htmlspecialchars($currentIps !== '' ? $currentIps : 'خالی', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>\n\n⚠️ اگر این لیست خالی باشد، همه درخواست‌های وب‌هوک رد می‌شوند.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("rialit_trustips", $from_id);
            break;
        case 16:
            $list = is_callable([\Faoxima\Payments\Gateways\Rialit\Access::class, 'parseIpList']) ? \Faoxima\Payments\Gateways\Rialit\Access::parseIpList($text) : [];
            if (empty($list)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ هیچ آدرس معتبری پیدا نشد. آدرس‌ها را با کاما یا خط جدید جدا کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", implode(',', $list), "NamePay", "rialit_trusted_ips");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آدرس‌های IP مجاز وب‌هوک ریالیت ذخیره شد.", $rialit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 17:
            $currentProxies = is_callable([\Faoxima\Payments\Gateways\Rialit\Access::class, 'trustedProxies']) ? implode(', ', \Faoxima\Payments\Gateways\Rialit\Access::trustedProxies($pdo)) : '';
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🧩 آدرس پراکسی‌های مورد اعتماد را وارد کنید.\n\nهدرهای X-Forwarded-For و X-Real-IP فقط وقتی پذیرفته می‌شوند که درخواست از یکی از این آدرس‌ها آمده باشد.\n\nمقدار فعلی: <code>" . htmlspecialchars($currentProxies !== '' ? $currentProxies : 'خالی', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>\n\nبرای غیرفعال کردن، عبارت none را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("rialit_trustproxies", $from_id);
            break;
        case 18:
            $rawProxies = trim((string) $text);
            $list = strtolower($rawProxies) === 'none' ? [] : (is_callable([\Faoxima\Payments\Gateways\Rialit\Access::class, 'parseIpList']) ? \Faoxima\Payments\Gateways\Rialit\Access::parseIpList($rawProxies) : []);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", implode(',', $list), "NamePay", "rialit_trusted_proxies");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ پراکسی‌های مورد اعتماد ریالیت ذخیره شد.", $rialit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 19:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("helprialit", $from_id);
            break;
        case 20:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helprialit");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helprialit");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helprialit");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helprialit");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $rialit, 'HTML');
            break;
        case 21:
            $atlaspayRegisterNote = '';
            if (!empty($atlaspayModeMatch[1])) {
                \Faoxima\Payments\Gateways\Atlaspay\CustomClient::saveSetting($pdo, 'atlaspay_payment_mode', $atlaspayModeMatch[1]);
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '✅ مقصد پرداخت اطلس‌پی ذخیره شد', 'show_alert' => false]);
                $atlaspayMode = $atlaspayModeMatch[1];
                if ($atlaspayMode === 'custom') {
                    $atlaspayRegistration = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomRegisterWebhook();
                    $atlaspayRegisterNote = "\n\n" . (!empty($atlaspayRegistration['ok']) ? '✅ ' : '⚠️ ') . htmlspecialchars((string) ($atlaspayRegistration['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
            } else {
                $atlaspayMode = \Faoxima\Payments\Gateways\Atlaspay\CustomClient::paymentMode();
            }
            $atlaspayOfficialMark = $atlaspayMode === 'official' ? '✅ ' : '';
            $atlaspayCustomMark = $atlaspayMode === 'custom' ? '✅ ' : '';
            $atlaspayModeKb = json_encode(['inline_keyboard' => [
                [['text' => "{$atlaspayOfficialMark}🌐 پرداخت از طریق اطلس‌پی", 'callback_data' => 'atlaspay_paymode_official']],
                [['text' => "{$atlaspayCustomMark}💎 پرداخت مستقیم در ربات و مینی‌اپ ما", 'callback_data' => 'atlaspay_paymode_custom']],
                [['text' => "🔙 بازگشت", 'callback_data' => 'atlaspaysetting']],
            ]], JSON_UNESCAPED_UNICODE);
            $atlaspayModeLabel = $atlaspayMode === 'custom' ? '💎 پرداخت مستقیم در ربات و مینی‌اپ ما' : '🌐 پرداخت از طریق اطلس‌پی';
            $atlaspayModeWarning = '';
            if ($atlaspayMode === 'custom') {
                $atlaspayModeWarning = "\n\n⚠️ برای نمایش شماره کارت داخل ربات و مینی‌اپ، قابلیت «نمایش مستقیم اطلاعات پرداخت» باید از سمت اطلس‌پی برای فروشگاه شما فعال شده باشد (از دکمه «🆘 پشتیبانی» ربات اطلس‌پی درخواست فعال‌سازی بدهید). تا زمان فعال‌سازی، کاربر فقط از طریق دکمه «ارسال رسید پرداخت» به صفحه رسمی اطلس‌پی هدایت می‌شود.";
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔀 مقصد پرداخت اطلس‌پی\n\nدر حالت رسمی، کاربر برای پرداخت به مینی‌اپ رسمی اطلس‌پی هدایت می‌شود. در حالت مستقیم، اطلاعات پرداخت داخل ربات و مینی‌اپ نمایش داده می‌شود و فقط ارسال رسید در مینی‌اپ رسمی اطلس‌پی انجام می‌شود. فاکتورهای قبلی با همان روشی که ساخته شده‌اند ادامه پیدا می‌کنند.\n\nحالت فعلی: <b>{$atlaspayModeLabel}</b>{$atlaspayModeWarning}{$atlaspayRegisterNote}", $atlaspayModeKb, 'HTML');
            break;
        case 22:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apiatlaspay", "select");
            $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
            $textatlaspay = "🔑 کلید API اطلس‌پی خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textatlaspay, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apiatlaspay', $from_id);
            break;
        case 23:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $atlaspay, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "apiatlaspay");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 24:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apiblupal", "select");
            $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
            $blupalWebhookUrl = 'https://' . $domainhosts . '/payment/blupal.php';
            $blupalCallbackUrl = 'https://' . $domainhosts . '/payment/blupal_return.php';
            $textblupal = "🔑 کلید API بلوپال خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}\n\n";
            $textblupal .= "⚠️ پیش از ادامه، آدرس‌های زیر را در داشبورد بلوپال (بخش مدیریت API Key) ثبت کنید تا تأیید خودکار پرداخت‌ها کار کند:\n\n";
            $textblupal .= "🔗 درگاه Webhook:\n<code>{$blupalWebhookUrl}</code>\n\n";
            $textblupal .= "🔙 Callback page:\n<code>{$blupalCallbackUrl}</code>";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textblupal, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apiblupal', $from_id);
            break;
        }

        return true;
    }
}
