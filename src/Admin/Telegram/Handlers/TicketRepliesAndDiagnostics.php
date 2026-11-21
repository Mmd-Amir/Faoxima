<?php

namespace Faoxima\Admin\Telegram\Handlers;

use InvalidArgumentException;
use PDO;
use RxOptimizerController;
use Throwable;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $caption, $dataget, $datain, $from_id, $message_id, $pdo, $photo, $photoid, $rxRedisTestText, $setting, $text, $textbotlang, $user, $video, $videoid.
 * Inherited or exported by reference: $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TicketRepliesAndDiagnostics
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $caption,
        $dataget,
        $datain,
        $from_id,
        $message_id,
        $pdo,
        $photo,
        $photoid,
        $rxRedisTestText,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $user,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }
        
        switch ($adminAction) {
        case 1:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $iduser, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('getmessageAsAdmin', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backadmin, 'HTML');
            break;
        case 2:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SendMessageuser'], null, 'HTML');
            $Respuseronse = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
                    ],
                ]
            ]);
            if ($text) {
                $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$text";
                sendmessage($user['Processing_value'], $textSendAdminToUser, $Respuseronse, 'HTML');
            }
            if ($photo) {
                $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$caption";
                telegram('sendphoto', [
                    'chat_id' => $user['Processing_value'],
                    'photo' => $photoid,
                    'reply_markup' => $Respuseronse,
                    'caption' => $textSendAdminToUser,
                    'parse_mode' => "HTML",
                ]);
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 3:
            if ((string)($setting['miniapp_ticket_mode'] ?? '0') === '1') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پاسخ به تیکت‌ها فقط از طریق پنل وب مدیریت امکان‌پذیر است.", $backadmin, 'HTML');
                return;
            }
            $ttk = \Faoxima\Admin\Telegram\Tickets::rxAdminTicketFind($dataget[1]);
            if (!is_array($ttk)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این تیکت یافت نشد.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $dataget[1], "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('ticketadminreplyWait', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📝 پاسخ خود را برای این تیکت بفرستید (متن، عکس یا ویدیو):", $backadmin, 'HTML');
            break;
        case 4:
            if ((string)($setting['miniapp_ticket_mode'] ?? '0') === '1') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پاسخ به تیکت‌ها فقط از طریق پنل وب مدیریت امکان‌پذیر است.", null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $T = (string)$user['Processing_value'];
            $ttk = \Faoxima\Admin\Telegram\Tickets::rxAdminTicketFind($T);
            if (!is_array($ttk)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این تیکت یافت نشد.", null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $time = date('Y/m/d H:i:s');
            [$bodytext, $userKb, $toUser] = \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminTelegramTicketReply($T, $text, $caption, $photo, $video, $photoid ?? null, $videoid ?? null);
            $sendRes = sendmessage($ttk['iduser'], $toUser, $userKb, 'HTML');
            if (empty($sendRes['ok'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ارسال پاسخ به کاربر ناموفق بود (ممکن است کاربر ربات را بلاک کرده باشد یا خطای تلگرام رخ داده باشد).\nتیکت همچنان در صف پاسخگویی باقی ماند.", null, 'HTML');
                return;
            }
            if ($photo) { sendphoto($ttk['iduser'], $photoid, null); }
            if ($video) { sendvideo($ttk['iduser'], $videoid, null); }
            $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$T, $ttk['idsupport'], $ttk['iduser'], $ttk['name_departman'], $bodytext, 'admin', $time, 'Answered']);
            \Faoxima\Admin\Telegram\Tickets::rxAdminTicketStatus($T, "Answered");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ پاسخ شما ثبت و برای کاربر ارسال شد.", null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ فقط متن، عکس یا ویدیو پشتیبانی می‌شود.", null, 'HTML');
            return;
            break;
        case 6:
            $rxFeatView = 'main';
            $rxDatainStr = (string)($datain ?? '');
            if ($rxDatainStr === 'featcat_bot')       $rxFeatView = 'bot';
            elseif ($rxDatainStr === 'featcat_users') $rxFeatView = 'users';
            elseif ($rxDatainStr === 'featcat_shop')  $rxFeatView = 'shop';
            elseif ($rxDatainStr === 'featcat_lottery') $rxFeatView = 'lottery';
            elseif ($rxDatainStr === 'featcat_crons') $rxFeatView = 'crons';
            elseif ($rxDatainStr === 'featcat_antispam') $rxFeatView = 'antispam';
            \Faoxima\Admin\Telegram\FeatureViews::rxRenderFeatureStatus($rxFeatView, $from_id);
            return;
            break;
        case 7:
            try {
                $rxWorkerGroup = '';
                $rxWorkerProfile = null;
                if (preg_match('/^cronworkers_set_(broadcast|payment)_([1-8])$/D', $datain, $rxWorkerMatch)) {
                    $rxWorkerGroup = $rxWorkerMatch[1];
                    $rxWorkerKey = $rxWorkerGroup === 'broadcast' ? 'broadcast_workers' : 'payment_workers';
                    $rxWorkerProfile = \Faoxima\Core\Runtime\HostProfile::rx_save_cron_worker_count($rxWorkerKey, (int) $rxWorkerMatch[2]);
                    if (!empty($callback_query_id)) {
                        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '✅ ذخیره شد؛ از نوبت بعدیِ کار اعمال می‌شود.', 'cache_time' => 0]);
                    }
                } elseif (preg_match('/^cronworkers_pick_(broadcast|payment)$/D', $datain, $rxWorkerMatch)) {
                    $rxWorkerGroup = $rxWorkerMatch[1];
                } elseif ($datain !== 'cronworkers_settings') {
                    throw new InvalidArgumentException('Invalid worker selection');
                }
                \Faoxima\Admin\Telegram\FeatureViews::rxRenderCronWorkerSettings($from_id, $rxWorkerGroup, $rxWorkerProfile);
            } catch (Throwable $rxWorkerError) {
                error_log('[cron workers] ' . $rxWorkerError->getMessage());
                $rxWorkerBack = json_encode(['inline_keyboard' => [
                    [['text' => '🔄 تلاش دوباره', 'callback_data' => 'cronworkers_settings']],
                    [['text' => '🔙 کرون‌ها و زمان‌بندی', 'callback_data' => 'featcat_crons']],
                ]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, '❌ خواندن یا ذخیرهٔ ورکرها انجام نشد. مقدار باید بین ۱ تا ۸ باشد؛ اگر تنظیمات در حال ذخیره است، کمی بعد دوباره تلاش کنید. دسترسی نوشتن پوشهٔ cronbot/.runtime را هم بررسی کنید.', $rxWorkerBack, 'HTML');
            }
            return;
            break;
        case 8:
        
            $rxOptRoot           = defined('APP_ROOT_PATH') ? APP_ROOT_PATH : REFACTORED_LEGACY_ROOT;
            $rxOptCfgFile        = $rxOptRoot . '/optimization_config.php';
            $rxOptCooldownSecs   = 60;
            $rxOptLastRun        = 0;
            if (@is_file($rxOptCfgFile)) {
                $rxOptExisting = @include $rxOptCfgFile;
                if (is_array($rxOptExisting) && isset($rxOptExisting['generated_at'])) {
                    $rxOptLastRun = (int) $rxOptExisting['generated_at'];
                }
            }
            $rxOptSecondsAgo = time() - $rxOptLastRun;
        
            if ($rxOptLastRun > 0 && $rxOptSecondsAgo < $rxOptCooldownSecs) {
                $rxOptWait = $rxOptCooldownSecs - $rxOptSecondsAgo;
                if (!empty($callback_query_id)) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'text'              => "⏳ لطفاً {$rxOptWait} ثانیه دیگر مجدداً امتحان کنید.",
                            'show_alert'        => true,
                            'cache_time'        => 0,
                        ]);
                    } catch (\Throwable $rxOptCbErr) {}
                }
                return;
            }
        
            if (!empty($callback_query_id)) {
                try {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text'              => 'در حال آنالیز هاست...',
                        'show_alert'        => false,
                        'cache_time'        => 2,
                    ]);
                } catch (\Throwable $rxOptCbErr) {}
            }
            if (!class_exists('RxOptimizerController')) {
                class_alias(\Faoxima\Admin\Telegram\OptimizerController::class, 'RxOptimizerController');
            }
        
            $rxOptResultText = '❌ فایل‌های بهینه‌ساز یافت نشد.';
            if (class_exists('RxOptimizerController') && isset($pdo) && $pdo instanceof PDO) {
                try {
                    $rxOptCtrl       = new RxOptimizerController($pdo, $rxOptRoot);
                    $rxOptResultText = $rxOptCtrl->run();
                } catch (\Throwable $rxOptErr) {
                    $rxOptResultText = '❌ خطا در اجرای بهینه‌ساز: ' . htmlspecialchars($rxOptErr->getMessage());
                }
            }
        
            $rxOptKb = json_encode(['inline_keyboard' => [
                [['text' => "⚙️ تنظیم ورکرها", 'callback_data' => 'cronworkers_settings']],
                [['text' => "🔙 بازگشت به وضعیت قابلیت‌ها", 'callback_data' => 'featcat_main']],
            ]]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxOptResultText, $rxOptKb, 'HTML');
            return;
            break;
        case 9:
            if (method_exists(\Faoxima\Cron\Health\CronHealth::class, 'rx_cron_health_admin_action')) {
                \Faoxima\Cron\Health\CronHealth::rx_cron_health_admin_action($pdo ?? null, (string) $datain, $from_id, $callback_query_id ?? null, $message_id ?? 0);
            }
            return;
            break;
        case 10:
        
            \Faoxima\Admin\Telegram\FeatureViews::rxRenderFeatureStatus('glass_mode', $from_id);
            return;
            break;
        case 11:
            return;
            break;
        case 12:
        
            \Faoxima\Admin\Telegram\FeatureViews::rxRenderFeatureStatus('redis', $from_id);
            return;
            break;
        case 13:
        
            if (!empty($callback_query_id)) {
                try {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text'              => 'در حال تست Redis...',
                        'show_alert'        => false,
                        'cache_time'        => 0,
                    ]);
                } catch (\Throwable $rxRedisCbErr) {}
            }
        
            if (!method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_extension_available') || !\Faoxima\Core\Cache\RedisCache::rx_redis_extension_available()) {
                $rxRedisTestText = method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_extension_guidance')
                    ? \Faoxima\Core\Cache\RedisCache::rx_redis_extension_guidance()
                    : '⚠️ افزونه Redis در PHP فعال نیست.';
            } else {
                $rxRedisTestKey = 'faoxima:admintest:' . $from_id;
                $rxRedisTestValue = (string) time();
                $rxRedisWriteOk = method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_set') && \Faoxima\Core\Cache\RedisCache::rx_redis_set($rxRedisTestKey, $rxRedisTestValue, 10);
                $rxRedisReadBack = $rxRedisWriteOk && method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_get') ? \Faoxima\Core\Cache\RedisCache::rx_redis_get($rxRedisTestKey) : null;
                if ($rxRedisWriteOk && $rxRedisReadBack === $rxRedisTestValue) {
                    $rxRedisTestText = "✅ تست خواندن/نوشتن Redis موفق بود.";
                } else {
                    $rxRedisTestText = "❌ تست Redis ناموفق بود — اتصال برقرار نیست یا Redis غیرفعال است.";
                }
            }
        
            $rxRedisTestKb = json_encode(['inline_keyboard' => [
                [['text' => "🔙 بازگشت", 'callback_data' => 'run_redis_status']],
            ]]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxRedisTestText, $rxRedisTestKb, 'HTML');
            return;
            break;
        }
        
        return true;
    }
}
