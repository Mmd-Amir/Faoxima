<?php


declare(strict_types=1);

$isCLI  = (php_sapi_name() === 'cli');
$isCron = ($isCLI || !isset($_SERVER['HTTP_HOST']));

@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', '1');
@set_time_limit(35);
@ini_set('memory_limit', '256M');
ignore_user_abort(true);

date_default_timezone_set('Asia/Tehran');
if (function_exists('putenv') && !preg_match('/(^|,)\s*putenv\s*(,|$)/', strtolower((string) ini_get('disable_functions')))) {
    @putenv('TZ=Asia/Tehran');
}


if (function_exists('fastcgi_finish_request') && !$isCLI) {
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Length: 0');
        header('Connection: close');
    }
    @ob_end_flush();
    @flush();
    @fastcgi_finish_request();
}

$baseDir = dirname(__FILE__);
require_once $baseDir . '/_init.php';
rx_cron_verify_web_access();

$__required_files = [
    $baseDir . '/../config.php',
    $baseDir . '/../botapi.php',
    $baseDir . '/../function.php',
];
foreach ($__required_files as $__f) {
    if (!is_file($__f)) {
        $msg = '[' . date('Y-m-d H:i:s') . '] [cron:sendmessage] missing required file: ' . $__f
             . ' — skipping this run (upload the file to fix).';
        @error_log($msg);
        @file_put_contents($baseDir . '/_missing_files.log', $msg . PHP_EOL, FILE_APPEND);
        return;
    }
}
foreach ($__required_files as $__f) {
    require_once $__f;
}
unset($__required_files, $__f);

if (!rx_cron_db_ready('sendmessage')) {
    return;
}

$workerId = (int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0);
$workerId = max(0, min(15, $workerId));
$lockFile = $baseDir . '/broadcast' . ($workerId > 0 ? "_w{$workerId}" : '') . '.lock';

