<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('statusday', 240);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

$setting = select('setting', '*', null, null, 'select');

$reportnightRow = select('topicid', 'idreport', 'report', 'reportnight', 'select');
$reportnight    = $reportnightRow['idreport'] ?? null;

if (empty($setting['Channel_Report'])) {
    return;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$datefirst        = date('Y-m-d') . ' 00:00:00';
$dateend          = date('Y-m-d') . ' 23:59:59';
$datefirstextend  = date('Y/m/d') . ' 00:00:00';
$dateendextend    = date('Y/m/d') . ' 23:59:59';

$executeQuery = static function (PDO $pdo, string $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return $stmt;
};

$sqlInvoices = "
    SELECT COUNT(*) AS count, SUM(price_product) AS total_price, SUM(Volume) AS total_volume
    FROM invoice
    WHERE (FROM_UNIXTIME(time_sell) BETWEEN :startDate AND :endDate)
      AND (status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold'))
      AND name_product != 'سرویس تست'
";
$params   = [':startDate' => $datefirst, ':endDate' => $dateend];
$stmt     = $executeQuery($pdo, $sqlInvoices, $params);
$result   = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$dayListSell   = (int)($result['count'] ?? 0);
$suminvoiceday = (int)($result['total_price'] ?? 0);
$sumvolume     = (float)($result['total_volume'] ?? 0);

$sqlTestService = "
    SELECT COUNT(*) AS count
    FROM invoice
    WHERE (FROM_UNIXTIME(time_sell) BETWEEN :startDate AND :endDate)
      AND (status IN ('active', 'end_of_time', 'sendedwarn'))
      AND name_product = 'سرویس تست'
";
$stmt      = $executeQuery($pdo, $sqlTestService, $params);
$dayListSelltest = (int)($stmt->fetchColumn() ?? 0);

$sqlNewUsers = "
    SELECT COUNT(*) AS count
    FROM user
    WHERE (FROM_UNIXTIME(register) BETWEEN :startDate AND :endDate)
";
$stmt   = $executeQuery($pdo, $sqlNewUsers, $params);
$usernew = (int)($stmt->fetchColumn() ?? 0);

$sqlExtensions = "
    SELECT COUNT(*) AS count, SUM(price) AS total_price
    FROM service_other
    WHERE (time BETWEEN :startDate AND :endDate)
      AND type = 'extend_user'
      AND status != 'unpaid'
";
$paramsExtend   = [':startDate' => $datefirstextend, ':endDate' => $dateendextend];
$stmt           = $executeQuery($pdo, $sqlExtensions, $paramsExtend);
$result         = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$countextendday = (int)($result['count'] ?? 0);
$sumcountextend = (int)($result['total_price'] ?? 0);
$sumcountextendFormatted = number_format($sumcountextend);

$sqlTopAgents = "
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
";
$paramsAgents = [
    ':startDate1' => strtotime($datefirstextend),
    ':endDate1'   => strtotime($dateendextend),
    ':startDate2' => strtotime($datefirstextend),
    ':endDate2'   => strtotime($dateendextend),
];
$stmt          = $executeQuery($pdo, $sqlTopAgents, $paramsAgents);
$listagentuser = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$textagent = "لیست نمایندگانی که بیشترین خرید در امروز داشتند :\n";
foreach ($listagentuser as $agent) {
    $textagent .= "\nایدی عددی کاربر : {$agent['id']}\nنام کاربری کاربر : {$agent['username']}\nجمع کل خرید امروز : {$agent['total_spent']}\n---------------\n";
}

$panels    = select('marzban_panel', '*', null, null, 'fetchAll');
$textpanel = "گزارش پنل ها :\n";
if (is_array($panels)) {
    foreach ($panels as $panel) {
        $sqlPanel = "
            SELECT COUNT(*) AS orders, SUM(price_product) AS total_price, SUM(Volume) AS total_volume
            FROM invoice
            WHERE (FROM_UNIXTIME(time_sell) BETWEEN :startDate AND :endDate)
              AND (status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold'))
              AND Service_location = :location
              AND name_product != 'سرویس تست'
        ";
        $paramsPanel = [
            ':startDate' => $datefirst,
            ':endDate'   => $dateend,
            ':location'  => $panel['name_panel'],
        ];
        $stmt        = $executeQuery($pdo, $sqlPanel, $paramsPanel);
        $result      = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $orders      = (int)($result['orders'] ?? 0);
        $total_price = (int)($result['total_price'] ?? 0);
        $total_volume = (float)($result['total_volume'] ?? 0);
        $textpanel  .= "\nنام پنل : {$panel['name_panel']}\n🛍 تعداد سفارشات امروز : {$orders} عدد\n🛍 جمع مبلغ سفارشات امروز : {$total_price} تومان\n🔋 جمع حجم های فروخته شده : {$total_volume} گیگابایت\n---------------\n";
    }
}

$textreport = "📌 گزارش روزانه کارکرد ربات :\n\n"
    . "🧲 تعداد تمدید امروز : {$countextendday} عدد\n"
    . "💰 جمع تمدید امروز : {$sumcountextendFormatted} تومان\n"
    . "🛍 تعداد سفارشات امروز : {$dayListSell} عدد\n"
    . "🛍 جمع مبلغ سفارشات امروز : {$suminvoiceday} تومان\n"
    . "🔑 اکانت های تست امروز : {$dayListSelltest} عدد\n"
    . "🔋 جمع حجم های فروخته شده : {$sumvolume} گیگابایت\n"
    . "تعداد کاربرانی که امروز به ربات پیوستند : {$usernew} نفر\n";

$chatId      = $setting['Channel_Report'];
$report_data = [
    ['text' => $textagent],
    ['text' => $textreport],
    ['text' => $textpanel],
];
foreach ($report_data as $report) {
    telegram('sendmessage', [
        'chat_id'           => $chatId,
        'message_thread_id' => $reportnight,
        'text'              => $report['text'],
        'parse_mode'        => 'HTML',
    ]);
}

?>


