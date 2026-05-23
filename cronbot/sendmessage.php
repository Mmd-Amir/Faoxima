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
@putenv('TZ=Asia/Tehran');


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


$workerId = (int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0);
$workerId = max(0, min(15, $workerId));
$lockFile = $baseDir . '/broadcast' . ($workerId > 0 ? "_w{$workerId}" : '') . '.lock';

if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 300) {
    exit;
}
file_put_contents($lockFile, getmypid() . '|' . date('Y-m-d H:i:s'));

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
    if ($infoContent === false) { @unlink($lockFile); exit; }
    $info = json_decode($infoContent, true);
    if (!is_array($info)) { @unlink($lockFile); exit; }


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
    if ((int) $totals['total'] === 0 && is_file($usersFileTxt)) {
        $totals['total'] = countLines($usersFileTxt);
    }


    $batchSize     = 400;
    $softTimeLimit = 27;
    $batch = [];
    $usingTxtMode = is_file($usersFileTxt);
    $usingJsonMode = !$usingTxtMode && is_file($usersFileJson);

    if ($usingTxtMode) {
        $batch = readBatchFromTxt($usersFileTxt, $batchSize);
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
    } else {
        @unlink($lockFile);
        exit;
    }


    if (count($batch) === 0) {
        if (isset($info['id_admin'], $info['id_message'])) {
            @deletemessage($info['id_admin'], $info['id_message']);
            sendmessage($info['id_admin'], buildFinalReport($totals), null, 'HTML');
        }
        @unlink($infoFile);
        @unlink($usersFileTxt);
        @unlink($usersFileJson);
        @unlink($usersFileTxt . '.tmp');
        @unlink($usersFileJson . '.tmp');
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
    $cancelmessage = json_encode(['inline_keyboard' => [[rx_cron_btn('cron_cancel', ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'])]]]);


    $batchStart    = microtime(true);
    $processed = $bSuccess = $bBlocked = $bDeleted = $bFailed = $bChatNotFound = 0;

    $deleteStmt = $pdo->prepare("DELETE FROM user WHERE id = :id");
    $orphanCheckStmt = $pdo->prepare(
        "SELECT (SELECT COUNT(*) FROM invoice WHERE id_user = :id) AS invoice_count, Balance
         FROM user WHERE id = :id2 LIMIT 1"
    );

    foreach ($batch as $userId) {
        if ((microtime(true) - $batchStart) >= $softTimeLimit) {

            $unprocessedSlice = array_slice($batch, $processed);
            if ($usingTxtMode && !empty($unprocessedSlice)) {
                prependLinesToTxt($usersFileTxt, $unprocessedSlice);
            } elseif ($usingJsonMode && !empty($unprocessedSlice)) {
                prependEntriesToJson($usersFileJson, $unprocessedSlice);
            }
            break;
        }
        $userId = trim((string) $userId);
        if ($userId === '' || !is_numeric($userId)) {
            continue;
        }
        $processed++;

        if ($info['type'] === 'unpinmessage') {
            unpinmessage($userId);
            $bSuccess++;
            continue;
        }

        if ($info['type'] === 'sendmessage' || $info['type'] === 'xdaynotmessage') {
            $kb = $keyboards[$info['btnmessage'] ?? 'none'] ?? null;
            $resp = sendmessage($userId, $info['message'], $kb, 'HTML');
            handleResponse($resp, $userId, $info, $orphanCheckStmt, $deleteStmt,
                $bSuccess, $bBlocked, $bDeleted, $bFailed, $bChatNotFound);
            continue;
        }

        if ($info['type'] === 'forwardmessage') {
            $resp = forwardMessage($info['id_admin'], $info['message'], $userId);
            handleResponse($resp, $userId, $info, $orphanCheckStmt, $deleteStmt,
                $bSuccess, $bBlocked, $bDeleted, $bFailed, $bChatNotFound);
        }
    }


    $totals['success']        += $bSuccess;
    $totals['blocked']        += $bBlocked;
    $totals['deleted']        += $bDeleted;
    $totals['failed']         += $bFailed;
    $totals['chat_not_found'] += $bChatNotFound;

    $batchExecutionTime = microtime(true) - $batchStart;
    $messagesPerSecond  = $processed > 0 ? $processed / max(0.001, $batchExecutionTime) : 0;

    $countRemain = $usingTxtMode
        ? countLines($usersFileTxt)
        : (is_file($usersFileJson) ? (count((array) json_decode((string) @file_get_contents($usersFileJson)))) : 0);
    $totalSent = $totals['success'] + $totals['blocked']
               + $totals['failed']  + $totals['chat_not_found'];

    if ($countRemain === 0) {
        if (isset($info['id_admin'], $info['id_message'])) {
            @deletemessage($info['id_admin'], $info['id_message']);
            sendmessage($info['id_admin'], buildFinalReport($totals), null, 'HTML');
        }
        @unlink($infoFile);
        @unlink($usersFileTxt);
        @unlink($usersFileJson);
        @unlink($usersFileTxt . '.tmp');
        @unlink($usersFileJson . '.tmp');
        @unlink($lockFile);
        exit;
    }


    $textprocces  = "✏️ عملیات ارسال پیام درحال انجام...\n\n";
    $textprocces .= "📊 باقی‌مانده: " . number_format($countRemain) . " نفر\n";
    $textprocces .= "🚀 ارسال‌شده: "  . number_format($totalSent)  . " از " . number_format((int)$totals['total']) . "\n";
    $textprocces .= "✅ موفق: "      . number_format($totals['success']);
    if ($totals['blocked']        > 0) $textprocces .= " | 🚫 بلاک: "      . number_format($totals['blocked']);
    if ($totals['chat_not_found'] > 0) $textprocces .= " | 📵 بدون چت: "   . number_format($totals['chat_not_found']);
    if ($totals['deleted']        > 0) $textprocces .= " | 🗑 حذف‌شده: "   . number_format($totals['deleted']);
    if ($totals['failed']         > 0) $textprocces .= " | ❌ خطا: "       . number_format($totals['failed']);
    $textprocces .= "\n\n⏱ این بچ: " . round($batchExecutionTime, 1) . "s | 🔥 سرعت: " . round($messagesPerSecond, 1) . " پیام/ثانیه";

    if (isset($info['id_admin'], $info['id_message'])) {
        Editmessagetext($info['id_admin'], $info['id_message'], $textprocces, $cancelmessage);
    }

    $info['stats'] = $totals;
    $tempInfo = $infoFile . '.tmp';
    file_put_contents($tempInfo, json_encode($info, JSON_UNESCAPED_UNICODE));
    @unlink($infoFile);
    @rename($tempInfo, $infoFile);
} catch (Throwable $e) {
    error_log('[sendmessage.php] ' . $e->getMessage());
} finally {
    @unlink($lockFile);
}


function handleResponse(
    $resp, string $userId, array $info,
    PDOStatement $orphanCheckStmt, PDOStatement $deleteStmt,
    int &$bSuccess, int &$bBlocked, int &$bDeleted, int &$bFailed, int &$bChatNotFound
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
            try { pinmessage($userId, $resp['result']['message_id']); } catch (Throwable $e) {}
        }
    }
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

function readBatchFromTxt(string $path, int $n): array
{
    if (!is_file($path)) return [];
    $batch = [];
    $tmp = $path . '.tail.tmp';
    $in = @fopen($path, 'r');
    if (!$in) return [];
    $out = @fopen($tmp, 'w');
    if (!$out) { fclose($in); return []; }
    $i = 0;
    while (($line = fgets($in)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        if ($i < $n) {
            $batch[] = $line;
            $i++;
        } else {
            fwrite($out, $line . "\n");
        }
    }
    fclose($in);
    fclose($out);
    @rename($tmp, $path);
    return $batch;
}

function prependLinesToTxt(string $path, array $lines): void
{
    if (empty($lines)) return;
    $tmp = $path . '.prep.tmp';
    $out = @fopen($tmp, 'w');
    if (!$out) return;
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line !== '') fwrite($out, $line . "\n");
    }
    if (is_file($path)) {
        $in = @fopen($path, 'r');
        if ($in) {
            while (!feof($in)) {
                $chunk = fread($in, 65536);
                if ($chunk === false) break;
                fwrite($out, $chunk);
            }
            fclose($in);
        }
    }
    fclose($out);
    @rename($tmp, $path);
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

