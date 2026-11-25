<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use Throwable;

final class TetraminatorSettings
{
    public static function menuKeyboard($textbotlang = null): string
    {
        return json_encode(['inline_keyboard' => [
            [['text' => "🏷️ نام نمایشی درگاه تترامیناتور", 'callback_data' => 'tmset_name']],
            [['text' => "🔑 ثبت API Key تترامیناتور", 'callback_data' => 'tmset_apikey']],
            [['text' => "💰 کش بک تترامیناتور", 'callback_data' => 'tmset_cashback']],
            [
                ['text' => "⬇️ کف تترامیناتور", 'callback_data' => 'tmset_min'],
                ['text' => "⬆️ سقف تترامیناتور", 'callback_data' => 'tmset_max'],
            ],
            [['text' => "📊 وضعیت درگاه و تراکنش‌ها", 'callback_data' => 'tmset_info']],
            [['text' => "📚 آموزش تترامیناتور", 'callback_data' => 'tmset_edu']],
            [['text' => "🔙 بازگشت", 'callback_data' => 'tmset_back']],
        ]], JSON_UNESCAPED_UNICODE);
    }

    private static function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function menuText($pdo): string
    {
        $callbackBase = \Faoxima\Payments\Gateways\Tetraminator\Client::callbackBaseUrl();
        $text = "💎 تنظیمات درگاه تترامیناتور\n\nیکی از گزینه‌ها را انتخاب کنید.";
        if ($callbackBase !== '') {
            $text .= "\n\n🌐 آدرس وب‌هوک (برای هر فاکتور به‌صورت خودکار و با شناسه اختصاصی ساخته می‌شود و نیازی به ثبت دستی ندارد):\n<code>" . self::escape($callbackBase) . "</code>";
        }
        return $text;
    }

