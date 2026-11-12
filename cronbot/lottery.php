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
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('setting_unavailable', 300);
    }
    return;
}

if (intval($setting['scorestatus']) === 0) {
    if (function_exists('rx_cron_task_complete')) {
        rx_cron_task_complete('lottery_not_requested');
    }
    return;
}

$Lottery_prize = json_decode($setting['Lottery_prize'], true);
if (!is_array($Lottery_prize) || empty($Lottery_prize)) {
    error_log("Lottery_prize is not a valid JSON array.");
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('lottery_prizes_invalid', 600);
    }
    return;
}
$textJson = json_decode(file_get_contents(__DIR__ . '/../text.json'), true);
if (!is_array($textJson)) {
    error_log("text.json is not a valid JSON file.");
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('lottery_text_invalid', 600);
    }
    return;
}

$rxLotteryOccurrence = function_exists('rx_cron_task_occurrence') ? rx_cron_task_occurrence(time()) : time();
$rxLotteryRunDate = date('Y-m-d', $rxLotteryOccurrence > 0 ? $rxLotteryOccurrence : time());
$rxLotteryRuntimeDir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
if (!is_dir($rxLotteryRuntimeDir)) {
    @mkdir($rxLotteryRuntimeDir, 0775, true);
}
$rxLotteryStateFile = $rxLotteryRuntimeDir . DIRECTORY_SEPARATOR . 'lottery_' . $rxLotteryRunDate . '.json';
$rxLotteryLock = @fopen($rxLotteryStateFile . '.lock', 'c');
if ($rxLotteryLock === false || !@flock($rxLotteryLock, LOCK_EX | LOCK_NB)) {
    if (is_resource($rxLotteryLock)) {
        @fclose($rxLotteryLock);
    }
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('lottery_locked', 120);
    }
    return;
}

