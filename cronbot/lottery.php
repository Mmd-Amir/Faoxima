<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('lottery', 120);

ini_set('error_log', 'error_log');

if (!rx_cron_require_or_skip('lottery', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../function.php',
    __DIR__ . '/../jdf.php',
])) {
    return;
}
if (!rx_cron_db_ready('lottery')) {
    return;
}
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

$setting = select("setting", "*");

if (!$setting || !isset($setting['scorestatus'])) {

    $__settingMarker = __DIR__ . '/_setting_missing.flag';
    $__age = is_file($__settingMarker) ? (time() - (int) @filemtime($__settingMarker)) : 99999;
    if ($__age > 3600) {
        error_log('[cron:lottery] Setting data is missing or incomplete — check setting table.');
        @touch($__settingMarker);
    }
    exit;
}

if (intval($setting['scorestatus']) == 1) {
    $otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];

    $temp = [];
    $Lottery_prize = json_decode($setting['Lottery_prize'], true);
    if (!is_array($Lottery_prize)) {
        error_log("Lottery_prize is not a valid JSON array.");
        exit;
    }
    foreach ($Lottery_prize as $lottery) {
        $temp[] = $lottery;
    }
    $Lottery_prize = $temp;

    if ($setting['Lotteryagent'] == "1") {
        $stmt = $pdo->prepare("SELECT * FROM user WHERE User_Status = 'Active' AND score != '0' ORDER BY score DESC LIMIT 3");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM user WHERE User_Status = 'Active' AND score != '0' AND agent = 'f' ORDER BY score DESC LIMIT 3");
    }
    $stmt->execute();

    $count = 0;
    $textlotterygroup = "📌 ادمین عزیز کاربران زیر برنده قرعه کشی و حسابشان شارژ گردید.\n";

    $textJson = json_decode(file_get_contents('../text.json'), true);
    if (!is_array($textJson)) {
        error_log("text.json is not a valid JSON file.");
        exit;
    }

    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userLang = isset($result['language']) && !empty($result['language']) ? $result['language'] : 'fa';
        if (!isset($textJson[$userLang])) {
            $userLang = 'fa';
        }
        $textbotlang = $textJson[$userLang];

        if (!isset($Lottery_prize[$count])) {
            error_log("No prize defined for rank " . ($count + 1));
            break;
        }

        $prizeAmount = intval($Lottery_prize[$count]);


        $atomic = $pdo->prepare("UPDATE user SET Balance = Balance + :prize, score = 0 WHERE id = :id AND score > 0");
        $atomic->execute([
            ':prize' => $prizeAmount,
            ':id'    => $result['id'],
        ]);
        if ($atomic->rowCount() !== 1) {

            $count++;
            continue;
        }

        $balanceFormatted = number_format($prizeAmount);
        $rank = $count + 1;

        $textlottery = "🎁 نتیجه قرعه کشی \n\n😎 کاربر عزیز تبریک! شما نفر $rank برنده $balanceFormatted تومان موجودی شدید و حساب شما شارژ گردید.";
        sendmessage($result['id'], $textlottery, null, 'html');

        $textlotterygroup .= "\nنام کاربری : @{$result['username']}\nآیدی عددی : {$result['id']}\nمبلغ : $balanceFormatted\nنفر : $rank\n---------------\n";

        $count++;
    }

    telegram('sendmessage', [
        'chat_id'           => $setting['Channel_Report'],
        'message_thread_id' => $otherreport,
        'text'              => $textlotterygroup,
        'parse_mode'        => "HTML",
    ]);


    update("user", "score", "0", null, null);
}

