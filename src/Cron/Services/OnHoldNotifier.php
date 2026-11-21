<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Services\Panels\Drivers\Marzban;
use Faoxima\Services\Panels\Drivers\PasarGuard;
use Faoxima\Services\Panels\Drivers\Rebecca;
use PDO;

final class OnHoldNotifier
{
    private PDO $pdo;
    private $setting;

    public function __construct(PDO $pdo, $setting)
    {
        $this->pdo = $pdo;
        $this->setting = $setting;
    }

    public function run(): void
    {
        $this->scanMarzban();
        if (method_exists(PasarGuard::class, 'pasarguardGetUsersByStatus')) {
            $this->scan('pasarguard', static function (string $panel): array {
                $result = PasarGuard::pasarguardGetUsersByStatus($panel, 'on_hold');
                return (!empty($result['status']) && is_array($result['users'] ?? null)) ? $result['users'] : [];
            });
        }
        if (method_exists(Rebecca::class, 'rebeccaGetOnHoldUsers')) {
            $this->scan('rebecca', static function (string $panel): array {
                $result = Rebecca::rebeccaGetOnHoldUsers($panel);
                return (!empty($result['status']) && is_array($result['users'] ?? null)) ? $result['users'] : [];
            });
        }
        if (CronRuntime::rx_cron_time_up()) {
            TaskState::rx_cron_task_defer('time_budget', 60);
        } else {
            TaskState::rx_cron_task_complete('on_hold_finished');
        }
    }

    private function panels(string $type): \PDOStatement
    {
        $stmt = $this->pdo->prepare("SELECT * FROM marzban_panel WHERE type = '" . $type . "' ORDER BY RAND() LIMIT 25");
        $stmt->execute();
        return $stmt;
    }

    private function scanMarzban(): void
    {
        $panels = $this->panels('marzban');
        while ($panel = $panels->fetch(PDO::FETCH_ASSOC)) {
            if (CronRuntime::rx_cron_time_up()) {
                TaskState::rx_cron_task_defer('time_budget', 60);
                break;
            }
            $users = Marzban::getusers($panel['name_panel'], "on_hold")['users'] ?? null;
            if (!is_array($users)) {
                continue;
            }
            foreach ($users as $user) {
                if (CronRuntime::rx_cron_time_up()) {
                    break 2;
                }
                if (($user['status'] ?? '') === "Unsuccessful") {
                    continue;
                }
                $this->notify($user['username'], $user['status'] ?? '');
            }
        }
    }

    private function scan(string $type, callable $fetchUsers): void
    {
        $panels = $this->panels($type);
        while ($panel = $panels->fetch(PDO::FETCH_ASSOC)) {
            if (CronRuntime::rx_cron_time_up()) {
                break;
            }
            foreach ($fetchUsers($panel['name_panel']) as $user) {
                if (CronRuntime::rx_cron_time_up()) {
                    break 2;
                }
                $this->notify($user['username'] ?? '', $user['status'] ?? '');
            }
        }
    }

    private function notify($username, $status): void
    {
        if ($status !== 'on_hold') {
            return;
        }
        $setting = $this->setting;
        $invoice = Db::select("invoice", "*", "username", $username, "select");
        if ($invoice == false) {
            return;
        }
        if ($invoice['Status'] == "send_on_hold") {
            return;
        }
        $daysSinceBuy = (time() - $invoice['time_sell']) / 86400;
        if ($daysSinceBuy < $setting['on_hold_day']) {
            return;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM service_other WHERE username = :username AND type = 'change_location'");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->rowCount() != 0) {
            return;
        }
        $text = "سلام! 🌐\r\n\r\nدیدیم که شما هنوز به کانفیگ خود با نام کاربری $username متصل نشده‌اید و بیش از {$setting['on_hold_day']} روز از فعال‌سازی آن گذشته است. اگر در راه‌اندازی یا استفاده از سرویس مشکلی دارید، لطفاً با تیم پشتیبانی ما  از طریق آیدی زیر در ارتباط باشید تا به شما کمک کنیم.\r\nما آماده‌ایم تا هر گونه سوال یا مشکلی را برطرف کنیم! 📞\r\n\r\nاکانت پشتیبانی : @{$setting['id_support']}";
        sendmessage($invoice['id_user'], $text, null, 'HTML');
        Db::update("invoice", "Status", "send_on_hold", "username", $username);
    }
}