    public static function showMenu($from_id, $pdo): void
    {
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
            \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'gw_tetraminator');
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, self::menuText($pdo), self::menuKeyboard(), 'HTML');
    }

    private static function statusText($pdo): string
    {
        $client = \Faoxima\Payments\Gateways\Tetraminator\Client::class;
        $enabled = $client::enabled($pdo) ? '✅ فعال' : '⚪ غیرفعال';
        $key = $client::maskSecret($client::apiKey($pdo));
        [$min, $max] = $client::amountRange($pdo);
        $lines = [
            "📊 وضعیت درگاه تترامیناتور",
            "",
            "📌 وضعیت: " . $enabled,
            "🔑 API Key: " . ($key !== '' ? self::escape($key) : 'ثبت نشده'),
            "⬇️ حداقل مبلغ: " . number_format($min) . " تومان",
            "⬆️ حداکثر مبلغ: " . number_format($max) . " تومان",
            "💰 کش‌بک: " . (int) $client::setting($pdo, 'chashbacktetraminator', '0') . "٪",
        ];
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->query(
                    "SELECT
                        SUM(CASE WHEN payment_Status IN ('Unpaid','pending','waiting') AND tetraminator_pay_id IS NOT NULL AND tetraminator_pay_id <> '' THEN 1 ELSE 0 END) AS open_count,
                        SUM(CASE WHEN payment_Status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                        SUM(CASE WHEN payment_Status = 'paid' AND COALESCE(direct_payment_done, 0) = 0 THEN 1 ELSE 0 END) AS unfulfilled_count,
                        MAX(tetraminator_webhook_at) AS last_webhook
                       FROM Payment_report WHERE Payment_Method = 'tetraminator'"
                );
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if (is_array($row)) {
                    $lastWebhook = (int) ($row['last_webhook'] ?? 0);
                    $lines[] = "";
                    $lines[] = "⏳ فاکتورهای باز: " . (int) ($row['open_count'] ?? 0);
                    $lines[] = "✅ پرداخت‌های تأییدشده: " . (int) ($row['paid_count'] ?? 0);
                    $lines[] = "🛠 تأییدشده با تحویل ناتمام: " . (int) ($row['unfulfilled_count'] ?? 0);
                    $lines[] = "🔔 آخرین وب‌هوک دریافتی: " . ($lastWebhook > 0 ? (function_exists('jdate') ? jdate('Y/m/d H:i', $lastWebhook) : date('Y/m/d H:i', $lastWebhook)) : '—');
                }
            } catch (Throwable $e) {
            }
        }
        return implode("\n", $lines);
    }

    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $caption,
        $from_id,
        $pdo,
        $photo,
        $photoid,
        $text,
        $textbotlang,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }
        $client = \Faoxima\Payments\Gateways\Tetraminator\Client::class;
        $menu = self::menuKeyboard();
        $reply = static function ($message, $keyboard) use ($from_id) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $message, $keyboard, 'HTML');
        };

        switch ($adminAction) {
        case 1:
            self::showMenu($from_id, $pdo);
            break;
        case 2:
            $reply("🏷️ نام نمایشی فعلی: <b>" . self::escape($client::displayName()) . "</b>\n\nنام نمایشی جدید را ارسال کنید.", $backadmin);
            \Faoxima\Telegram\Navigation\Steps::step('tmset_name', $from_id);
            break;
        case 3:
            $newName = trim((string) $text);
            if ($newName === '' || mb_strlen($newName) > 100) {
                $reply("❌ نام نمایشی نامعتبر است (حداکثر ۱۰۰ نویسه).", $backadmin);
                return;
            }
            \Faoxima\Core\Database\Db::update("textbot", "text", $newName, "id_text", "tetraminator");
            $reply("✅ نام نمایشی تترامیناتور ذخیره شد.", $menu);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 4:
            $currentKey = $client::maskSecret($client::apiKey($pdo));
            $reply("🔑 کلید API تترامیناتور خود را اینجا وارد کنید.\n\nکلید فعلی شما: " . ($currentKey !== '' ? self::escape($currentKey) : 'ثبت نشده') . "\n\nکلید فقط در سرور ذخیره می‌شود و در مینی‌اپ یا کد سمت کاربر نمایش داده نمی‌شود.", $backadmin);
            \Faoxima\Telegram\Navigation\Steps::step('tmset_apikey', $from_id);
            break;
        case 5:
            $newKey = trim((string) $text);
            if ($newKey === '' || preg_match('/\s/', $newKey) || strlen($newKey) > 200) {
                $reply("❌ کلید API نامعتبر است. کلید را بدون فاصله ارسال کنید.", $backadmin);
                return;
            }
            if (!$client::saveSetting($pdo, 'apitetraminator', $newKey)) {
                $reply("❌ ذخیره کلید API ناموفق بود. دوباره تلاش کنید.", $menu);
            } else {
                $reply($textbotlang['Admin']['SettingnowPayment']['Savaapi'] ?? '✅ ذخیره شد', $menu);
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 6:
            $reply("📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin);
            \Faoxima\Telegram\Navigation\Steps::step("tmset_cashback", $from_id);
            break;
        case 7:
            if (!ctype_digit((string) $text) || (int) $text > 100) {
                $reply($textbotlang['Admin']['agent']['invalidvlue'] ?? '❌ مقدار نامعتبر است', $backadmin);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbacktetraminator");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "tetraminator");
            $reply("📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard());
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 8:
            [$minNow] = $client::amountRange($pdo);
            $reply("📌 حداقل مبلغ واریزی تترامیناتور را به تومان ارسال نمایید.\n\nمقدار فعلی: " . number_format($minNow) . " تومان\nپیش‌فرض تترامیناتور: از ۵۰,۰۰۰ تا ۱۰,۰۰۰,۰۰۰ تومان (ممکن است برای فروشگاه شما متفاوت تعریف شده باشد)", $backadmin);
            \Faoxima\Telegram\Navigation\Steps::step("tmset_min", $from_id);
            break;
        case 9:
            if (!ctype_digit((string) $text) || (int) $text < 1000 || (int) $text > 1000000000) {
                $reply("❌ مقدار نامعتبر است. یک مبلغ معتبر به تومان ارسال کنید.", $backadmin);
                return;
            }
            $maxNow = $client::amountRange($pdo)[1];
            if ((int) $text > $maxNow) {
                $reply("❌ حداقل نمی‌تواند بیشتر از سقف فعلی (" . number_format($maxNow) . " تومان) باشد.", $backadmin);
                return;
            }
            $client::saveSetting($pdo, 'minbalancetetraminator', (string) (int) $text);
            $reply("✅ حداقل مبلغ واریزی تترامیناتور تنظیم گردید.", $menu);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 10:
            [, $maxNow] = $client::amountRange($pdo);
            $reply("📌 حداکثر مبلغ واریزی تترامیناتور را به تومان ارسال نمایید.\n\nمقدار فعلی: " . number_format($maxNow) . " تومان\nپیش‌فرض تترامیناتور: از ۵۰,۰۰۰ تا ۱۰,۰۰۰,۰۰۰ تومان (ممکن است برای فروشگاه شما متفاوت تعریف شده باشد)", $backadmin);
            \Faoxima\Telegram\Navigation\Steps::step("tmset_max", $from_id);
            break;
        case 11:
            if (!ctype_digit((string) $text) || (int) $text < 1000 || (int) $text > 1000000000) {
                $reply("❌ مقدار نامعتبر است. یک مبلغ معتبر به تومان ارسال کنید.", $backadmin);
                return;
            }
            $minNow = $client::amountRange($pdo)[0];
            if ((int) $text < $minNow) {
                $reply("❌ سقف نمی‌تواند کمتر از کف فعلی (" . number_format($minNow) . " تومان) باشد.", $backadmin);
                return;
            }
            $client::saveSetting($pdo, 'maxbalancetetraminator', (string) (int) $text);
            $reply("✅ حداکثر مبلغ واریزی تترامیناتور تنظیم گردید.", $menu);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 12:
            $reply("📌آموزش خود را ارسال نمایید .\n۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید\n۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin);
            \Faoxima\Telegram\Navigation\Steps::step("tmset_edu", $from_id);
            break;
        case 13:
            if ($text) {
                if (intval($text) == 2) {
                    $client::saveSetting($pdo, 'helptetraminator', '0');
                } else {
                    $client::saveSetting($pdo, 'helptetraminator', json_encode(['type' => "text", 'text' => $text]));
                }
            } elseif ($photo) {
                $client::saveSetting($pdo, 'helptetraminator', json_encode(['type' => "photo", 'text' => $caption, 'photoid' => $photoid]));
            } elseif ($video) {
                $client::saveSetting($pdo, 'helptetraminator', json_encode(['type' => "video", 'text' => $caption, 'videoid' => $videoid]));
            } else {
                $reply("❌ محتوای ارسال نامعتبر است.", $backadmin);
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $reply("✅ آموزش با موفقیت ذخیره گردید.", $menu);
            break;
        case 14:
            $reply(self::statusText($pdo), $menu);
            break;
        case 15:
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Admin\Keyboard\AdminBuildersHelpers::sendAdminFinanceMenu($from_id);
            break;
        }

        return true;
    }
}
