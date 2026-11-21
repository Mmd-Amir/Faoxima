<?php

namespace Faoxima\Cron\Services;

use ManagePanel;
use PDO;
use Throwable;
use stdClass;

final class ServiceMonitor
{
    private $Panel;
    private $pdo;
    private $setting;
    private $reportCron;
    private $text_Purchased_services;
    private $status_cron;
    const SECONDS_PER_DAY = 86400;
    const DELETE_BUDGET = 5;
    const FAILED_CHECK_DELAY = 300;
    private $deletionAttempts = 0;
    private $textBotLang;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->Panel = new ManagePanel();
        $this->reportCron = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "reportcron", "select")['idreport'];
        $this->setting = \Faoxima\Core\Database\Db::select("setting", "*");
        $this->status_cron = json_decode($this->setting['cron_status'], true);
        $this->text_Purchased_services = \Faoxima\Core\Database\Db::select("textbot", "text", "id_text", "text_Purchased_services", "select")['text'];
        $this->textBotLang = \Faoxima\Telegram\Conversation\ConversationData::languagechange('../text.json');
    }

    public function RunNotifactions()
    {
        $invoices = $this->getActiveInvoices();
        if ($invoices == false) return;
        $this->deletionAttempts = 0;
        foreach ($invoices as $invoice) {
            if (\Faoxima\Cron\Runtime\CronRuntime::rx_cron_budget_exhausted()) {
                break;
            }
            if ($invoice['time_cron'] != null) {
                $time_cron = time() - $invoice['time_cron'];
                if ($time_cron < 1600) continue;
            }
            if ($this->deletionAttempts >= self::DELETE_BUDGET) break;
            try {
                $this->runInvoiceCheck($invoice);
            } catch (Throwable $e) {
                $this->rescheduleInvoiceCheck($invoice['id_invoice'], self::FAILED_CHECK_DELAY);
                error_log('[cron:notifications] invoice ' . $invoice['id_invoice'] . ': ' . $e->getMessage());
            }
        }
    }

    private function runInvoiceCheck(array $invoice): void
    {
        $data = $this->processInvoice($invoice);
        if (!is_array($data)) {
            $delay = ($data === false) ? 3600 : self::FAILED_CHECK_DELAY;
            $this->rescheduleInvoiceCheck($invoice['id_invoice'], $delay);
            return;
        }

        \Faoxima\Core\Database\Db::update("invoice", "time_cron", time(), "id_invoice", $invoice['id_invoice']);
        $check_send = json_decode($invoice['notifctions'], true);
        $data = $this->persistProviderState($data, $invoice);
        $this->applyMaintenancePolicy($data, $invoice, $check_send);
    }

    private function persistProviderState(array $data, array $invoice): array
    {
        $currentUStatus = strtolower((string)($data['userData']['status'] ?? ''));
        $currentUExp = is_numeric($data['userData']['expire'] ?? null) ? (int)$data['userData']['expire'] : 0;
        $currentUDl = is_numeric($data['userData']['data_limit'] ?? null) ? (float)$data['userData']['data_limit'] : 0.0;
        $currentUUt = is_numeric($data['userData']['used_traffic'] ?? null) ? (float)$data['userData']['used_traffic'] : 0.0;
        if ($currentUExp > 0 && $currentUExp <= time()) {
            $currentUStatus = 'expired';
            $data['userData']['status'] = 'expired';
        } elseif ($currentUDl > 0.0 && $currentUUt >= $currentUDl) {
            $currentUStatus = 'limited';
            $data['userData']['status'] = 'limited';
        }
        try {
            \Faoxima\Core\Database\Db::update("invoice", "user_info", json_encode($data['userData'], JSON_UNESCAPED_UNICODE), "id_invoice", $invoice['id_invoice']);
        } catch (Throwable $e) {
            error_log('[cron:notifications] provider snapshot persistence failed for invoice ' . $invoice['id_invoice'] . ': ' . get_class($e));
        }
        if ($currentUStatus === 'expired' && ($data['invoice']['Status'] ?? '') !== 'end_of_time') {
            \Faoxima\Core\Database\Db::update("invoice", "Status", "end_of_time", "id_invoice", $invoice['id_invoice']);
            $data['invoice']['Status'] = 'end_of_time';
        } elseif ($currentUStatus === 'limited' && ($data['invoice']['Status'] ?? '') !== 'end_of_volume') {
            \Faoxima\Core\Database\Db::update("invoice", "Status", "end_of_volume", "id_invoice", $invoice['id_invoice']);
            $data['invoice']['Status'] = 'end_of_volume';
        } elseif ($currentUStatus === 'on_hold' && ($data['invoice']['Status'] ?? '') !== 'send_on_hold') {
            \Faoxima\Core\Database\Db::update("invoice", "Status", "send_on_hold", "id_invoice", $invoice['id_invoice']);
            $data['invoice']['Status'] = 'send_on_hold';
        }
        return $data;
    }

    private function applyMaintenancePolicy(array $data, array $invoice, $check_send): void
    {
        $result = false;
        if (!$check_send['volume']) {
            if ($this->status_cron['volume']) $result = $this->checkVolumeThreshold($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
        }
        if ($result) $data['invoice'] = \Faoxima\Core\Database\Db::select("invoice", "id_invoice,id_user,username,Service_location,name_product,bottype,Status,notifctions,uuid", "id_invoice", $invoice['id_invoice']);
        if (!$check_send['time']) {
            if ($this->status_cron['day']) $this->checkTimeExpiration($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
        }
        $removalResult = false;
        if ($this->status_cron['remove']) $removalResult = $this->shouldRemoveService($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
        if ($removalResult === false && $this->status_cron['remove_volume']) {
            $removalResult = $this->shouldRemoveServiceـvolume($data['invoice'], $data['user'], $data['userData'], $invoice['username'], $data['panel']);
        }
        if ($removalResult === null) {
            $this->rescheduleInvoiceCheck($invoice['id_invoice'], self::FAILED_CHECK_DELAY);
        }
        if ($removalResult === false && !\Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up() && $data['panel']['inboundstatus'] == "oninbounddisable" && $data['panel']['type'] == "marzban") $this->active_inbound_expire($data['invoice'],  $data['userData'], $data['panel']);
    }

    private function rescheduleInvoiceCheck($invoiceId, int $delay): void
    {
        \Faoxima\Core\Database\Db::update("invoice", "time_cron", time() - 3600 + $delay, "id_invoice", $invoiceId);
    }


    private function getActiveInvoices()
    {
        $time_hours = time() - 3600;
        list($w, $n) = method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_shard') ? \Faoxima\Cron\Runtime\CronRuntime::rx_cron_shard() : [0, 1];
        $shard = ($n > 1) ? " AND MOD(id_invoice, $n) = $w " : "";
        $limit = method_exists(\Faoxima\Cron\Runtime\CronBatches::class, 'rx_cron_batch_size') ? \Faoxima\Cron\Runtime\CronBatches::rx_cron_batch_size(50) : 30;
        $QUERY = "SELECT id_invoice, id_user, username, Service_location, name_product, bottype, Status, time_cron, notifctions, uuid FROM invoice WHERE Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold') AND name_product != 'سرویس تست' AND (time_cron <= '$time_hours' OR time_cron IS NULL)$shard ORDER BY time_cron LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($QUERY);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function processInvoice($invoice)
    {
        $username = $invoice['username'];


        $panelInfo = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if (!$panelInfo) return false;


        $user = \Faoxima\Core\Database\Db::select("user", "id,status_cron", "id", $invoice['id_user'], "select");
        if ($user == false) return false;


        $userData = $this->Panel->DataUser($invoice['Service_location'], $username);
        if (($userData['paused_by'] ?? '') === 'credit') return false;
        if (!is_array($userData) || ($userData['status'] ?? '') == "Unsuccessful") return;
        return [
            'invoice' => $invoice,
            'panel' => $panelInfo,
            'user' => $user,
            'userData' => $userData
        ];
    }

    private function getServiceLabel($invoice, $username)
    {
        $label = trim((string) ($invoice['name_product'] ?? ''));
        if ($label === '') {
            $label = (string) $username;
        }
        return htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function checkVolumeThreshold($invoice, $user, $userData, $username)
    {
        $remainingVolume = $userData['data_limit'] - $userData['used_traffic'];
        $volumeWarningThreshold = $this->setting['volumewarn'] * pow(1024, 3);
        $isVolumeWarning = $remainingVolume <= $volumeWarningThreshold && $remainingVolume > 0 && in_array($userData['status'], ['active', 'Unknown']);

        if ($isVolumeWarning) {
            $formattedVolume = \Faoxima\Core\Support\Formatting::formatBytes($remainingVolume);
            $message = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cron_volume_warning_tpl', "با سلام خدمت شما کاربر گرامی 👋\n🚨 از حجم سرویس «{plan}» شما تنها {volume} باقی مانده است (شناسه سرویس: {username}). لطفاً در صورت تمایل برای خرید حجم اضافه و یا تمدید سرویستون از طریق بخش «{purchased_services}» اقدام بفرمایین"), [
                'username' => $username,
                'plan' => $this->getServiceLabel($invoice, $username),
                'volume' => $formattedVolume,
                'purchased_services' => $this->text_Purchased_services,
            ]);
            $reportMessage = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cron_volume_warning_report_tpl', "📌 اطلاعیه کرون حجم\n\n<blockquote>نام کاربری سرویس :‌ <code>{username}</code></blockquote>\n<blockquote>وضعیت سرویس : {status}</blockquote>\n<blockquote>حجم باقی مانده : {volume}</blockquote>"), [
                'username' => $username,
                'status' => $userData['status'],
                'volume' => $formattedVolume,
            ]);
            $shouldNotify = !empty($user['status_cron'] ?? null);
            $sent = $this->send_notifactions($invoice, $shouldNotify, $message, true, $invoice['bottype']);
            $this->sendReportNotification($reportMessage);
            if ($sent) {
                $this->updateInvoiceStatus("volume", $invoice);
            }
            return true;
        }
    }
    private function hasPendingQueuedRenewal($username)
    {
        $username = (string) $username;
        $stmt = $this->pdo->prepare("SELECT id FROM queued_renewal WHERE username = :username AND status IN ('pending', 'processing', 'retry', 'reconciling') LIMIT 1");
        $stmt->execute([':username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private function shouldRemoveService($invoice, $user, $userData, $username)
    {
        if (!in_array($userData['status'], ['limited', 'expired'])) return false;
        $timeService = $userData['expire'] - time();
        $daysRemaining = intval($timeService / 86400);
        $removalThreshold = intval("-" . $this->setting['removedayc']);
        $result =  $daysRemaining <= $removalThreshold;
        $statusText = $statusMap = [
            'active' => $this->textBotLang['users']['stateus']['active'],
            'limited' => $this->textBotLang['users']['stateus']['limited'],
            'disabled' => $this->textBotLang['users']['stateus']['disabled'],
            'expired' => $this->textBotLang['users']['stateus']['expired'],
            'on_hold' => $this->textBotLang['users']['stateus']['on_hold'],
            'Unknown' => $this->textBotLang['users']['stateus']['Unknown']
        ][$userData['status']];
        $remainingVolume = \Faoxima\Core\Support\Formatting::formatBytes($userData['data_limit'] - $userData['used_traffic']);
        if ($result) {
            if (\Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) return null;
            if ($this->hasPendingQueuedRenewal($username)) return false;
            $this->deletionAttempts++;
            $removeRes = $this->Panel->RemoveUser($invoice['Service_location'], $username);
            if (!empty($removeRes['status']) && $removeRes['status'] === 'successful') {
                \Faoxima\Core\Database\Db::update("invoice", "status", "removeTime", "id_invoice", $invoice['id_invoice']);
                $message = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cron_removal_notice_tpl', "📌 کاربر گرامی بدلیل عدم تمدید، سرویس {username} از لیست سرویس های شما حذف گردید\n\n🌟 جهت تهیه سرویس جدید از بخش خرید سرویس اقدام فرمایید"), [
                    'username' => $invoice['username'],
                ]);
                $reportMessage = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cron_removal_report_tpl', "📌 اطلاعیه کرون حذف\n\n<blockquote>نام کاربری سرویس :‌ <code>{username}</code></blockquote>\n<blockquote>وضعیت سرویس : {status}</blockquote>\n<blockquote>تعداد روز باقی مانده ‌:‌{days}</blockquote>\n<blockquote>حجم باقی مانده : {volume}</blockquote>"), [
                    'username' => $invoice['username'],
                    'status' => $statusText,
                    'days' => $daysRemaining,
                    'volume' => $remainingVolume,
                ]);
                $shouldNotify = !empty($user['status_cron'] ?? null);
                $this->send_notifactions($invoice, $shouldNotify, $message, false, $invoice['bottype']);
                $this->sendReportNotification($reportMessage);
                return true;
            } else {
                $errMsg = is_array($removeRes['msg'] ?? null) ? json_encode($removeRes['msg']) : ($removeRes['msg'] ?? 'unknown');
                $errReport = "⚠️ خطای حذف سرویس در کرون زمان\n<blockquote>نام کاربری: <code>{$username}</code></blockquote>\n<blockquote>لوکیشن: {$invoice['Service_location']}</blockquote>\n<blockquote>خطا: {$errMsg}</blockquote>";
                $this->sendReportNotification($errReport);
                return null;
            }
        }
        return false;
    }
    private function shouldRemoveServiceـvolume($invoice, $user, $userData, $username, $panelInfo = null)
    {
        if (!in_array($userData['status'], ['limited', 'expired'])) return false;
        $panel = is_array($panelInfo) ? $panelInfo : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if (!in_array($panel['type'], ["marzban", "rebecca", "pasarguard"], true)) return false;
        if ($userData['data_limit_reset'] != "no_reset") return false;
        if ($userData['status'] == "Unsuccessful") return false;
        if (in_array($userData['status'], ['Unknown', 'active', 'on_hold', 'disabled', 'expired'])) return false;
        if (empty($userData['online_at']) or $userData['online_at'] == null) {
            $timelastconect = 0;
        } else {
            $time = strtotime($userData['online_at']);
            $timelastconect = (time() - $time) / 86400;
        }
        if($timelastconect == 0)return false;
        $timeService = $userData['expire'] - time();
        $daysRemaining = intval($timeService / 86400);
        $removalThreshold = intval($this->setting['cronvolumere']);
        $result =  $timelastconect >= $removalThreshold;
        $statusText = [
            'active' => $this->textBotLang['users']['stateus']['active'],
            'limited' => $this->textBotLang['users']['stateus']['limited'],
            'disabled' => $this->textBotLang['users']['stateus']['disabled'],
            'expired' => $this->textBotLang['users']['stateus']['expired'],
            'on_hold' => $this->textBotLang['users']['stateus']['on_hold'],
            'Unknown' => $this->textBotLang['users']['stateus']['Unknown']
        ][$userData['status']];
        $remainingVolume = \Faoxima\Core\Support\Formatting::formatBytes($userData['data_limit'] - $userData['used_traffic']);
        if ($result) {
            if (\Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) return null;
            if ($this->hasPendingQueuedRenewal($username)) return false;
            $this->deletionAttempts++;
            $removeRes = $this->Panel->RemoveUser($invoice['Service_location'], $username);
            if (!empty($removeRes['status']) && $removeRes['status'] === 'successful') {
                \Faoxima\Core\Database\Db::update("invoice", "status", "removevolume", "id_invoice", $invoice['id_invoice']);
                $message = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cron_removal_volume_notice_tpl', "📌 کاربر گرامی بدلیل عدم تمدید، سرویس {username} از لیست سرویس های شما حذف گردید\n\n🌟 جهت تهیه سرویس جدید از بخش خرید سرویس اقدام فرمایید"), [
                    'username' => $username,
                ]);
                $reportMessage = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cron_removal_volume_report_tpl', "📌  اطلاعیه کرون حذف حجم \n<blockquote>نام کاربری سرویس : {username} </blockquote>\n<blockquote> وضعیت سرویس : {status} </blockquote>\n<blockquote>تعداد روز باقی مانده :{days} </blockquote>\n<blockquote> حجم باقی مانده : {volume}</blockquote>\n<blockquote>آخرین اتصال کاربر : {last_online}</blockquote>"), [
                    'username' => $username,
                    'status' => $statusText,
                    'days' => $daysRemaining,
                    'volume' => $remainingVolume,
                    'last_online' => $userData['online_at'],
                ]);
                $shouldNotify = !empty($user['status_cron'] ?? null);
                $this->send_notifactions($invoice, $shouldNotify, $message, false, $invoice['bottype']);
                $this->sendReportNotification($reportMessage);
                return true;
            } else {
                $errMsg = is_array($removeRes['msg'] ?? null) ? json_encode($removeRes['msg']) : ($removeRes['msg'] ?? 'unknown');
                $errReport = "⚠️ خطای حذف سرویس در کرون حجم\n<blockquote>نام کاربری: <code>{$username}</code></blockquote>\n<blockquote>لوکیشن: {$invoice['Service_location']}</blockquote>\n<blockquote>خطا: {$errMsg}</blockquote>";
                $this->sendReportNotification($errReport);
                return null;
            }
        }
        return false;
    }
    private function active_inbound_expire($invoice, $userData, $panel_info)
    {
        if ($invoice['uuid'] != null || $userData['data_limit_reset'] != "no_reset") return;
        $inbound = explode("*", $panel_info['inbound_deactive']);
        \Faoxima\Core\Database\Db::update("invoice", "uuid", json_encode($userData['uuid']), "id_invoice", $invoice['id_invoice']);
        $proxies = [];
        $proxies[$inbound[0]] = new stdClass();;
        $inbounds[$inbound[0]][] = $inbound[1];
        $configs  = array(
            "proxies" => $proxies,
            "inbounds" => $inbounds
        );
        $this->Panel->Modifyuser($invoice['username'], $panel_info['code_panel'], $configs);
    }
    private function checkTimeExpiration($invoice, $user, $userData, $username)
    {
        $validStatuses = ['expired', 'on_hold', 'limited'];
        if (in_array($userData['status'], $validStatuses)) {
            if ($userData['status'] === 'expired' && ($invoice['Status'] ?? '') !== 'end_of_time') {
                \Faoxima\Core\Database\Db::update("invoice", "Status", "end_of_time", "id_invoice", $invoice['id_invoice']);
            } elseif ($userData['status'] === 'limited' && ($invoice['Status'] ?? '') !== 'end_of_volume') {
                \Faoxima\Core\Database\Db::update("invoice", "Status", "end_of_volume", "id_invoice", $invoice['id_invoice']);
            }
            return;
        }
        $timeRemaining = $userData['expire'] - time();
        $daysRemaining = intval($timeRemaining / self::SECONDS_PER_DAY);
        $warningThreshold = intval($this->setting['daywarn']) * self::SECONDS_PER_DAY;

        $isTimeWarning = $timeRemaining <= $warningThreshold && $timeRemaining > 0;

        if ($isTimeWarning) {
            $message = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cron_time_warning_tpl', "با سلام خدمت شما کاربر گرامی 👋\n📌 از مهلت زمانی سرویس «{plan}» شما فقط {days_display} روز باقی مانده است (شناسه سرویس: {username}). لطفاً در صورت تمایل برای تمدید این سرویس، از طریق بخش «{purchased_services}» اقدام بفرمایین. با تشکر از همراهی شما"), [
                'username' => $username,
                'plan' => $this->getServiceLabel($invoice, $username),
                'days' => $daysRemaining,
                'days_display' => $daysRemaining < 1 ? 'کمتر از ۱' : $daysRemaining,
                'purchased_services' => $this->text_Purchased_services,
            ]);
            $reportMessage = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cron_time_warning_report_tpl', "📌 اطلاعیه کرون زمان\n\n<blockquote>نام کاربری سرویس :‌ <code>{username}</code></blockquote>\n<blockquote>وضعیت سرویس : {status}</blockquote>\n<blockquote>تعداد روز باقی مانده ‌:‌{days}</blockquote>"), [
                'username' => $invoice['username'],
                'status' => $userData['status'],
                'days' => $daysRemaining,
            ]);
            $shouldNotify = !empty($user['status_cron'] ?? null);
            $sent = $this->send_notifactions($invoice, $shouldNotify, $message, true, $invoice['bottype']);
            $this->sendReportNotification($reportMessage);
            if ($sent) {
                $this->updateInvoiceStatus("time", $invoice);
            }
            return true;
        }
    }

    private function send_notifactions($invoice, bool $shouldNotify, $message, $keyboard_active, $bot_token)
    {
        if (!$shouldNotify) {
            return true;
        }
        $keyboard = $this->createExtendServiceKeyboard($invoice['id_invoice']);
        $keyboard = $keyboard_active ? $keyboard : null;
        $result = sendmessage($invoice['id_user'], $message, $keyboard, 'HTML', $bot_token);
        return is_array($result) && ($result['ok'] ?? false) === true;
    }

    private function createExtendServiceKeyboard($invoiceId)
    {
        return json_encode([
            'inline_keyboard' => [
                [
                    rx_cron_btn('cron_extend', ['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cron_extend_service_btn', "💊 تمدید سرویس"), 'callback_data' => 'extend_' . $invoiceId]),
                ],
            ]
        ]);
    }

    private function sendReportNotification($reportMessage)
    {
        if (empty($this->setting['Channel_Report'])) return true;


        $result = telegram('sendmessage', [
            'chat_id' => $this->setting['Channel_Report'],
            'message_thread_id' => $this->reportCron,
            'text' => $reportMessage,
            'parse_mode' => "HTML"
        ]);
        return is_array($result) && ($result['ok'] ?? false) === true;
    }

    private function updateInvoiceStatus($type, $invoice)
    {
        $data = json_decode($invoice['notifctions'], true);
        $data[$type] = true;
        $data = json_encode($data);
        \Faoxima\Core\Database\Db::update("invoice", "notifctions", $data, "id_invoice", $invoice['id_invoice']);
    }
}
