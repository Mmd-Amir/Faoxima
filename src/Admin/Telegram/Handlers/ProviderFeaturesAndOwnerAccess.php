<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $CartManage, $NowPaymentsManage, $Startelegram, $abangateway, $adminAction, $adminExecution, $adminnumber, $atlaspay, $backadmin, $blupal, $callback_query_id, $cubepay, $domainhosts, $from_id, $iranpaykeyboard, $keyboardprotocol, $keyboardzarinpal, $message_id, $nowpayment_setting_keyboard, $optionMarzban, $pdo, $rxGatewayLabelRoute, $text, $textbotlang, $tonpay, $trnado, $tronnowpayments, $update, $user, $variza.
 * Inherited or exported by reference: $Response, $button, $tableName.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderFeaturesAndOwnerAccess
{
    public static function handle(
        $CartManage,
        $NowPaymentsManage,
        &$Response,
        $Startelegram,
        $abangateway,
        $adminAction,
        $adminExecution,
        $adminnumber,
        $atlaspay,
        $backadmin,
        $blupal,
        &$button,
        $callback_query_id,
        $cubepay,
        $domainhosts,
        $from_id,
        $iranpaykeyboard,
        $keyboardprotocol,
        $keyboardzarinpal,
        $message_id,
        $nowpayment_setting_keyboard,
        $optionMarzban,
        $pdo,
        $rxGatewayLabelRoute,
        &$tableName,
        $text,
        $textbotlang,
        $tonpay,
        $trnado,
        $tronnowpayments,
        $update,
        $user,
        $variza
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $blupalWebhookUrl = 'https://' . $domainhosts . '/payment/blupal.php';
            $blupalCallbackUrl = 'https://' . $domainhosts . '/payment/blupal_return.php';
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "apiblupal");
            $textblupalSaved = $textbotlang['Admin']['SettingnowPayment']['Savaapi'] . "\n\n";
            $textblupalSaved .= "⚠️ فراموش نکنید آدرس‌های زیر را در داشبورد بلوپال (بخش مدیریت API Key) ثبت کنید:\n\n";
            $textblupalSaved .= "🔗 درگاه Webhook:\n<code>{$blupalWebhookUrl}</code>\n\n";
            $textblupalSaved .= "🔙 Callback page:\n<code>{$blupalCallbackUrl}</code>";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textblupalSaved, $blupal, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 2:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apicubepay", "select");
            $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
            $textcubepay = "🔑 توکن API کیوب‌پی خود را اینجا وارد کنید.\n\nتوکن فعلی شما: {$currentKey}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textcubepay, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apicubepay', $from_id);
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $cubepay, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "apicubepay");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 4:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apivariza", "select");
            $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
            $varizaWebhookUrl = 'https://' . $domainhosts . '/payment/variza_webhook.php';
            $textvariza = "🔑 توکن API واریزا خود را اینجا وارد کنید.\n\nتوکن فعلی شما: {$currentKey}\n\n";
            $textvariza .= "⚠️ پیش از ادامه، آدرس زیر را در پنل واریزا (بخش وب‌هوک پروفایل) ثبت کنید تا تأیید خودکار پرداخت‌ها کار کند:\n\n";
            $textvariza .= "🔗 درگاه Webhook:\n<code>{$varizaWebhookUrl}</code>";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textvariza, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apivariza', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $variza, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", trim($text), "NamePay", "apivariza");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 6:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "variza_webhook_secret", "select");
            $currentSecret = $PaySetting['ValuePay'] ?? 'ثبت نشده';
            $textvariza = "🔐 کلید وب‌هوک واریزا خود را اینجا وارد کنید (همان کلیدی که در پنل واریزا، بخش وب‌هوک به شما نمایش داده شد).\n\nکلید فعلی شما: {$currentSecret}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textvariza, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('variza_webhook_secret', $from_id);
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $variza, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", trim($text), "NamePay", "variza_webhook_secret");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 8:
            $currentUrl = trim((string) (\Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "urlabangateway", "select")['ValuePay'] ?? ''));
            $textabangateway = "🔗 آدرس درگاه آبان گیت وی را اینجا بفرستید.\n\n";
            $textabangateway .= "این آدرس و «کلید اتصال» را ربات آبان گیت وی (@Abangw_bot) به شما میدهد: تنظیمات ← اتصال به ربات ها ← اتصال ربات جدید ← فاکسیما آپدیت شده.\n\n";
            $textabangateway .= "آدرس فعلی: " . ($currentUrl !== '' ? "<code>" . htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>" : "ثبت نشده");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textabangateway, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('urlabangateway', $from_id);
            break;
        case 9:
            $abangatewayUrl = rtrim(trim((string) $text), '/');
            if (stripos($abangatewayUrl, 'https://') !== 0 || filter_var($abangatewayUrl, FILTER_VALIDATE_URL) === false) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آدرس درگاه باید با https:// شروع شود. همان آدرسی را بفرستید که ربات آبان گیت وی به شما داده است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $abangateway, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $abangatewayUrl, "NamePay", "urlabangateway");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 10:
            $currentKey = trim((string) (\Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apiabangateway", "select")['ValuePay'] ?? ''));
            // Shown masked: this screen can be open in a chat somebody else can see.
            $maskedKey = ($currentKey === '' || $currentKey === '0') ? 'ثبت نشده' : ('••••' . substr($currentKey, -4));
            $textabangateway = "🔑 کلید اتصال آبان گیت وی را اینجا بفرستید.\n\nکلید فعلی: {$maskedKey}\n\n";
            $textabangateway .= "این کلید فقط یک بار در ربات آبان گیت وی نشان داده میشود. اگر آن را ندارید، اتصال را آنجا حذف کنید و دوباره بسازید.";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textabangateway, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apiabangateway', $from_id);
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $abangateway, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", trim((string) $text), "NamePay", "apiabangateway");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 12:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "یک گزینه را انتخاب کنید", $tronnowpayments, 'HTML');
            break;
        case 13:
            if ($rxGatewayLabelRoute['is_prompt']) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxGatewayLabelRoute['prompt'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step($rxGatewayLabelRoute['step'], $from_id);
            } else {
                \Faoxima\Admin\Telegram\Support\SettingsFunctionsHelpers::rxAdminSaveGatewayLabel($from_id, $text, $rxGatewayLabelRoute['key'], (['CartManage' => $CartManage ?? null, 'NowPaymentsManage' => $NowPaymentsManage ?? null, 'Startelegram' => $Startelegram ?? null, 'abangateway' => $abangateway ?? null, 'atlaspay' => $atlaspay ?? null, 'blupal' => $blupal ?? null, 'cubepay' => $cubepay ?? null, 'iranpaykeyboard' => $iranpaykeyboard ?? null, 'keyboardzarinpal' => $keyboardzarinpal ?? null, 'nowpayment_setting_keyboard' => $nowpayment_setting_keyboard ?? null, 'tonpay' => $tonpay ?? null, 'trnado' => $trnado ?? null, 'tronnowpayments' => $tronnowpayments ?? null, 'variza' => $variza ?? null][$rxGatewayLabelRoute['keyboard']] ?? null));
            }
            break;
        case 14:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getprotocoldisable', $from_id);
            break;
        case 15:
            if (!isset($update['message']) && empty($text)) { return; }
            $protocol = ["vless", "vmess", "trojan", "shadowsocks"];
            if (!in_array($text, $protocol)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['invalidprotocol'], null, 'HTML');
                return;
            }
            $getinbounds = \Faoxima\Services\Panels\Drivers\Marzban::getinbounds($user['Processing_value']);
            if (is_array($getinbounds) && !empty($getinbounds['error'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ " . \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($getinbounds['error']), null, 'HTML');
                return;
            }
            $getinbounds = $getinbounds[$text];
            $list_marzban_panel_inbounds = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            foreach ($getinbounds as $button) {
                $list_marzban_panel_inbounds['keyboard'][] = [
                    ['text' => $button['tag']]
                ];
            }
            $list_marzban_panel_inbounds['keyboard'][] = [
                ['text' => "🏠 بازگشت به منوی مدیریت"],
            ];
            $json_list_marzban_panel_inbounds = json_encode($list_marzban_panel_inbounds);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $text, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['getInbound'], $json_list_marzban_panel_inbounds, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getInbounddisable', $from_id);
            break;
        case 16:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "نام اینباند با موفقیت ذخیره گردید", $optionMarzban, 'HTML');
            $textpro = "{$user['Processing_value_one']}*$text";
            \Faoxima\Core\Database\Db::update("marzban_panel", "inbound_deactive", $textpro, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 17:
            $textoptimize = "❌❌❌❌❌❌❌ متن زیر را با دقت بخوانید

📌 با تایید گزینه زیر عملیات زیر انجام خواهد شد. و قابل بازگشت نیستند

1 - سفارش های غیرفعال (بیش از ۳۰ روز) حذف خواهند شد
2 - سفارش های پرداخت نشده (بیش از ۳۰ روز) حذف خواهند شد
3 - سفارش های حذف شده توسط ادمین (بیش از ۳۰ روز)
4 - حذف سرویس های تست غیرفعال (بیش از ۳۰ روز)
5 - سفارش های حذف شده توسط کاربر (بیش از ۳۰ روز)
6 - سفارشاتی که زمان یا حجم شان تمام شده باشد (بیش از ۳۰ روز)
7 - فاکتورهای قدیمی پرداخت نشده و ناموفق (بیش از ۳۰ روز)
8 - تراکنش‌های کیف پول قدیمی‌تر از ۳۰ روز
9 - کدهای تخفیف منقضی‌شده (بیش از ۹۰ روز)
10 - درخواست‌های کنسلی و نمایندگی بررسی‌شده (بیش از ۶۰ روز)
11 - کدهای هدیه مصرف‌شده و لاگ‌های کریپتو (بیش از ۹۰ روز)
12 - تیکت‌های قدیمی‌تر از ۳۰ روز
13 - سرویس‌های متصل به پنل حذف‌شده شناسایی و علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز)
14 - تمام سرویس‌های فعال با پنل استعلام می‌شوند و سرویس‌های نامعتبر (کاربر یافت نشد) علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز) — این مرحله ممکن است طول بکشد
15 - سرویس‌هایی که بیش از ۳۰ روز از خریدشان گذشته و هیچ مصرفی نداشته‌اند (۰ مصرف) شناسایی و علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز دیگر) — سرویس‌هایی که حتی مقدار کمی مصرف داشته‌اند دست‌نخورده می‌مانند
16 - سرویس‌هایی که هنگام استعلام، پنل‌شان قابل‌دسترس نبود (خطای اتصال) علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز دیگر، مگر اینکه پنل در همین بازه دوباره در دسترس قرار گیرد)
17 - سرویس‌هایی که حجم یا زمانشان تمام شده و تمدید نشده‌اند علامت‌گذاری خواهند شد (حذف نهایی پس از ۳۰ روز دیگر از لحظه‌ی تمام‌شدن)

