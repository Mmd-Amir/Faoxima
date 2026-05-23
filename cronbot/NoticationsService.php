<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('notifications', 600);

ini_set('error_log', 'error_log');

if (!rx_cron_require_or_skip('notifications', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('notifications')) {
    return;
}

class ServiceMonitor
{
    private $Panel;
    private $pdo;
    private $setting;
    private $reportCron;
    private $text_Purchased_services;
    private $status_cron;
    const SECONDS_PER_DAY = 86400;
    private $textBotLang;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->Panel = new ManagePanel();
        $this->reportCron = select("topicid", "idreport", "report", "reportcron", "select")['idreport'];
        $this->setting = select("setting", "*");
        $this->status_cron = json_decode($this->setting['cron_status'], true);
        $this->text_Purchased_services = select("textbot", "text", "id_text", "text_Purchased_services", "select")['text'];
        $this->textBotLang = languagechange('../text.json');
    }

    public function RunNotifactions()
    {
        $invoices = $this->getActiveInvoices();
        if ($invoices == false) return;
        foreach ($invoices as $invoice) {
            if ($invoice['time_cron'] != null) {
                $time_cron = time() - $invoice['time_cron'];
                if ($time_cron < 1600) continue;
            }
            update("invoice", "time_cron", time(), "id_invoice", $invoice['id_invoice']);
            $check_send = json_decode($invoice['notifctions'], true);
            $data = $this->processInvoice($invoice);
            if (!is_array($data)) continue;
            $result = false;
            if (!$check_send['volume']) {
                if ($this->status_cron['volume']) $result = $this->checkVolumeThreshold($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
            }
            if ($result) $data['invoice'] = select("invoice", "*", "id_invoice", $invoice['id_invoice']);
            if (!$check_send['time']) {
                if ($this->status_cron['day']) $this->checkTimeExpiration($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
            }
            if ($this->status_cron['remove']) $this->shouldRemoveService($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
            if ($this->status_cron['remove_volume']) $this->shouldRemoveServiceـvolume($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
            if ($data['panel']['inboundstatus'] == "oninbounddisable" && $data['panel']['type'] == "marzban") $this->active_inbound_expire($data['invoice'],  $data['userData'], $data['panel']);
        }
    }


    private function getActiveInvoices()
    {
        $time_hours = time() - 3600;
        $QUERY = "SELECT * FROM invoice WHERE (Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume' OR Status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND (time_cron <= '$time_hours' OR time_cron IS NULL) ORDER BY time_cron  LIMIT 30";
        $stmt = $this->pdo->prepare($QUERY);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function processInvoice($invoice)
    {
        $username = $invoice['username'];


        $panelInfo = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if (!$panelInfo) return false;


        $user = select("user", "*", "id", $invoice['id_user'], "select");
        if ($user == false) return false;


        $userData = $this->Panel->DataUser($invoice['Service_location'], $username);
        if (!$userData || $userData['status'] == "Unsuccessful") return;
        return [
            'invoice' => $invoice,
            'panel' => $panelInfo,
            'user' => $user,
            'userData' => $userData
        ];
    }

    private function checkVolumeThreshold($invoice, $user, $userData, $username)
    {
        $remainingVolume = $userData['data_limit'] - $userData['used_traffic'];
        $volumeWarningThreshold = $this->setting['volumewarn'] * pow(1024, 3);
        $isVolumeWarning = $remainingVolume <= $volumeWarningThreshold && $remainingVolume > 0 && in_array($userData['status'], ['active', 'Unknown']);

        if ($isVolumeWarning) {
            $formattedVolume = formatBytes($remainingVolume);
            $message = "با سلام خدمت شما کاربر گرامی 👋\n" .
                "🚨 از حجم سرویس {$username} تنها {$formattedVolume} باقی مانده است. " .
                "لطفاً در صورت تمایل برای خرید حجم اضافه و یا تمدید سرویستون از طریق بخش «{$this->text_Purchased_services}» اقدام بفرمایین";
            $reportMessage = "📌 اطلاعیه کرون حجم\n\n" .
                "نام کاربری سرویس :‌ <code>{$username}</code>\n" .
                "وضعیت سرویس : {$userData['status']}\n" .
                "حجم باقی مانده : {$formattedVolume}";
            $shouldNotify = !empty($user['status_cron'] ?? null);
            $this->send_notifactions($invoice, $shouldNotify, $message, true, $invoice['bottype']);
            $this->sendReportNotification($reportMessage);
            $this->updateInvoiceStatus("volume", $invoice);
            return true;
        }
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
        $remainingVolume = formatBytes($userData['data_limit'] - $userData['used_traffic']);
        if ($result) {
            update("invoice", "status", "removeTime", "username", $username);
            $this->Panel->RemoveUser($invoice['Service_location'], $username);
            $message = "📌 کاربر گرامی بدلیل عدم تمدید، سرویس {$invoice['username']} از لیست سرویس های شما حذف گردید\n\n🌟 جهت تهیه سرویس جدید از بخش خرید سرویس اقدام فرمایید";
            $reportMessage = "📌 اطلاعیه کرون حذف\n\nنام کاربری سرویس :‌ <code>{$invoice['username']}</code>\nوضعیت سرویس : $statusText\nتعداد روز باقی مانده ‌:‌$daysRemaining\nحجم باقی مانده : $remainingVolume";
            $shouldNotify = !empty($user['status_cron'] ?? null);
            $this->send_notifactions($invoice, $shouldNotify, $message, false, $invoice['bottype']);
            $this->sendReportNotification($reportMessage);
        }
    }
    private function shouldRemoveServiceـvolume($invoice, $user, $userData, $username)
    {
        if (!in_array($userData['status'], ['limited', 'expired'])) return false;
        $panel = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if ($panel['type'] != "marzban") return;
        if ($userData['data_limit_reset'] != "no_reset") return;
        if ($userData['status'] == "Unsuccessful") return;
        if (in_array($userData['status'], ['Unknown', 'active', 'on_hold', 'disabled', 'expired'])) return;
        if (empty($userData['online_at']) or $userData['online_at'] == null) {
            $timelastconect = 0;
        } else {
            $time = strtotime($userData['online_at']);
            $timelastconect = (time() - $time) / 86400;
        }
        if($timelastconect == 0)return;
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
        $remainingVolume = formatBytes($userData['data_limit'] - $userData['used_traffic']);
        if ($result) {
            update("invoice", "status", "removevolume", "username", $username);
            $this->Panel->RemoveUser($invoice['Service_location'], $username);
            $message = "📌 کاربر گرامی بدلیل عدم تمدید، سرویس $username از لیست سرویس های شما حذف گردید

🌟 جهت تهیه سرویس جدید از بخش خرید سرویس اقدام فرمایید";
            $reportMessage = "📌  اطلاعیه کرون حذف حجم \nنام کاربری سرویس : $username \n وضعیت سرویس : $statusText \nتعداد روز باقی مانده :$daysRemaining \n حجم باقی مانده : $remainingVolume\nآخرین اتصال کاربر : {$userData['online_at']}";
            $shouldNotify = !empty($user['status_cron'] ?? null);
            $this->send_notifactions($invoice, $shouldNotify, $message, false, $invoice['bottype']);
            $this->sendReportNotification($reportMessage);
        }
    }
    private function active_inbound_expire($invoice, $userData, $panel_info)
    {
        if ($invoice['uuid'] != null || $userData['data_limit_reset'] != "no_reset") return;
        $inbound = explode("*", $panel_info['inbound_deactive']);
        update("invoice", "uuid", json_encode($userData['uuid']), "id_invoice", $invoice['id_invoice']);
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
        if (in_array($userData['status'], $validStatuses)) return;
        $timeRemaining = $userData['expire'] - time();
        $daysRemaining = intval($timeRemaining / self::SECONDS_PER_DAY);
        $warningThreshold = intval($this->setting['daywarn']) * self::SECONDS_PER_DAY;

        $isTimeWarning = $timeRemaining <= $warningThreshold && $timeRemaining > 0;

        if ($isTimeWarning) {
            $message = "با سلام خدمت شما کاربر گرامی 👋\n" .
                "📌 از مهلت زمانی استفاده از سرویس {$username} فقط {$daysRemaining} روز باقی مانده است. " .
                "لطفاً در صورت تمایل برای تمدید این سرویس، از طریق بخش «{$this->text_Purchased_services}» اقدام بفرمایین. " .
                "با تشکر از همراهی شما";
            $reportMessage = "📌 اطلاعیه کرون زمان\n\n" .
                "نام کاربری سرویس :‌ <code>{$invoice['username']}</code>\n" .
                "وضعیت سرویس : {$userData['status']}\n" .
                "تعداد روز باقی مانده ‌:‌{$daysRemaining}";
            $shouldNotify = !empty($user['status_cron'] ?? null);
            $this->send_notifactions($invoice, $shouldNotify, $message, true, $invoice['bottype']);
            $this->sendReportNotification($reportMessage);
            $this->updateInvoiceStatus("time", $invoice);
            return true;
        }
    }

    private function send_notifactions($invoice, bool $shouldNotify, $message, $keyboard_active, $bot_token)
    {
        if (!$shouldNotify) {
            return;
        }
        $keyboard = $this->createExtendServiceKeyboard($invoice['id_invoice']);
        $keyboard = $keyboard_active ? $keyboard : null;
        sendmessage($invoice['id_user'], $message, $keyboard, 'HTML', $bot_token);
    }

    private function createExtendServiceKeyboard($invoiceId)
    {
        return json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "💊 تمدید سرویس", 'callback_data' => 'extend_' . $invoiceId],
                ],
            ]
        ]);
    }

    private function sendReportNotification($reportMessage)
    {
        if (empty($this->setting['Channel_Report'])) return;


        telegram('sendmessage', [
            'chat_id' => $this->setting['Channel_Report'],
            'message_thread_id' => $this->reportCron,
            'text' => $reportMessage,
            'parse_mode' => "HTML"
        ]);
    }

    private function updateInvoiceStatus($type, $invoice)
    {
        $data = json_decode($invoice['notifctions'], true);
        $data[$type] = true;
        $data = json_encode($data);
        update("invoice", "notifctions", $data, "id_invoice", $invoice['id_invoice']);
    }
}


$volumeMonitor = new ServiceMonitor();
$volumeMonitor->RunNotifactions();