$rxBcLog = static function (string $line) use ($baseDir, $workerId): void {
    if (!is_file($baseDir . '/broadcast_log.on')) {
        return;
    }
    $f = $baseDir . '/broadcast_run.log';
    if (is_file($f) && (int) @filesize($f) > 2 * 1024 * 1024) {
        @rename($f, $f . '.1');
    }
    @file_put_contents(
        $f,
        '[' . date('Y-m-d H:i:s') . "][w{$workerId}] " . $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
};

$lockFh = @fopen($lockFile, 'c');
if ($lockFh === false) {
    if (file_exists($lockFile) && (time() - (int) @filemtime($lockFile)) < 90) {
        $rxBcLog('SKIP — قفل فعال است (mtime fallback) age=' . (time() - (int) @filemtime($lockFile)) . 's');
        exit;
    }
    @file_put_contents($lockFile, getmypid() . '|' . date('Y-m-d H:i:s'));
} else {
    if (!@flock($lockFh, LOCK_EX | LOCK_NB)) {
        $rxBcLog('SKIP — قفل فعال است (اجرای دیگری همین حالا در حال اجراست)');
        @fclose($lockFh);
        exit;
    }
    @ftruncate($lockFh, 0);
    @fwrite($lockFh, getmypid() . '|' . date('Y-m-d H:i:s'));
    @fflush($lockFh);
    register_shutdown_function(static function () use ($lockFh) {
        @flock($lockFh, LOCK_UN);
        @fclose($lockFh);
    });
}

try {

    $datatxtbot  = select("textbot", "*", null, null, "fetchAll");
    $datatextbot = [
        'text_usertest'   => '',
        'text_support'    => '',
        'text_help'       => '',
        'text_sell'       => '',
        'text_affiliates' => '',
        'text_Add_Balance'=> '',
    ];
    if (is_array($datatxtbot)) {
        foreach ($datatxtbot as $row) {
            $idText = isset($row['id_text']) ? (string) $row['id_text'] : '';
            if ($idText !== '' && array_key_exists($idText, $datatextbot)) {
                $datatextbot[$idText] = (string) ($row['text'] ?? '');
            }
        }
    }

    $infoFile     = $baseDir . '/info';
    $usersFileTxt = $baseDir . '/users.txt';
    $usersFileJson= $baseDir . '/users.json';

    if (!is_file($infoFile)) {
        @unlink($lockFile);
        exit;
    }
    $infoContent = @file_get_contents($infoFile);
    if ($infoContent === false) { $rxBcLog('ERROR — خواندن info ناموفق'); @unlink($lockFile); exit; }
    $info = json_decode($infoContent, true);
    if (!is_array($info)) { $rxBcLog('ERROR — info نامعتبر (JSON خراب)'); @unlink($lockFile); exit; }

    $bcToken = substr(md5(
        ($info['id_admin'] ?? '') . '|' . ($info['id_message'] ?? '') . '|' .
        ($info['type'] ?? '') . '|' . ($info['message'] ?? '')
    ), 0, 12);
    $inflightFile = $usersFileTxt . '.w' . $workerId . '.' . $bcToken . '.inflight';
    $sentFile     = $usersFileTxt . '.w' . $workerId . '.' . $bcToken . '.sent';

    $workersTotal = (int) ($_GET['workers'] ?? $_SERVER['BROADCAST_WORKERS'] ?? 1);
    $workersTotal = max(1, min(16, $workersTotal));

    $claimLockFile  = $usersFileTxt . '.' . $bcToken . '.claim';
    $reportLockFile = $usersFileTxt . '.' . $bcToken . '.report';
    $infoLockFile   = $infoFile . '.wlock';

    $rxInfoUpdate = static function (callable $mutator) use ($infoFile, $infoLockFile) {
        $lf = @fopen($infoLockFile, 'c');
        if ($lf) { @flock($lf, LOCK_EX); }
        $cur = json_decode((string) @file_get_contents($infoFile), true);
        if (is_array($cur)) {
            $mutator($cur);
            $tmp = $infoFile . '.tmp';
            if (@file_put_contents($tmp, json_encode($cur, JSON_UNESCAPED_UNICODE)) !== false) {
                @rename($tmp, $infoFile);
            }
        }
        if ($lf) { @flock($lf, LOCK_UN); @fclose($lf); }
        return is_array($cur) ? $cur : null;
    };

    $rxAllInflight = static function () use ($usersFileTxt, $bcToken): int {
        $t = 0;
        foreach (glob($usersFileTxt . '.w*.' . $bcToken . '.inflight') ?: [] as $inf) {
            $t += countLines($inf);
        }
        return $t;
    };

    $rxTryFinish = static function () use (
        $usersFileTxt, $usersFileJson, $claimLockFile, $reportLockFile, $infoFile, $infoLockFile,
        &$info, $rxBcLog, $rxAllInflight
    ): bool {
        $clf = @fopen($claimLockFile, 'c');
        if ($clf) { @flock($clf, LOCK_EX); }
        $remain = (is_file($usersFileTxt) ? countLines($usersFileTxt) : 0) + $rxAllInflight();
        $done = ($remain === 0);
        if ($done) {
            $rl = @fopen($reportLockFile, 'x');
            if ($rl !== false) {
                @fclose($rl);
                $st = json_decode((string) @file_get_contents($infoFile), true);
                $st = (is_array($st) && isset($st['stats']) && is_array($st['stats'])) ? $st['stats'] : [];
                $rxBcLog('FINISHED — کل عملیات تمام شد | total=' . (int) ($st['total'] ?? 0) . ' | موفق=' . (int) ($st['success'] ?? 0));
                if (isset($info['id_admin'], $info['id_message'])) {
                    @deletemessage($info['id_admin'], $info['id_message']);
                    sendmessage($info['id_admin'], buildFinalReport($st), null, 'HTML');
                }
                @unlink($infoFile);
                @unlink($usersFileTxt);
                @unlink($usersFileJson);
                @unlink($infoLockFile);
                foreach (glob($usersFileTxt . '.*') ?: [] as $f) {
                    if ($f === $reportLockFile) continue;
                    @unlink($f);
                }
            } else {
                $rxBcLog('DONE — این worker تمام شد؛ گزارش نهایی را worker دیگری می‌فرستد');
            }
        }
        if ($clf) { @flock($clf, LOCK_UN); @fclose($clf); }
        return $done;
    };

    $rxLinesNow = is_file($usersFileTxt) ? countLines($usersFileTxt) : -1;
    $rxStatsTotal = (isset($info['stats']['total'])) ? (int) $info['stats']['total'] : 0;
    $rxBcLog('START — type=' . ($info['type'] ?? '?')
        . ' | admin=' . ($info['id_admin'] ?? '?')
        . ' | خطوط users.txt الان=' . $rxLinesNow
        . ' | total ذخیره‌شده در info=' . $rxStatsTotal);

    if ($rxStatsTotal === 0) {
        foreach (glob($usersFileTxt . '.*') ?: [] as $f) {
            if (strpos($f, '.' . $bcToken . '.') !== false) continue;
            if (preg_match('/\.(inflight|sent|claim|report)$/', $f)) {
                @unlink($f);
            }
        }
        $rxBcLog('FRESH — شروع تمیز: فایل‌های جانبیِ ارسال‌های قبلی پاک شد');
    }


    if (!is_file($usersFileTxt) && is_file($usersFileJson)) {
        $migrated = migrateLegacyJsonToTxt($usersFileJson, $usersFileTxt);
        if ($migrated >= 0) {
            @unlink($usersFileJson);
        }
    }


    $totals = isset($info['stats']) && is_array($info['stats']) ? $info['stats'] : [];
    $totals += [
        'total'         => 0,
        'success'       => 0,
        'blocked'       => 0,
        'deleted'       => 0,
        'failed'        => 0,
        'chat_not_found'=> 0,
        'started_at'    => time(),
    ];
    $batchSize       = 50;
    $softTimeLimit   = 27;
    $checkpointEvery = 1;
    $batch = [];
    $usingTxtMode = is_file($usersFileTxt);
    $usingJsonMode = !$usingTxtMode && is_file($usersFileJson);

    $batchSource  = 'none';

    if (is_file($inflightFile) && countLines($inflightFile) > 0) {
        $batch       = loadLinesFromTxt($inflightFile, $batchSize);
        $batchSource = 'inflight-recover';
    } elseif ($usingTxtMode) {
        $clf = @fopen($claimLockFile, 'c');
        if ($clf) { @flock($clf, LOCK_EX); }
        $rxInfoUpdate(function (array &$c) use ($usersFileTxt, $rxAllInflight) {
            if (!isset($c['stats']) || !is_array($c['stats'])) $c['stats'] = [];
            if ((int) ($c['stats']['total'] ?? 0) > 0) return;
            $c['stats']['total'] = (is_file($usersFileTxt) ? countLines($usersFileTxt) : 0) + $rxAllInflight();
            if (empty($c['stats']['started_at'])) $c['stats']['started_at'] = time();
        });
        $batch       = claimBatchToInflight($usersFileTxt, $inflightFile, $batchSize);
        if ($clf) { @flock($clf, LOCK_UN); @fclose($clf); }
        $batchSource = 'txt-claim';
    } elseif ($usingJsonMode) {
        $jsonRaw = @file_get_contents($usersFileJson);
        $jsonArr = $jsonRaw !== false ? json_decode($jsonRaw) : null;
        if (is_array($jsonArr)) {
            $batch = [];
            $remaining = [];
            $i = 0;
            foreach ($jsonArr as $row) {
                if ($i++ < $batchSize) {
                    if (isset($row->id)) {
                        $batch[] = (string) $row->id;
                    } elseif (is_array($row) && isset($row['id'])) {
                        $batch[] = (string) $row['id'];
                    }
                } else {
                    $remaining[] = $row;
                }
            }
            $tmp = $usersFileJson . '.tmp';
            file_put_contents($tmp, json_encode($remaining, JSON_UNESCAPED_UNICODE));
            @rename($tmp, $usersFileJson);
        }
        $batchSource = 'json-legacy';
    } else {
        $rxBcLog('SKIP — نه users.txt و نه users.json موجود است (صف خالی)');
        @unlink($lockFile);
        exit;
    }

    $rxFresh = json_decode((string) @file_get_contents($infoFile), true);
    if (is_array($rxFresh) && isset($rxFresh['stats']) && is_array($rxFresh['stats'])) {
        $totals = $rxFresh['stats'] + $totals;
        $totals['total'] = (int) ($rxFresh['stats']['total'] ?? $totals['total']);
    }

    $rxBcLog('BATCH — mode=' . ($usingTxtMode ? 'txt' : ($usingJsonMode ? 'json' : 'none'))
        . ' | w=' . $workerId . '/' . $workersTotal
        . ' | source=' . $batchSource
        . ' | اندازه بچ=' . $batchSize
        . ' | خوانده‌شده در این اجرا=' . count($batch)
        . ' | inflight=' . (is_file($inflightFile) ? countLines($inflightFile) : 0)
        . ' | باقی‌مانده users.txt=' . (is_file($usersFileTxt) ? countLines($usersFileTxt) : 0)
        . ' | total=' . (int) $totals['total']);

    if (count($batch) === 0) {
        $rxTryFinish();
        @unlink($inflightFile);
        @unlink($sentFile);
        @unlink($lockFile);
        exit;
    }


    $keyboards = [
        'none'         => null,
        'buy'          => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_buy_service', ['text' => $datatextbot['text_sell'],     'callback_data' => 'buy'])]]]),
        'start'        => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_start_bot',   ['text' => "شروع",                        'callback_data' => 'start'])]]]),
        'usertestbtn'  => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_usertest',    ['text' => $datatextbot['text_usertest'], 'callback_data' => 'usertestbtn'])]]]),
        'helpbtn'      => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_help',        ['text' => $datatextbot['text_help'],     'callback_data' => 'helpbtn'])]]]),
        'affiliatesbtn'=> json_encode(['inline_keyboard' => [[rx_cron_btn('cron_affiliates',  ['text' => $datatextbot['text_affiliates'],'callback_data' => 'affiliatesbtn'])]]]),
        'addbalance'   => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_addbalance',  ['text' => $datatextbot['text_Add_Balance'],'callback_data' => 'Add_Balance'])]]]),
    ];
    $cancelmessage = json_encode(['inline_keyboard' => [[['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage']]]]);


    $batchStart    = microtime(true);
    $processed = $bSuccess = $bBlocked = $bDeleted = $bFailed = $bChatNotFound = 0;

    $rxApplied = ['success' => 0, 'blocked' => 0, 'deleted' => 0, 'failed' => 0, 'chat_not_found' => 0];
    $rxApplyStatsDelta = function () use (&$rxApplied, &$bSuccess, &$bBlocked, &$bDeleted, &$bFailed, &$bChatNotFound, $rxInfoUpdate) {
        $d = [
            'success'        => $bSuccess      - $rxApplied['success'],
            'blocked'        => $bBlocked      - $rxApplied['blocked'],
            'deleted'        => $bDeleted      - $rxApplied['deleted'],
            'failed'         => $bFailed       - $rxApplied['failed'],
            'chat_not_found' => $bChatNotFound - $rxApplied['chat_not_found'],
        ];
        if (!array_filter($d)) return;
        $rxInfoUpdate(function (array &$c) use ($d) {
            if (!isset($c['stats']) || !is_array($c['stats'])) $c['stats'] = [];
            foreach ($d as $k => $v) {
                $c['stats'][$k] = (int) ($c['stats'][$k] ?? 0) + $v;
            }
        });
        $rxApplied = ['success' => $bSuccess, 'blocked' => $bBlocked, 'deleted' => $bDeleted, 'failed' => $bFailed, 'chat_not_found' => $bChatNotFound];
    };

    $deleteStmt = $pdo->prepare("DELETE FROM user WHERE id = :id");
    $orphanCheckStmt = $pdo->prepare(
        "SELECT (SELECT COUNT(*) FROM invoice WHERE id_user = :id) AS invoice_count, Balance
         FROM user WHERE id = :id2 LIMIT 1"
    );

    $sentSet = loadSentSet($sentFile);

    $idx = 0;
    foreach ($batch as $userId) {
        if ((microtime(true) - $batchStart) >= $softTimeLimit) {
            $unprocessedSlice = array_slice($batch, $idx);
            if ($usingJsonMode && !empty($unprocessedSlice)) {
                prependEntriesToJson($usersFileJson, $unprocessedSlice);
            }
            break;
        }
        $idx++;
        $userId = trim((string) $userId);
        if ($userId === '' || !is_numeric($userId)) {
            continue;
        }

        if (isset($sentSet[$userId])) {
            if (!$usingJsonMode) {
                syncInflight($inflightFile, array_slice($batch, $idx));
            }
            continue;
        }

        $processed++;

        $resp = null;
        if ($info['type'] === 'unpinmessage') {
            $unpinResult = runRateLimitedUnpin($userId, $baseDir, $batchStart, $softTimeLimit, $rxBcLog);
            if (!empty($unpinResult['deferred'])) {
                $idx--;
                $processed--;
                if ($usingJsonMode) {
                    prependEntriesToJson($usersFileJson, array_slice($batch, $idx));
                }
                break;
            }
            $resp = $unpinResult['response'];
            handleResponse($resp, $userId, $info, $orphanCheckStmt, $deleteStmt,
                $bSuccess, $bBlocked, $bDeleted, $bFailed, $bChatNotFound, $rxBcLog);
        } elseif ($info['type'] === 'sendmessage' || $info['type'] === 'xdaynotmessage') {
            $kb = $keyboards[$info['btnmessage'] ?? 'none'] ?? null;
            $resp = sendmessage($userId, $info['message'], $kb, 'HTML');
            handleResponse($resp, $userId, $info, $orphanCheckStmt, $deleteStmt,
                $bSuccess, $bBlocked, $bDeleted, $bFailed, $bChatNotFound, $rxBcLog);
        } elseif ($info['type'] === 'forwardmessage') {
            $resp = forwardMessage($info['id_admin'], $info['message'], $userId);
            handleResponse($resp, $userId, $info, $orphanCheckStmt, $deleteStmt,
                $bSuccess, $bBlocked, $bDeleted, $bFailed, $bChatNotFound, $rxBcLog);
        }

        @file_put_contents($sentFile, $userId . "\n", FILE_APPEND | LOCK_EX);
        $sentSet[$userId] = true;

        if (!$usingJsonMode && ($processed % $checkpointEvery) === 0) {
            syncInflight($inflightFile, array_slice($batch, $idx));
            $rxApplyStatsDelta();
        }
    }


    if (!$usingJsonMode) {
        syncInflight($inflightFile, array_slice($batch, $idx));
    }

    $rxApplyStatsDelta();
    $rxAgg = json_decode((string) @file_get_contents($infoFile), true);
    $rxAgg = (is_array($rxAgg) && isset($rxAgg['stats']) && is_array($rxAgg['stats'])) ? $rxAgg['stats'] : $totals;

    $batchExecutionTime = microtime(true) - $batchStart;
    $messagesPerSecond  = $processed > 0 ? $processed / max(0.001, $batchExecutionTime) : 0;

    $inflightRemain = $rxAllInflight();
    $usersRemain = $usingTxtMode
        ? (is_file($usersFileTxt) ? countLines($usersFileTxt) : 0)
        : (is_file($usersFileJson) ? (count((array) json_decode((string) @file_get_contents($usersFileJson)))) : 0);
    $countRemain = $usersRemain + $inflightRemain;
    $totalSent = (int) ($rxAgg['success'] ?? 0) + (int) ($rxAgg['blocked'] ?? 0)
               + (int) ($rxAgg['failed'] ?? 0)  + (int) ($rxAgg['chat_not_found'] ?? 0);

    $rxBcLog('RUN-DONE — w=' . $workerId . ' | پردازش این اجرا=' . $processed
        . ' (موفق=' . $bSuccess . ' بلاک=' . $bBlocked . ' حذف=' . $bDeleted
        . ' خطا=' . $bFailed . ' بدون‌چت=' . $bChatNotFound . ')'
        . ' | باقی‌مانده=' . $countRemain . ' (inflight=' . $inflightRemain . ')'
        . ' | مجموع ارسال‌شده تاکنون=' . $totalSent . ' از ' . (int) ($rxAgg['total'] ?? 0));

    if ($countRemain === 0) {
        if ($usingJsonMode) {
            $rxBcLog('FINISHED — کل عملیات تمام شد | total=' . (int) ($rxAgg['total'] ?? 0));
            if (isset($info['id_admin'], $info['id_message'])) {
                @deletemessage($info['id_admin'], $info['id_message']);
                sendmessage($info['id_admin'], buildFinalReport($rxAgg), null, 'HTML');
            }
            @unlink($infoFile); @unlink($usersFileTxt); @unlink($usersFileJson);
            @unlink($inflightFile); @unlink($sentFile);
        } else {
            $rxTryFinish();
        }
        @unlink($lockFile);
        exit;
    }

    $textprocces  = "✏️ عملیات ارسال پیام درحال انجام...\n\n";
    $textprocces .= "📊 باقی‌مانده: " . number_format($countRemain) . " نفر\n";
    $textprocces .= "🚀 ارسال‌شده: "  . number_format($totalSent)  . " از " . number_format((int) ($rxAgg['total'] ?? 0)) . "\n";
    $textprocces .= "✅ موفق: "      . number_format((int) ($rxAgg['success'] ?? 0));
    if ((int) ($rxAgg['blocked'] ?? 0)        > 0) $textprocces .= " | 🚫 بلاک: "      . number_format((int) $rxAgg['blocked']);
    if ((int) ($rxAgg['chat_not_found'] ?? 0) > 0) $textprocces .= " | 📵 بدون چت: "   . number_format((int) $rxAgg['chat_not_found']);
    if ((int) ($rxAgg['deleted'] ?? 0)        > 0) $textprocces .= " | 🗑 حذف‌شده: "   . number_format((int) $rxAgg['deleted']);
    if ((int) ($rxAgg['failed'] ?? 0)         > 0) $textprocces .= " | ❌ خطا: "       . number_format((int) $rxAgg['failed']);
    $textprocces .= "\n\n⏱ این بچ: " . round($batchExecutionTime, 1) . "s | 🔥 سرعت: " . round($messagesPerSecond, 1) . " پیام/ثانیه";

    if ($workerId === 0 && isset($info['id_admin'], $info['id_message'])) {
        Editmessagetext($info['id_admin'], $info['id_message'], $textprocces, $cancelmessage);
    }
} catch (Throwable $e) {
    error_log('[sendmessage.php] ' . $e->getMessage());
    $rxBcLog('EXCEPTION — ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    @unlink($lockFile);
}


function handleResponse(
    $resp, string $userId, array $info,
    PDOStatement $orphanCheckStmt, PDOStatement $deleteStmt,
    int &$bSuccess, int &$bBlocked, int &$bDeleted, int &$bFailed, int &$bChatNotFound,
    ?callable $rxBcLog = null
): void {
    if (isset($resp['ok']) && !$resp['ok']) {
        $errorDesc = $resp['description'] ?? 'unknown error';
        if ($errorDesc === "Forbidden: bot was blocked by the user") {
            $bBlocked++;
            try {
                $orphanCheckStmt->execute([':id' => $userId, ':id2' => $userId]);
                $r = $orphanCheckStmt->fetch(PDO::FETCH_ASSOC);
                if ($r && (int) $r['invoice_count'] === 0 && (int) $r['Balance'] === 0) {
                    $deleteStmt->execute([':id' => $userId]);
                    $bDeleted++;
                }
            } catch (Throwable $e) {}
        } elseif (stripos($errorDesc, 'chat not found') !== false
               || stripos($errorDesc, 'user is deactivated') !== false) {
            $bChatNotFound++;
            try { $deleteStmt->execute([':id' => $userId]); $bDeleted++; } catch (Throwable $e) {}
        } else {
            $bFailed++;
        }
    } elseif (isset($resp['ok']) && $resp['ok']) {
        $bSuccess++;
        if (($info['pingmessage'] ?? '') === 'yes' && isset($resp['result']['message_id'])) {
            try {
                pinmessage($userId, $resp['result']['message_id']);
                savePinnedMessageRecord($userId, (int) $resp['result']['message_id'], (string) ($info['pinduration'] ?? 'none'));
            } catch (Throwable $e) {
                if ($rxBcLog) {
                    $rxBcLog('PIN FAILED — userId=' . $userId . ' message_id=' . $resp['result']['message_id'] . ' — ' . $e->getMessage());
                }
            }
        }
    }
}

function runRateLimitedUnpin(
    string $userId,
    string $baseDir,
    float $batchStart,
    int $softTimeLimit,
    ?callable $rxBcLog = null
): array {
    $resolvedBaseDir = realpath($baseDir);
    $rateKey = md5($resolvedBaseDir !== false ? $resolvedBaseDir : $baseDir);
    $ratePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'faoxima_unpin_' . $rateKey;
    $lockFh = @fopen($ratePath . '.lock', 'c');

    if ($lockFh !== false) {
        while (!@flock($lockFh, LOCK_EX | LOCK_NB)) {
            if ((microtime(true) - $batchStart) >= $softTimeLimit) {
                @fclose($lockFh);
                return ['deferred' => true, 'response' => null];
            }
            usleep(50000);
        }
    }

    $response = null;
    $isRateLimited = false;

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $nextAllowedAt = is_file($ratePath) ? (float) @file_get_contents($ratePath) : 0.0;
        $waitMicros = (int) max(0, ceil(($nextAllowedAt - microtime(true)) * 1000000));
        $remainingMicros = (int) max(0, floor(($softTimeLimit - (microtime(true) - $batchStart)) * 1000000));

        if ($waitMicros >= $remainingMicros) {
            $isRateLimited = true;
            break;
        }

        if ($waitMicros > 0) {
            usleep($waitMicros);
        }

        $response = unpinmessage($userId);
        $isRateLimited = isset($response['error_code']) && (int) $response['error_code'] === 429;

        if (!$isRateLimited) {
            @file_put_contents($ratePath, sprintf('%.6F', microtime(true) + 0.1), LOCK_EX);
            break;
        }

        $retryAfter = max(1, (int) ($response['parameters']['retry_after'] ?? 1));
        @file_put_contents($ratePath, sprintf('%.6F', microtime(true) + $retryAfter + 0.25), LOCK_EX);
        if ($rxBcLog) {
            $rxBcLog('RATE-LIMIT — unpin userId=' . $userId . ' retry_after=' . $retryAfter . 's attempt=' . $attempt);
        }
    }

    if ($lockFh !== false) {
        @flock($lockFh, LOCK_UN);
        @fclose($lockFh);
    }

    if ($isRateLimited) {
        return ['deferred' => true, 'response' => $response];
    }

    return ['deferred' => false, 'response' => $response];
}

function savePinnedMessageRecord(string $chatId, int $messageId, string $duration): void
{
    global $pdo;
    $durationSeconds = ['24' => 86400, '48' => 172800][$duration] ?? null;
    $unpinAt = $durationSeconds !== null ? (time() + $durationSeconds) : null;
    $stmt = $pdo->prepare(
        "INSERT INTO pinned_messages (chat_id, message_id, unpin_at, created_at) VALUES (:chat_id, :message_id, :unpin_at, :created_at)"
    );
    $stmt->execute([
        ':chat_id'    => $chatId,
        ':message_id' => $messageId,
        ':unpin_at'   => $unpinAt,
        ':created_at' => time(),
    ]);
}

function migrateLegacyJsonToTxt(string $jsonPath, string $txtPath): int
{
    if (!is_file($jsonPath)) return -1;
    $size = @filesize($jsonPath);
    if ($size === false) return -1;


    if ($size <= 32 * 1024 * 1024) {
        $raw = @file_get_contents($jsonPath);
        if ($raw === false) return -1;
        $arr = json_decode($raw);
        if (!is_array($arr)) return -1;
        $tmp = $txtPath . '.tmp';
        $fh = @fopen($tmp, 'w');
        if (!$fh) return -1;
        $count = 0;
        foreach ($arr as $row) {
            $id = null;
            if (isset($row->id)) $id = $row->id;
            elseif (is_array($row) && isset($row['id'])) $id = $row['id'];
            elseif (is_scalar($row)) $id = $row;
            if ($id !== null && $id !== '' && is_numeric($id)) {
                fwrite($fh, ((string) $id) . "\n");
                $count++;
            }
        }
        fclose($fh);
        if (!@rename($tmp, $txtPath)) return -1;
        return $count;
    }


    $in  = @fopen($jsonPath, 'r');
    if (!$in) return -1;
    $tmp = $txtPath . '.tmp';
    $out = @fopen($tmp, 'w');
    if (!$out) { fclose($in); return -1; }

    $count  = 0;
    $buffer = '';
    $regex  = '/"id"\s*:\s*"?([0-9]+)"?/';

    while (!feof($in)) {
        $chunk = fread($in, 65536);
        if ($chunk === false) break;
        $buffer .= $chunk;


        $offset = 0;
        while (preg_match($regex, $buffer, $m, PREG_OFFSET_CAPTURE, $offset)) {
            fwrite($out, $m[1][0] . "\n");
            $count++;
            $offset = $m[0][1] + strlen($m[0][0]);
        }


        if ($offset > 64) {
            $buffer = substr($buffer, $offset - 64);
        }
    }
    fclose($in);
    fclose($out);
    if (!@rename($tmp, $txtPath)) return -1;
    return $count;
}

function claimBatchToInflight(string $src, string $inflight, int $n): array
{
    if (!is_file($src)) return [];
    $in = @fopen($src, 'r');
    if (!$in) return [];
    $tail = $src . '.tail.tmp';
    $out  = @fopen($tail, 'w');
    if (!$out) { fclose($in); return []; }

    $batch = [];
    $i = 0;
    while (($line = fgets($in)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        if ($i < $n) { $batch[] = $line; $i++; }
        else         { fwrite($out, $line . "\n"); }
    }
    fclose($in);
    fclose($out);

    syncInflight($inflight, $batch);
    @rename($tail, $src);
    return $batch;
}

function syncInflight(string $path, array $remainder): void
{
    $clean = [];
    foreach ($remainder as $line) {
        $line = trim((string) $line);
        if ($line !== '') $clean[] = $line;
    }

    if (empty($clean)) { @unlink($path); return; }

    $tmp = $path . '.tmp';
    $out = @fopen($tmp, 'w');
    if (!$out) return;
    foreach ($clean as $line) {
        fwrite($out, $line . "\n");
    }
    fclose($out);
    @rename($tmp, $path);
}

function loadSentSet(string $path): array
{
    if (!is_file($path)) return [];
    $set = [];
    $in = @fopen($path, 'r');
    if (!$in) return [];
    while (($line = fgets($in)) !== false) {
        $line = trim($line);
        if ($line !== '') $set[$line] = true;
    }
    fclose($in);
    return $set;
}

function loadLinesFromTxt(string $path, int $max): array
{
    if (!is_file($path)) return [];
    $in = @fopen($path, 'r');
    if (!$in) return [];
    $out = [];
    $i = 0;
    while (($line = fgets($in)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        $out[] = $line;
        if (++$i >= $max) break;
    }
    fclose($in);
    return $out;
}

function prependEntriesToJson(string $path, array $items): void
{
    $existing = [];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw) : null;
        if (is_array($decoded)) $existing = $decoded;
    }
    $rebuilt = [];
    foreach ($items as $id) $rebuilt[] = (object) ['id' => (string) $id];
    foreach ($existing as $row) $rebuilt[] = $row;
    $tmp = $path . '.prep.tmp';
    file_put_contents($tmp, json_encode($rebuilt, JSON_UNESCAPED_UNICODE));
    @rename($tmp, $path);
}

function countLines(string $path): int
{
    if (!is_file($path)) return 0;
    $fh = @fopen($path, 'r');
    if (!$fh) return 0;
    $count = 0;
    while (!feof($fh)) {
        $chunk = fread($fh, 65536);
        if ($chunk === false) break;
        $count += substr_count($chunk, "\n");
    }
    fclose($fh);
    return $count;
}

function buildFinalReport(array $totals): string {
    $f  = "✅ عملیات ارسال پیام با موفقیت به پایان رسید.\n\n📊 گزارش نهایی:\n";
    $f .= "👥 مخاطبین: "  . number_format((int) ($totals['total']    ?? 0)) . "\n";
    $f .= "✅ موفق: "    . number_format((int) ($totals['success']  ?? 0)) . "\n";
    if (!empty($totals['blocked']))        $f .= "🚫 بلاک‌شده: "   . number_format((int) $totals['blocked'])        . "\n";
    if (!empty($totals['chat_not_found'])) $f .= "📵 چت ناموجود: ". number_format((int) $totals['chat_not_found']) . "\n";
    if (!empty($totals['deleted']))        $f .= "🗑 حذف‌شده: "   . number_format((int) $totals['deleted'])        . "\n";
    if (!empty($totals['failed']))         $f .= "❌ خطا: "       . number_format((int) $totals['failed'])         . "\n";
    if (!empty($totals['started_at'])) {
        $elapsed = max(0, time() - (int) $totals['started_at']);
        $f .= "⏱ مدت کل: " . gmdate('H:i:s', $elapsed) . "\n";
    }
    $f .= "\n🕒 پایان: " . date('Y-m-d H:i:s') . " (Asia/Tehran)";
    return $f;
}