🛡 اگر در عملکرد ربات با باگ یا مشکلی مواجه شدید، از طریق گیت هاب یا گروه فاکسیما اطلاع رسانی کنید
<a href=\"https://github.com/Mmd-Amir/Faoxima\">لینک گیت هاب</a>";
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "✅ تایید و  بهینه سازی", 'callback_data' => 'optimizebot'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textoptimize, $Response, 'HTML');
            break;
        case 18:
            $mainAdminId = trim((string) ($adminnumber ?? ''));
            $currentUserId = trim((string) $from_id);
            if ($mainAdminId !== '' && $currentUserId !== $mainAdminId) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ فقط ادمین اصلی می‌تواند این بخش را مشاهده کند.", null, 'HTML');
                return;
            }
            $resetWarning = "⚠️ هشدار مهم\n\nبا تایید بازنشانی، تمامی جداول پایگاه داده حذف و مجدداً ساخته خواهند شد. این عملیات غیرقابل بازگشت است.\n\nآیا از انجام این کار مطمئن هستید؟";
            $resetKeyboard = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "✅ بله، مطمئن هستم", 'callback_data' => 'resetbot_confirm'],
                        ['text' => "❌ خیر", 'callback_data' => 'resetbot_cancel'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $resetWarning, $resetKeyboard, 'HTML');
            break;
        case 19:
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "عملیات لغو شد.",
                'show_alert' => false,
                'cache_time' => 5,
            ));
            Editmessagetext($from_id, $message_id, "❌ عملیات بازنشانی لغو شد.", null);
            break;
        case 20:
            $mainAdminId = trim((string) ($adminnumber ?? ''));
            $currentUserId = trim((string) $from_id);
            if ($mainAdminId !== '' && $currentUserId !== $mainAdminId) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "❌ شما اجازه انجام این عملیات را ندارید.",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "⏳ در حال بازنشانی...",
                'show_alert' => false,
                'cache_time' => 5,
            ));
            Editmessagetext($from_id, $message_id, "⏳ عملیات بازنشانی ربات آغاز شد. لطفاً منتظر بمانید...", null);

            $dropError = null;
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($tables)) {
                    foreach ($tables as $tableName) {
                        $tableName = trim($tableName);
                        if ($tableName !== '') {
                            $pdo->exec("DROP TABLE IF EXISTS `{$tableName}`;");
                        }
                    }
                }
            } catch (Throwable $exception) {
                $dropError = $exception;
            } finally {
                try {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                } catch (Throwable $ignored) {
                }
            }

            if ($dropError !== null) {
                file_put_contents(REFACTORED_LEGACY_ROOT . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] DROP ERROR: " . $dropError->getMessage() . PHP_EOL, FILE_APPEND);
                Editmessagetext($from_id, $message_id, "❌ خطا در حذف جداول. لطفاً فایل resetbot_error.log را بررسی کنید.", null);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ عملیات بازنشانی به دلیل خطا در حذف جداول متوقف شد.", null, 'HTML');
                return;
            }

            $resetUrlUsed = '';
            $reinstallSuccess = false;
            $installerErrors = [];
            $candidateUrls = [];
            $normalizedHost = '';

            if (!empty($domainhosts)) {
                $normalizedHost = rtrim($domainhosts, '/');
                $candidateUrls[] = "https://{$normalizedHost}/table.php";
                $candidateUrls[] = "http://{$normalizedHost}/table.php";
            }

            $attemptInstallerRequest = function (string $url) use (&$resetUrlUsed, &$reinstallSuccess, &$installerErrors) {
                if ($reinstallSuccess || $url === '') {
                    return;
                }

                $response = false;
                $httpCode = null;

                if (function_exists('curl_init')) {
                    $curlHandle = @curl_init($url);
                    if ($curlHandle !== false) {
                        curl_setopt_array($curlHandle, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 20,
                            CURLOPT_CONNECTTIMEOUT => 10,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]);
                        $response = curl_exec($curlHandle);
                        if ($response === false) {
                            $installerErrors[] = 'cURL error: ' . curl_error($curlHandle) . " ({$url})";
                        } else {
                            $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
                        }
                        curl_close($curlHandle);
                    }
                }

                if ($response === false) {
                    $streamContext = stream_context_create([
                        'http' => [
                            'timeout' => 20,
                        ],
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ],
                    ]);
                    $response = @file_get_contents($url, false, $streamContext);
                    if ($response === false) {
                        $installerErrors[] = 'stream error: unable to fetch ' . $url;
                    } else {
                        $httpCode = 200;
                    }
                }

                if ($response !== false && ($httpCode === null || ($httpCode >= 200 && $httpCode < 400))) {
                    $resetUrlUsed = $url;
                    $reinstallSuccess = true;
                }
            };

            foreach ($candidateUrls as $candidateUrl) {
                $attemptInstallerRequest($candidateUrl);
                if ($reinstallSuccess) {
                    break;
                }
            }

            if (!$reinstallSuccess) {
                $localTablePath = REFACTORED_LEGACY_ROOT . '/table.php';
                if (is_file($localTablePath)) {
                    try {
                        \Faoxima\Admin\Telegram\Support\LegacyInstaller::runTableScript($localTablePath);
                        $reinstallSuccess = true;
                        $resetUrlUsed = 'local include';
                    } catch (Throwable $tableError) {
                        $installerErrors[] = 'local table include: ' . $tableError->getMessage();
                        file_put_contents(REFACTORED_LEGACY_ROOT . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] TABLE ERROR: " . $tableError->getMessage() . PHP_EOL, FILE_APPEND);
                        Editmessagetext($from_id, $message_id, "⚠️ جداول حذف شدند اما اجرای table.php با خطا مواجه شد.", null);
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ اجرای table.php با خطا مواجه شد. لطفاً فایل resetbot_error.log را بررسی کنید.", null, 'HTML');
                        return;
                    }
                }
            }

            if ($reinstallSuccess) {
                $successMessage = "✅ بازنشانی ربات با موفقیت انجام شد." . (!empty($resetUrlUsed) ? "\nمنبع اجرا: {$resetUrlUsed}" : '');
                Editmessagetext($from_id, $message_id, $successMessage, null);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ عملیات بازنشانی ربات با موفقیت انجام شد.", null, 'HTML');
            } else {
                if (!empty($installerErrors)) {
                    file_put_contents(REFACTORED_LEGACY_ROOT . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] INSTALL ERROR: " . implode(' | ', $installerErrors) . PHP_EOL, FILE_APPEND);
                }
                $manualUrlHint = !empty($normalizedHost) ? "لطفاً لینک https://{$normalizedHost}/table.php را به صورت دستی باز کنید." : "لطفاً فایل table.php را به صورت دستی اجرا کنید.";
                $warningText = "⚠️ جداول حذف شدند اما اجرای table.php انجام نشد. {$manualUrlHint}";
                Editmessagetext($from_id, $message_id, $warningText, null);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $warningText, null, 'HTML');
            }
            break;
        }

        return true;
    }
}
