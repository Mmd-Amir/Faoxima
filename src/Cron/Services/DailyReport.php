<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use PDO;

final class DailyReport
{
    private PDO $pdo;
    private string $start;
    private string $end;
    private string $startExtend;
    private string $endExtend;

    public function __construct(PDO $pdo, int $reportTimestamp)
    {
        $this->pdo = $pdo;
        $this->start = date('Y-m-d', $reportTimestamp) . ' 00:00:00';
        $this->end = date('Y-m-d', $reportTimestamp) . ' 23:59:59';
        $this->startExtend = date('Y/m/d', $reportTimestamp) . ' 00:00:00';
        $this->endExtend = date('Y/m/d', $reportTimestamp) . ' 23:59:59';
    }

    public static function referenceTimestamp(int $occurrence): int
    {
        $reference = $occurrence > 0 ? $occurrence : time();
        return ((int) date('G', $reference) < 12) ? strtotime('-1 day', $reference) : $reference;
    }

    private function run(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt;
    }

    public function messages(): array
    {
        $params = [':startDate' => $this->start, ':endDate' => $this->end];

        $sold = $this->run("
            SELECT COUNT(*) AS count, SUM(price_product) AS total_price, SUM(Volume) AS total_volume
            FROM invoice
            WHERE (FROM_UNIXTIME(time_sell) BETWEEN :startDate AND :endDate)
              AND (status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold'))
              AND name_product != 'سرویس تست'
        ", $params)->fetch(PDO::FETCH_ASSOC) ?: [];
        $orders = (int) ($sold['count'] ?? 0);
        $revenue = (int) ($sold['total_price'] ?? 0);
        $volume = (float) ($sold['total_volume'] ?? 0);

        $tests = (int) ($this->run("
            SELECT COUNT(*) AS count
            FROM invoice
            WHERE (FROM_UNIXTIME(time_sell) BETWEEN :startDate AND :endDate)
              AND (status IN ('active', 'end_of_time', 'sendedwarn'))
              AND name_product = 'سرویس تست'
        ", $params)->fetchColumn() ?? 0);

        $newUsers = (int) ($this->run("
            SELECT COUNT(*) AS count
            FROM user
            WHERE (FROM_UNIXTIME(register) BETWEEN :startDate AND :endDate)
        ", $params)->fetchColumn() ?? 0);

        $extensions = $this->run("
            SELECT COUNT(*) AS count, SUM(price) AS total_price
            FROM service_other
            WHERE (time BETWEEN :startDate AND :endDate)
              AND type = 'extend_user'
              AND status != 'unpaid'
        ", [':startDate' => $this->startExtend, ':endDate' => $this->endExtend])->fetch(PDO::FETCH_ASSOC) ?: [];
        $extendCount = (int) ($extensions['count'] ?? 0);
        $extendTotal = number_format((int) ($extensions['total_price'] ?? 0));

        return [
            ['text' => $this->agentSection()],
            ['text' => "📌 گزارش روزانه کارکرد ربات :\n\n"
                . "🧲 تعداد تمدید امروز : {$extendCount} عدد\n"
                . "💰 جمع تمدید امروز : {$extendTotal} تومان\n"
                . "🛍 تعداد سفارشات امروز : {$orders} عدد\n"
                . "🛍 جمع مبلغ سفارشات امروز : {$revenue} تومان\n"
                . "🔑 اکانت های تست امروز : {$tests} عدد\n"
                . "🔋 جمع حجم های فروخته شده : {$volume} گیگابایت\n"
                . "تعداد کاربرانی که امروز به ربات پیوستند : {$newUsers} نفر\n"],
            ['text' => $this->panelSection($params)],
        ];
    }

    private function agentSection(): string
    {
        $from = strtotime($this->startExtend);
        $to = strtotime($this->endExtend);
        $agents = $this->run("
            SELECT u.id, u.username,
                   (SELECT SUM(i.price_product)
                    FROM invoice i
                    WHERE i.id_user = u.id
                      AND (i.time_sell BETWEEN :startDate1 AND :endDate1)
                      AND i.status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold')) AS total_spent
            FROM user u
            WHERE u.agent IN ('n', 'n2')
              AND EXISTS (
                    SELECT 1
                    FROM invoice i
                    WHERE i.id_user = u.id
                      AND (i.time_sell BETWEEN :startDate2 AND :endDate2)
                      AND i.status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold')
              )
            ORDER BY total_spent DESC
            LIMIT 3
        ", [
            ':startDate1' => $from,
            ':endDate1'   => $to,
            ':startDate2' => $from,
            ':endDate2'   => $to,
        ])->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $text = "لیست نمایندگانی که بیشترین خرید در امروز داشتند :\n";
        foreach ($agents as $agent) {
            $text .= "\n<blockquote>ایدی عددی کاربر : {$agent['id']}</blockquote>\n<blockquote>نام کاربری کاربر : {$agent['username']}</blockquote>\n<blockquote>جمع کل خرید امروز : {$agent['total_spent']}</blockquote>\n---------------\n";
        }
        return $text;
    }

    private function panelSection(array $params): string
    {
        $panels = Db::select('marzban_panel', 'name_panel', null, null, 'fetchAll');
        $stmt = $this->run("
            SELECT Service_location AS location, COUNT(*) AS orders, SUM(price_product) AS total_price, SUM(Volume) AS total_volume
            FROM invoice
            WHERE (FROM_UNIXTIME(time_sell) BETWEEN :startDate AND :endDate)
              AND (status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold'))
              AND name_product != 'سرویس تست'
            GROUP BY Service_location
        ", $params);
        $totals = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $totals[strtolower((string) ($row['location'] ?? ''))] = $row;
        }
        $text = "گزارش پنل ها :\n";
        if (is_array($panels)) {
            foreach ($panels as $panel) {
                $row = $totals[strtolower((string) $panel['name_panel'])] ?? [];
                $orders = (int) ($row['orders'] ?? 0);
                $totalPrice = (int) ($row['total_price'] ?? 0);
                $totalVolume = (float) ($row['total_volume'] ?? 0);
                $text .= "\nنام پنل : {$panel['name_panel']}\n🛍 تعداد سفارشات امروز : {$orders} عدد\n🛍 جمع مبلغ سفارشات امروز : {$totalPrice} تومان\n🔋 جمع حجم های فروخته شده : {$totalVolume} گیگابایت\n---------------\n";
            }
        }
        return $text;
    }
}
