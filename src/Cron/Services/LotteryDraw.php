<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use PDO;
use RuntimeException;
use Throwable;

final class LotteryDraw
{
    private PDO $pdo;
    private array $setting;
    private array $prizes;
    private string $runtimeDir;
    private string $stateFile;
    private int $occurrence;
    private string $runDate;
    private $lock;
    private ?array $state = null;
    private bool $claimed = false;
    private int $status = 0;
    private string $groupReport = '';
    private $reportThread = null;

    private function __construct(PDO $pdo, array $setting, array $prizes, string $runtimeDir, string $stateFile, int $occurrence, string $runDate, $lock)
    {
        $this->pdo = $pdo;
        $this->setting = $setting;
        $this->prizes = $prizes;
        $this->runtimeDir = $runtimeDir;
        $this->stateFile = $stateFile;
        $this->occurrence = $occurrence;
        $this->runDate = $runDate;
        $this->lock = $lock;
    }

    public static function begin($setting, $pdo, string $root): ?self
    {
        $dir = $root . '/cronbot';
        if (!$setting || !isset($setting['scorestatus'])) {
            $marker = $dir . '/_setting_missing.flag';
            $age = is_file($marker) ? (time() - (int) @filemtime($marker)) : 99999;
            if ($age > 3600) {
                error_log('[cron:lottery] Setting data is missing or incomplete — check setting table.');
                @touch($marker);
            }
            TaskState::rx_cron_task_defer('setting_unavailable', 300);
            return null;
        }

        if (intval($setting['scorestatus']) === 0) {
            TaskState::rx_cron_task_complete('lottery_not_requested');
            return null;
        }

        $prizes = json_decode($setting['Lottery_prize'], true);
        if (!is_array($prizes) || empty($prizes)) {
            error_log("Lottery_prize is not a valid JSON array.");
            TaskState::rx_cron_task_defer('lottery_prizes_invalid', 600);
            return null;
        }
        $textJson = json_decode(file_get_contents($dir . '/../text.json'), true);
        if (!is_array($textJson)) {
            error_log("text.json is not a valid JSON file.");
            TaskState::rx_cron_task_defer('lottery_text_invalid', 600);
            return null;
        }

        $occurrence = TaskState::rx_cron_task_occurrence(time());
        $runDate = date('Y-m-d', $occurrence > 0 ? $occurrence : time());
        $runtimeDir = $dir . DIRECTORY_SEPARATOR . '.runtime';
        if (!is_dir($runtimeDir)) {
            @mkdir($runtimeDir, 0775, true);
        }
        $stateFile = $runtimeDir . DIRECTORY_SEPARATOR . 'lottery_' . $runDate . '.json';
        $lock = @fopen($stateFile . '.lock', 'c');
        if ($lock === false || !@flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                @fclose($lock);
            }
            TaskState::rx_cron_task_defer('lottery_locked', 120);
            return null;
        }
        return new self($pdo, $setting, $prizes, $runtimeDir, $stateFile, $occurrence, $runDate, $lock);
    }

    public function execute(): void
    {
        $pdo = $this->pdo;
        try {
            if (!$this->ledgerIsTransactional()) {
                TaskState::rx_cron_task_defer('lottery_transactional_ledger_unavailable', 600);
                return;
            }
            if (!$this->claimDraw()) {
                TaskState::rx_cron_task_complete('lottery_claim_lost');
                return;
            }
            if (!$this->restoreState()) {
                return;
            }
            if (!is_array($this->state) && !$this->createState()) {
                return;
            }

            $GLOBALS['otherreport'] = Db::select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
            $this->reportThread = $GLOBALS['otherreport'];
            $awardsDone = $this->awardAll();
            if ($awardsDone && empty($this->state['reset_done'])) {
                $this->resetScores();
            }
            if ($awardsDone && empty($this->state['report_sent']) && ($this->state['report_state'] ?? 'pending') === 'pending' && (int) ($this->state['report_after'] ?? 0) <= time() && !empty($this->setting['Channel_Report'])) {
                $this->sendGroupReport();
            }
            $this->saveState();
            $this->conclude($awardsDone);
        } catch (Throwable $e) {
            error_log('[cron:lottery] ' . $e->getMessage());
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            TaskState::rx_cron_task_defer('lottery_interrupted', 120);
        } finally {
            @flock($this->lock, LOCK_UN);
            @fclose($this->lock);
        }
    }

    private function ledgerIsTransactional(): bool
    {
        $pdo = $this->pdo;
        $ledgerIndex = $pdo->query("SHOW INDEX FROM wallet_ledger WHERE Column_name = 'fulfillment_key' AND Non_unique = 0")->fetch(PDO::FETCH_ASSOC);
        $engineStmt = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('user','wallet_ledger')");
        $transactional = [];
        foreach ($engineStmt->fetchAll(PDO::FETCH_ASSOC) as $engineRow) {
            $transactional[(string) $engineRow['TABLE_NAME']] = strtolower((string) $engineRow['ENGINE']) === 'innodb';
        }
        return is_array($ledgerIndex) && !empty($transactional['user']) && !empty($transactional['wallet_ledger']);
    }

    private function claimDraw(): bool
    {
        $pdo = $this->pdo;
        $this->claimed = false;
        $this->status = (int) $this->setting['scorestatus'];
        if ($this->status === 1) {
            $claim = $pdo->prepare("UPDATE setting SET scorestatus = 2 WHERE scorestatus = 1");
            $claim->execute();
            $this->claimed = $claim->rowCount() > 0;
        }
        return $this->claimed || (int) ($pdo->query('SELECT scorestatus FROM setting LIMIT 1')->fetchColumn() ?: 0) === 2;
    }

    private function restoreState(): bool
    {
        $state = null;
        if (is_file($this->stateFile)) {
            $state = json_decode((string) @file_get_contents($this->stateFile), true);
        }
        if (!is_array($state) && !$this->claimed && $this->status === 2) {
            $candidates = @glob($this->runtimeDir . DIRECTORY_SEPARATOR . 'lottery_*.json');
            if (is_array($candidates) && !empty($candidates)) {
                usort($candidates, static function ($a, $b) {
                    return (int) @filemtime($b) <=> (int) @filemtime($a);
                });
                foreach ($candidates as $candidate) {
                    $recovered = json_decode((string) @file_get_contents($candidate), true);
                    if (is_array($recovered) && empty($recovered['finished']) && isset($recovered['awards'])) {
                        $state = $recovered;
                        $this->stateFile = $candidate;
                        break;
                    }
                }
            }
        }
        if (!is_array($state) && !$this->claimed && $this->status === 2) {
            TaskState::rx_cron_task_uncertain('lottery_state_missing', 1800);
            return false;
        }
        if (is_array($state) && !TaskState::rx_cron_catchup_allowed((int) ($state['occurrence'] ?? 0))) {
            TaskState::rx_cron_task_complete('lottery_catchup_window_elapsed');
            return false;
        }
        if (is_array($state) && empty($state['ledger_version'])) {
            foreach ((array) ($state['awards'] ?? []) as $legacyAward) {
                if (!in_array((string) ($legacyAward['state'] ?? ''), ['awarded', 'skipped'], true)) {
                    TaskState::rx_cron_task_uncertain('legacy_lottery_awards_require_review', 1800);
                    return false;
                }
            }
        }
        $this->state = is_array($state) ? $state : null;
        return true;
    }

    private function createState(): bool
    {
        $pdo = $this->pdo;
        if ($this->setting['Lotteryagent'] == "1") {
            $stmt = $pdo->prepare("SELECT * FROM user WHERE User_Status = 'Active' AND score != '0' ORDER BY score DESC LIMIT 3");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM user WHERE User_Status = 'Active' AND score != '0' AND agent = 'f' ORDER BY score DESC LIMIT 3");
        }
        $stmt->execute();
        $winnerRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $awards = [];
        $rank = 0;
        foreach ($winnerRows as $winnerRow) {
            if (!isset($this->prizes[$rank])) {
                break;
            }
            $awards[] = [
                'rank' => $rank + 1,
                'user_id' => (string) $winnerRow['id'],
                'username' => (string) $winnerRow['username'],
                'language' => (string) ($winnerRow['language'] ?? 'fa'),
                'prize' => (int) $this->prizes[$rank],
                'state' => 'pending',
                'notified' => false,
            ];
            $rank++;
        }
        $this->state = [
            'ledger_version' => 1,
            'occurrence' => $this->occurrence,
            'date' => $this->runDate,
            'awards' => $awards,
            'reset_done' => false,
            'report_sent' => false,
        ];
        $tmp = $this->stateFile . '.tmp';
        if (@file_put_contents($tmp, json_encode($this->state, JSON_UNESCAPED_UNICODE), LOCK_EX) === false || !@rename($tmp, $this->stateFile)) {
            @unlink($tmp);
            try {
                $pdo->prepare("UPDATE setting SET scorestatus = 1 WHERE scorestatus = 2")->execute();
            } catch (Throwable $e) {
            }
            TaskState::rx_cron_task_uncertain('lottery_state_persist_failed', 600);
            return false;
        }
        return true;
    }

    private function saveState(): void
    {
        $tmp = $this->stateFile . '.tmp';
        $encoded = json_encode($this->state, JSON_UNESCAPED_UNICODE);
        if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $this->stateFile)) {
            throw new RuntimeException('lottery_state_persist_failed');
        }
    }

    private function awardAll(): bool
    {
        $awardsDone = true;
        $this->groupReport = "📌 ادمین عزیز کاربران زیر برنده قرعه کشی و حسابشان شارژ گردید.\n";
        foreach ($this->state['awards'] as $index => $award) {
            if (CronRuntime::rx_cron_time_up()) {
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
                $this->state['awards'][$index]['state'] = 'skipped';
                $this->saveState();
                continue;
            }
            if (($award['state'] ?? '') !== 'awarded') {
                $outcome = $this->creditPrize($index, $rank, $userId, $prizeAmount);
                if ($outcome === 'skip') {
                    continue;
                }
                if ($outcome === 'failed') {
                    $awardsDone = false;
                    continue;
                }
            }
            $balanceFormatted = number_format($prizeAmount);
            $this->groupReport .= "\n<blockquote>نام کاربری : @{$award['username']}</blockquote>\n<blockquote>آیدی عددی : {$userId}</blockquote>\n<blockquote>مبلغ : $balanceFormatted</blockquote>\n<blockquote>نفر : $rank</blockquote>\n---------------\n";
            $this->notifyWinner($index, $rank, $userId, $balanceFormatted);
        }
        return $awardsDone;
    }

    private function creditPrize(int $index, int $rank, string $userId, int $prizeAmount): string
    {
        $pdo = $this->pdo;
        try {
            $pdo->beginTransaction();
            $userStmt = $pdo->prepare('SELECT Balance FROM user WHERE id = ? FOR UPDATE');
            $userStmt->execute([$userId]);
            $balance = $userStmt->fetchColumn();
            if ($balance === false) {
                $pdo->commit();
                $this->state['awards'][$index]['state'] = 'skipped';
                $this->saveState();
                return 'skip';
            }
            $ledgerKey = 'cron:lottery:' . (string) $this->state['date'] . ':' . $userId;
            $ledgerStmt = $pdo->prepare('SELECT id FROM wallet_ledger WHERE fulfillment_key = ? LIMIT 1');
            $ledgerStmt->execute([$ledgerKey]);
            if ($ledgerStmt->fetchColumn() === false) {
                $ledger = $pdo->prepare("INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, fulfillment_key) VALUES (?, 'credit', ?, ?, 'lottery', ?, ?)");
                $ledger->execute([$userId, $prizeAmount, (int) $balance + $prizeAmount, 'جایزه قرعه‌کشی رتبه ' . $rank, $ledgerKey]);
                $atomic = $pdo->prepare('UPDATE user SET Balance = Balance + ?, score = 0 WHERE id = ?');
                $atomic->execute([$prizeAmount, $userId]);
            }
            $pdo->commit();
            $this->state['awards'][$index]['state'] = 'awarded';
            $this->saveState();
            return 'ok';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[cron:lottery] award persistence failed for rank ' . $rank . ': ' . $e->getMessage());
            return 'failed';
        }
    }

    private function notifyWinner(int $index, int $rank, string $userId, string $balanceFormatted): void
    {
        $award = $this->state['awards'][$index];
        $notificationState = (string) ($award['notification_state'] ?? 'pending');
        if (!(empty($award['notified']) && $notificationState === 'pending' && (int) ($award['notify_after'] ?? 0) <= time())) {
            return;
        }
        $this->state['awards'][$index]['notification_state'] = 'sending';
        $this->state['awards'][$index]['notify_attempts'] = (int) ($award['notify_attempts'] ?? 0) + 1;
        $this->saveState();
        $text = "🎁 نتیجه قرعه کشی \n\n😎 کاربر عزیز تبریک! شما نفر $rank برنده $balanceFormatted تومان موجودی شدید و حساب شما شارژ گردید.";
        try {
            $result = telegram('sendmessage', ['chat_id' => $userId, 'text' => $text, 'parse_mode' => 'HTML', '_rx_no_network_retry' => true]);
        } catch (Throwable $e) {
            $result = ['ok' => false];
        }
        if (is_array($result) && ($result['ok'] ?? false) === true) {
            $this->state['awards'][$index]['notified'] = true;
            $this->state['awards'][$index]['notification_state'] = 'sent';
        } else {
            $code = (int) ($result['error_code'] ?? 0);
            $this->state['awards'][$index]['notification_state'] = $code === 429 && $this->state['awards'][$index]['notify_attempts'] < 5 ? 'pending' : ($code >= 400 && $code < 500 ? 'failed' : 'uncertain');
            $this->state['awards'][$index]['notify_after'] = time() + max(60, (int) ($result['parameters']['retry_after'] ?? 300));
        }
        $this->saveState();
    }

    private function resetScores(): void
    {
        $pdo = $this->pdo;
        try {
            $pdo->beginTransaction();
            $resetKey = '__lottery_reset:' . (string) $this->state['date'];
            $insert = $pdo->prepare("INSERT IGNORE INTO cron_runtime_state (job_key, last_run, unit, value, enabled) VALUES (?, 0, 'disabled', 1, 0)");
            $insert->execute([$resetKey]);
            $check = $pdo->prepare('SELECT last_run FROM cron_runtime_state WHERE job_key = ? FOR UPDATE');
            $check->execute([$resetKey]);
            if ((int) $check->fetchColumn() === 0) {
                $pdo->exec("UPDATE user SET score = 0 WHERE score > 0");
                $done = $pdo->prepare('UPDATE cron_runtime_state SET last_run = ? WHERE job_key = ?');
                $done->execute([max(1, (int) $this->state['occurrence']), $resetKey]);
            }
            $pdo->commit();
            $this->state['reset_done'] = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[cron:lottery] score reset failed: ' . $e->getMessage());
        }
    }

    private function sendGroupReport(): void
    {
        $this->state['report_state'] = 'sending';
        $this->state['report_attempts'] = (int) ($this->state['report_attempts'] ?? 0) + 1;
        $this->saveState();
        try {
            $result = telegram('sendmessage', [
                'chat_id' => $this->setting['Channel_Report'],
                'message_thread_id' => $this->reportThread,
                'text' => $this->groupReport,
                'parse_mode' => 'HTML',
                '_rx_no_network_retry' => true,
            ]);
        } catch (Throwable $e) {
            $result = ['ok' => false];
        }
        if (is_array($result) && ($result['ok'] ?? false) === true) {
            $this->state['report_sent'] = true;
            $this->state['report_state'] = 'sent';
        } else {
            $code = (int) ($result['error_code'] ?? 0);
            $this->state['report_state'] = $code === 429 && $this->state['report_attempts'] < 5 ? 'pending' : ($code >= 400 && $code < 500 ? 'failed' : 'uncertain');
            $this->state['report_after'] = time() + max(60, (int) ($result['parameters']['retry_after'] ?? 300));
        }
    }

    private function conclude(bool $awardsDone): void
    {
        $state = &$this->state;
        $allDone = $awardsDone && !empty($state['reset_done']);
        $retainState = false;
        if (!empty($this->setting['Channel_Report']) && empty($state['report_sent'])) {
            if (($state['report_state'] ?? 'pending') === 'pending') {
                $allDone = false;
            } else {
                $retainState = true;
            }
        }
        foreach ($state['awards'] as $award) {
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
            $state['finished'] = true;
            $this->saveState();
            $finish = $this->pdo->prepare("UPDATE setting SET scorestatus = 0 WHERE scorestatus = 2");
            $finish->execute();
            if (!$retainState) {
                @unlink($this->stateFile);
            }
            TaskState::rx_cron_task_complete('lottery_finished');
        } else {
            TaskState::rx_cron_task_defer('lottery_partial_progress', 120);
        }
    }
}