try {
    $ledgerIndex = $pdo->query("SHOW INDEX FROM wallet_ledger WHERE Column_name = 'fulfillment_key' AND Non_unique = 0")->fetch(PDO::FETCH_ASSOC);
    $engineStmt = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('user','wallet_ledger')");
    $transactional = [];
    foreach ($engineStmt->fetchAll(PDO::FETCH_ASSOC) as $engineRow) {
        $transactional[(string) $engineRow['TABLE_NAME']] = strtolower((string) $engineRow['ENGINE']) === 'innodb';
    }
    if (!is_array($ledgerIndex) || empty($transactional['user']) || empty($transactional['wallet_ledger'])) {
        rx_cron_task_defer('lottery_transactional_ledger_unavailable', 600);
        return;
    }
    $rxLotteryClaimed = false;
    $rxLotteryStatus = (int) $setting['scorestatus'];
    if ($rxLotteryStatus === 1) {
        $lotteryClaim = $pdo->prepare("UPDATE setting SET scorestatus = 2 WHERE scorestatus = 1");
        $lotteryClaim->execute();
        $rxLotteryClaimed = $lotteryClaim->rowCount() > 0;
    }
    if (!$rxLotteryClaimed && (int) ($pdo->query('SELECT scorestatus FROM setting LIMIT 1')->fetchColumn() ?: 0) !== 2) {
        if (function_exists('rx_cron_task_complete')) {
            rx_cron_task_complete('lottery_claim_lost');
        }
        return;
    }

    $rxLotteryState = null;
    if (is_file($rxLotteryStateFile)) {
        $rxLotteryState = json_decode((string) @file_get_contents($rxLotteryStateFile), true);
    }
    if (!is_array($rxLotteryState) && !$rxLotteryClaimed && $rxLotteryStatus === 2) {
        $rxLotteryCandidates = @glob($rxLotteryRuntimeDir . DIRECTORY_SEPARATOR . 'lottery_*.json');
        if (is_array($rxLotteryCandidates) && !empty($rxLotteryCandidates)) {
            usort($rxLotteryCandidates, static function ($a, $b) {
                return (int) @filemtime($b) <=> (int) @filemtime($a);
            });
            foreach ($rxLotteryCandidates as $rxLotteryCandidate) {
                $rxLotteryRecovered = json_decode((string) @file_get_contents($rxLotteryCandidate), true);
                if (is_array($rxLotteryRecovered) && empty($rxLotteryRecovered['finished']) && isset($rxLotteryRecovered['awards'])) {
                    $rxLotteryState = $rxLotteryRecovered;
                    $rxLotteryStateFile = $rxLotteryCandidate;
                    break;
                }
            }
        }
    }
    if (!is_array($rxLotteryState) && !$rxLotteryClaimed && $rxLotteryStatus === 2) {
        if (function_exists('rx_cron_task_uncertain')) {
            rx_cron_task_uncertain('lottery_state_missing', 1800);
        }
        return;
    }
    if (is_array($rxLotteryState) && empty($rxLotteryState['ledger_version'])) {
        foreach ((array) ($rxLotteryState['awards'] ?? []) as $legacyAward) {
            if (!in_array((string) ($legacyAward['state'] ?? ''), ['awarded', 'skipped'], true)) {
                rx_cron_task_uncertain('legacy_lottery_awards_require_review', 1800);
                return;
            }
        }
    }
    if (!is_array($rxLotteryState)) {
        if ($setting['Lotteryagent'] == "1") {
            $stmt = $pdo->prepare("SELECT id, username, language FROM user WHERE User_Status = 'Active' AND score != '0' ORDER BY score DESC LIMIT 3");
        } else {
            $stmt = $pdo->prepare("SELECT id, username, language FROM user WHERE User_Status = 'Active' AND score != '0' AND agent = 'f' ORDER BY score DESC LIMIT 3");
        }
        $stmt->execute();
        $winnerRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $awards = [];
        $rank = 0;
        foreach ($winnerRows as $winnerRow) {
            if (!isset($Lottery_prize[$rank])) {
                break;
            }
            $awards[] = [
                'rank' => $rank + 1,
                'user_id' => (string) $winnerRow['id'],
                'username' => (string) $winnerRow['username'],
                'language' => (string) ($winnerRow['language'] ?? 'fa'),
                'prize' => (int) $Lottery_prize[$rank],
                'state' => 'pending',
                'notified' => false,
            ];
            $rank++;
        }
        $rxLotteryState = [
            'ledger_version' => 1,
            'occurrence' => $rxLotteryOccurrence,
            'date' => $rxLotteryRunDate,
            'awards' => $awards,
            'reset_done' => false,
            'report_sent' => false,
        ];
        $tmp = $rxLotteryStateFile . '.tmp';
        if (@file_put_contents($tmp, json_encode($rxLotteryState, JSON_UNESCAPED_UNICODE), LOCK_EX) === false || !@rename($tmp, $rxLotteryStateFile)) {
            @unlink($tmp);
            try {
                $pdo->prepare("UPDATE setting SET scorestatus = 1 WHERE scorestatus = 2")->execute();
            } catch (Throwable $e) {
            }
            if (function_exists('rx_cron_task_uncertain')) {
                rx_cron_task_uncertain('lottery_state_persist_failed', 600);
            }
            return;
        }
    }

    $rxLotterySaveState = function () use (&$rxLotteryState, &$rxLotteryStateFile): void {
        $tmp = $rxLotteryStateFile . '.tmp';
        $encoded = json_encode($rxLotteryState, JSON_UNESCAPED_UNICODE);
        if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $rxLotteryStateFile)) {
            throw new RuntimeException('lottery_state_persist_failed');
        }
    };
    $otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
    $awardsDone = true;
    $textlotterygroup = "📌 ادمین عزیز کاربران زیر برنده قرعه کشی و حسابشان شارژ گردید.\n";

    foreach ($rxLotteryState['awards'] as $index => $award) {
        if (rx_cron_time_up()) {
            $awardsDone = false;
            break;
        }
        $rank = (int) ($award['rank'] ?? 0);
        $userId = (string) ($award['user_id'] ?? '');
        $prizeAmount = (int) ($award['prize'] ?? 0);
        if (($award['state'] ?? '') === 'skipped') {
            continue;
        }
        if ($rank <= 0 || $userId === '' || $prizeAmount < 0) {
            $rxLotteryState['awards'][$index]['state'] = 'skipped';
            $rxLotterySaveState();
            continue;
        }
        if (($award['state'] ?? '') !== 'awarded') {
            try {
                $pdo->beginTransaction();
                $userStmt = $pdo->prepare('SELECT Balance FROM user WHERE id = ? FOR UPDATE');
                $userStmt->execute([$userId]);
                $balance = $userStmt->fetchColumn();
                if ($balance === false) {
                    $pdo->commit();
                    $rxLotteryState['awards'][$index]['state'] = 'skipped';
                    $rxLotterySaveState();
                    continue;
                }
                $ledgerKey = 'cron:lottery:' . (string) $rxLotteryState['date'] . ':' . $userId;
                $ledgerStmt = $pdo->prepare('SELECT id FROM wallet_ledger WHERE fulfillment_key = ? LIMIT 1');
                $ledgerStmt->execute([$ledgerKey]);
                if ($ledgerStmt->fetchColumn() === false) {
                    $ledger = $pdo->prepare("INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, fulfillment_key) VALUES (?, 'credit', ?, ?, 'lottery', ?, ?)");
                    $ledger->execute([$userId, $prizeAmount, (int) $balance + $prizeAmount, 'جایزه قرعه‌کشی رتبه ' . $rank, $ledgerKey]);
                    $atomic = $pdo->prepare('UPDATE user SET Balance = Balance + ?, score = 0 WHERE id = ?');
                    $atomic->execute([$prizeAmount, $userId]);
                }
                $pdo->commit();
                $rxLotteryState['awards'][$index]['state'] = 'awarded';
                $rxLotterySaveState();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[cron:lottery] award persistence failed for rank ' . $rank . ': ' . $e->getMessage());
                $awardsDone = false;
                continue;
            }
        }
        $balanceFormatted = number_format($prizeAmount);
        $textlotterygroup .= "\n<blockquote>نام کاربری : @{$award['username']}</blockquote>\n<blockquote>آیدی عددی : {$userId}</blockquote>\n<blockquote>مبلغ : $balanceFormatted</blockquote>\n<blockquote>نفر : $rank</blockquote>\n---------------\n";
        $notificationState = (string) ($rxLotteryState['awards'][$index]['notification_state'] ?? 'pending');
        if (empty($rxLotteryState['awards'][$index]['notified']) && $notificationState === 'pending' && (int) ($rxLotteryState['awards'][$index]['notify_after'] ?? 0) <= time()) {
            $rxLotteryState['awards'][$index]['notification_state'] = 'sending';
            $rxLotteryState['awards'][$index]['notify_attempts'] = (int) ($rxLotteryState['awards'][$index]['notify_attempts'] ?? 0) + 1;
            $rxLotterySaveState();
            $textlottery = "🎁 نتیجه قرعه کشی \n\n😎 کاربر عزیز تبریک! شما نفر $rank برنده $balanceFormatted تومان موجودی شدید و حساب شما شارژ گردید.";
            try {
                $notifyResult = telegram('sendmessage', ['chat_id' => $userId, 'text' => $textlottery, 'parse_mode' => 'HTML', '_rx_no_network_retry' => true]);
            } catch (Throwable $e) {
                $notifyResult = ['ok' => false];
            }
            if (is_array($notifyResult) && ($notifyResult['ok'] ?? false) === true) {
                $rxLotteryState['awards'][$index]['notified'] = true;
                $rxLotteryState['awards'][$index]['notification_state'] = 'sent';
            } else {
                $code = (int) ($notifyResult['error_code'] ?? 0);
                $rxLotteryState['awards'][$index]['notification_state'] = $code === 429 && $rxLotteryState['awards'][$index]['notify_attempts'] < 5 ? 'pending' : ($code >= 400 && $code < 500 ? 'failed' : 'uncertain');
                $rxLotteryState['awards'][$index]['notify_after'] = time() + max(60, (int) ($notifyResult['parameters']['retry_after'] ?? 300));
            }
            $rxLotterySaveState();
        }
    }

    if ($awardsDone && empty($rxLotteryState['reset_done'])) {
        try {
            $pdo->beginTransaction();
            $resetKey = '__lottery_reset:' . (string) $rxLotteryState['date'];
            $resetInsert = $pdo->prepare("INSERT IGNORE INTO cron_runtime_state (job_key, last_run, unit, value, enabled) VALUES (?, 0, 'disabled', 1, 0)");
            $resetInsert->execute([$resetKey]);
            $resetCheck = $pdo->prepare('SELECT last_run FROM cron_runtime_state WHERE job_key = ? FOR UPDATE');
            $resetCheck->execute([$resetKey]);
            if ((int) $resetCheck->fetchColumn() === 0) {
                $pdo->exec("UPDATE user SET score = 0 WHERE score > 0");
                $resetDone = $pdo->prepare('UPDATE cron_runtime_state SET last_run = ? WHERE job_key = ?');
                $resetDone->execute([max(1, (int) $rxLotteryState['occurrence']), $resetKey]);
            }
            $pdo->commit();
            $rxLotteryState['reset_done'] = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[cron:lottery] score reset failed: ' . $e->getMessage());
        }
    }

    if ($awardsDone && empty($rxLotteryState['report_sent']) && ($rxLotteryState['report_state'] ?? 'pending') === 'pending' && (int) ($rxLotteryState['report_after'] ?? 0) <= time() && !empty($setting['Channel_Report'])) {
        $rxLotteryState['report_state'] = 'sending';
        $rxLotteryState['report_attempts'] = (int) ($rxLotteryState['report_attempts'] ?? 0) + 1;
        $rxLotterySaveState();
        try {
            $reportResult = telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport,
                'text' => $textlotterygroup,
                'parse_mode' => 'HTML',
                '_rx_no_network_retry' => true,
            ]);
        } catch (Throwable $e) {
            $reportResult = ['ok' => false];
        }
        if (is_array($reportResult) && ($reportResult['ok'] ?? false) === true) {
            $rxLotteryState['report_sent'] = true;
            $rxLotteryState['report_state'] = 'sent';
        } else {
            $code = (int) ($reportResult['error_code'] ?? 0);
            $rxLotteryState['report_state'] = $code === 429 && $rxLotteryState['report_attempts'] < 5 ? 'pending' : ($code >= 400 && $code < 500 ? 'failed' : 'uncertain');
            $rxLotteryState['report_after'] = time() + max(60, (int) ($reportResult['parameters']['retry_after'] ?? 300));
        }
    }
    $rxLotterySaveState();

    $allDone = $awardsDone && !empty($rxLotteryState['reset_done']);
    $retainState = false;
    if (!empty($setting['Channel_Report']) && empty($rxLotteryState['report_sent'])) {
        if (($rxLotteryState['report_state'] ?? 'pending') === 'pending') {
            $allDone = false;
        } else {
            $retainState = true;
        }
    }
    foreach ($rxLotteryState['awards'] as $award) {
        if (($award['state'] ?? '') === 'awarded' && empty($award['notified'])) {
            if (($award['notification_state'] ?? 'pending') === 'pending') {
                $allDone = false;
            } else {
                $retainState = true;
            }
        }
        if (!in_array((string) ($award['state'] ?? ''), ['awarded', 'skipped'], true)) {
            $allDone = false;
            break;
        }
    }
    if ($allDone) {
        $rxLotteryState['finished'] = true;
        $rxLotterySaveState();
        $finish = $pdo->prepare("UPDATE setting SET scorestatus = 0 WHERE scorestatus = 2");
        $finish->execute();
        if (!$retainState) {
            @unlink($rxLotteryStateFile);
        }
        if (function_exists('rx_cron_task_complete')) {
            rx_cron_task_complete('lottery_finished');
        }
    } else {
        if (function_exists('rx_cron_task_defer')) {
            rx_cron_task_defer('lottery_partial_progress', 120);
        }
    }
} catch (Throwable $e) {
    error_log('[cron:lottery] ' . $e->getMessage());
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('lottery_interrupted', 120);
    }
} finally {
    @flock($rxLotteryLock, LOCK_UN);
    @fclose($rxLotteryLock);
}

