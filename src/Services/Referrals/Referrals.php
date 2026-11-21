<?php

namespace Faoxima\Services\Referrals;

use Error;
use Exception;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Referrals
{
    public static function generateReferralCode($length = 12)
    {
        $length = max(1, (int) $length);
        $bytes = (int) ceil($length / 2);

        if (function_exists('random_bytes')) {
            try {
                $code = bin2hex(random_bytes($bytes));
                return substr($code, 0, $length);
            } catch (Exception $exception) {
                error_log('Falling back to pseudo-random referral code generator: ' . $exception->getMessage());
            } catch (Error $exception) {
                error_log('Falling back to pseudo-random referral code generator: ' . $exception->getMessage());
            }
        }

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $maxIndex = strlen($characters) - 1;
        $code = '';

        for ($i = 0; $i < $length; ++$i) {
            if (function_exists('random_int')) {
                try {
                    $index = random_int(0, $maxIndex);
                } catch (Exception $exception) {
                    error_log('random_int failed, using mt_rand fallback: ' . $exception->getMessage());
                    $index = mt_rand(0, $maxIndex);
                } catch (Error $exception) {
                    error_log('random_int failed, using mt_rand fallback: ' . $exception->getMessage());
                    $index = mt_rand(0, $maxIndex);
                }
            } else {
                $index = mt_rand(0, $maxIndex);
            }

            $code .= $characters[$index];
        }

        return $code;
    }

    public static function ensureUserInvitationCode($userId, $currentCode = null, $length = 12)
    {
        if (!is_scalar($userId) || (string) $userId === '') {
            return null;
        }

        $currentCode = is_string($currentCode) ? trim($currentCode) : '';
        if ($currentCode !== '') {
            return $currentCode;
        }

        $newCode = self::generateReferralCode($length);
        \Faoxima\Core\Database\Db::update('user', 'codeInvitation', $newCode, 'id', (string) $userId);

        return $newCode;
    }

    public static function rxReferralRegister($inviteeId, $inviterId): string
    {
        global $pdo;
        $inviteeId = trim((string) $inviteeId);
        $inviterId = trim((string) $inviterId);
        if (!($pdo instanceof PDO) || !ctype_digit($inviteeId) || !ctype_digit($inviterId) || $inviteeId === $inviterId) {
            return 'invalid';
        }
        if ($pdo->inTransaction()) {
            return 'failed';
        }
        try {
            $pdo->beginTransaction();
            $exists = $pdo->prepare("SELECT id FROM user WHERE id IN (:a, :b) FOR UPDATE");
            $exists->execute([':a' => $inviteeId, ':b' => $inviterId]);
            if (count($exists->fetchAll(PDO::FETCH_COLUMN)) !== 2) {
                $pdo->rollBack();
                return 'invalid';
            }
            $claim = $pdo->prepare("UPDATE user SET affiliates = :inviter WHERE id = :invitee AND (affiliates IS NULL OR affiliates = 0 OR affiliates = '')");
            $claim->execute([':inviter' => $inviterId, ':invitee' => $inviteeId]);
            if ($claim->rowCount() < 1) {
                $pdo->rollBack();
                return 'already_referred';
            }
            $count = $pdo->prepare("UPDATE user SET affiliatescount = CAST(COALESCE(NULLIF(affiliatescount, ''), '0') AS UNSIGNED) + 1 WHERE id = :inviter");
            $count->execute([':inviter' => $inviterId]);
            $report = $pdo->prepare("INSERT INTO reagent_report (user_id, get_gift, time, reagent) VALUES (:user_id, 0, :time, :reagent)
                                     ON DUPLICATE KEY UPDATE reagent = IF(get_gift = 0, VALUES(reagent), reagent), time = IF(get_gift = 0, VALUES(time), time)");
            $report->execute([':user_id' => $inviteeId, ':time' => date('Y/m/d H:i:s'), ':reagent' => $inviterId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('rxReferralRegister failed for invitee ' . $inviteeId . ': ' . $e->getMessage());
            return 'failed';
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $inviteeId);
            \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $inviterId);
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('reagent_report');
        }
        return 'registered';
    }

    public static function rxReferralGiftPayout($inviteeId): array
    {
        global $pdo;
        $inviteeId = trim((string) $inviteeId);
        if (!($pdo instanceof PDO) || !ctype_digit($inviteeId)) {
            return ['status' => 'unavailable'];
        }
        if ($pdo->inTransaction()) {
            return ['status' => 'failed', 'reason' => 'nested_transaction'];
        }
        try {
            $gateStmt = $pdo->query("SELECT * FROM affiliates LIMIT 1");
            $gate = $gateStmt ? $gateStmt->fetch(PDO::FETCH_ASSOC) : false;
            if (!is_array($gate) || ($gate['Discount'] ?? '') === 'offDiscountaffiliates') {
                return ['status' => 'disabled'];
            }
            $userStmt = $pdo->prepare("SELECT * FROM user WHERE id = :id LIMIT 1");
            $userStmt->execute([':id' => $inviteeId]);
            $invitee = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($invitee)) {
                return ['status' => 'not_referred'];
            }
            $declaredInviter = trim((string) ($invitee['affiliates'] ?? ''));
            if ($declaredInviter === '' || !ctype_digit($declaredInviter) || $declaredInviter === '0' || $declaredInviter === $inviteeId) {
                return ['status' => 'not_referred'];
            }
            $userStmt->execute([':id' => $declaredInviter]);
            if (!is_array($userStmt->fetch(PDO::FETCH_ASSOC))) {
                return ['status' => 'not_referred'];
            }
            $reagentStmt = $pdo->prepare("SELECT * FROM reagent_report WHERE user_id = :id LIMIT 1");
            $reagentStmt->execute([':id' => $inviteeId]);
            $reagent = $reagentStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($reagent)) {
                $seed = $pdo->prepare("INSERT IGNORE INTO reagent_report (user_id, get_gift, time, reagent) VALUES (:user_id, 0, :time, :reagent)");
                $seed->execute([':user_id' => $inviteeId, ':time' => date('Y/m/d H:i:s'), ':reagent' => $declaredInviter]);
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                    \Faoxima\Core\Database\SelectCache::clearSelectCache('reagent_report');
                }
                $reagentStmt->execute([':id' => $inviteeId]);
                $reagent = $reagentStmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($reagent)) {
                    return ['status' => 'not_referred'];
                }
            }
            if (!empty($reagent['get_gift'])) {
                return ['status' => 'already'];
            }
            if (($gate['require_phone_verified'] ?? '') === 'on_require_phone' && ($invitee['number'] ?? 'none') !== 'confrim number by admin') {
                return ['status' => 'phone_required'];
            }
            $minAgeHours = (int) ($gate['min_account_age_hours'] ?? 0);
            if ($minAgeHours > 0 && !empty($invitee['register']) && $invitee['register'] !== 'none') {
                if ((time() - (int) $invitee['register']) / 3600 < $minAgeHours) {
                    return ['status' => 'too_new'];
                }
            }
            $amount = intval($gate['price_Discount'] ?? 0) / 2;
            if ($amount <= 0) {
                return ['status' => 'no_amount'];
            }
        } catch (Throwable $e) {
            error_log('rxReferralGiftPayout precheck failed for invitee ' . $inviteeId . ': ' . $e->getMessage());
            return ['status' => 'failed', 'reason' => 'precheck'];
        }

        $dailyCap = (int) ($gate['daily_referral_cap'] ?? 0);
        $monthlyCap = (int) ($gate['monthly_referral_cap'] ?? 0);
        try {
            $pdo->beginTransaction();
            $lockReagent = $pdo->prepare("SELECT get_gift, reagent FROM reagent_report WHERE user_id = :id FOR UPDATE");
            $lockReagent->execute([':id' => $inviteeId]);
            $locked = $lockReagent->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();
                return ['status' => 'not_referred'];
            }
            if (!empty($locked['get_gift'])) {
                $pdo->rollBack();
                return ['status' => 'already'];
            }
            $inviterId = trim((string) $locked['reagent']);
            if ($inviterId === '' || $inviterId === $inviteeId) {
                $pdo->rollBack();
                return ['status' => 'not_referred'];
            }
            $lockUsers = $pdo->prepare("SELECT id, Balance FROM user WHERE id = :id FOR UPDATE");
            $lockUsers->execute([':id' => $inviterId]);
            $inviterRow = $lockUsers->fetch(PDO::FETCH_ASSOC);
            $lockUsers->execute([':id' => $inviteeId]);
            $inviteeRow = $lockUsers->fetch(PDO::FETCH_ASSOC);
            if (!is_array($inviterRow) || !is_array($inviteeRow)) {
                $pdo->rollBack();
                return ['status' => 'not_referred'];
            }
            if ($dailyCap > 0) {
                $capStmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_referral_claims WHERE reagent = :reagent AND claim_date = :today");
                $capStmt->execute([':reagent' => $inviterId, ':today' => date('Y-m-d')]);
                if ((int) $capStmt->fetchColumn() >= $dailyCap) {
                    $pdo->rollBack();
                    return ['status' => 'cap_daily'];
                }
            }
            if ($monthlyCap > 0) {
                $capStmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_referral_claims WHERE reagent = :reagent AND claim_date >= :monthstart");
                $capStmt->execute([':reagent' => $inviterId, ':monthstart' => date('Y-m-01')]);
                if ((int) $capStmt->fetchColumn() >= $monthlyCap) {
                    $pdo->rollBack();
                    return ['status' => 'cap_monthly'];
                }
            }
            $mark = $pdo->prepare("UPDATE reagent_report SET get_gift = 1 WHERE user_id = :id AND get_gift = 0");
            $mark->execute([':id' => $inviteeId]);
            if ($mark->rowCount() !== 1) {
                $pdo->rollBack();
                return ['status' => 'already'];
            }
            $claim = $pdo->prepare("INSERT INTO affiliate_referral_claims (reagent, invitee_id, claim_type, claim_date, time) VALUES (:reagent, :invitee_id, 'start_gift', :claim_date, :time)");
            $claim->execute([
                ':reagent' => $inviterId,
                ':invitee_id' => $inviteeId,
                ':claim_date' => date('Y-m-d'),
                ':time' => date('Y/m/d H:i:s'),
            ]);
            if (!is_callable([\Faoxima\Wallet\Balance\Balances::class, 'credit']) || !is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                throw new RuntimeException('wallet helpers unavailable');
            }
            if (!\Faoxima\Wallet\Balance\Balances::credit($pdo, $inviterId, $amount)) {
                throw new RuntimeException('inviter credit failed');
            }
            if (!\Faoxima\Wallet\Ledger\Ledger::record($pdo, $inviterId, 'credit', $amount, 'referral_gift', 'هدیه عضویت زیرمجموعه', null, 'user', $inviteeId)) {
                throw new RuntimeException('inviter ledger failed');
            }
            if (!\Faoxima\Wallet\Balance\Balances::credit($pdo, $inviteeId, $amount)) {
                throw new RuntimeException('invitee credit failed');
            }
            if (!\Faoxima\Wallet\Ledger\Ledger::record($pdo, $inviteeId, 'credit', $amount, 'referral_gift', 'هدیه عضویت', null, 'user', $inviterId)) {
                throw new RuntimeException('invitee ledger failed');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $inviteeId);
                if (isset($inviterId)) {
                    \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $inviterId);
                }
            }
            if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                return ['status' => 'already'];
            }
            error_log('rxReferralGiftPayout failed for invitee ' . $inviteeId . ': ' . $e->getMessage());
            return ['status' => 'failed', 'reason' => 'transaction'];
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $inviteeId);
            \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $inviterId);
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('reagent_report');
        }
        return [
            'status' => 'paid',
            'invitee' => $inviteeId,
            'inviter' => $inviterId,
            'amount' => $amount,
            'invitee_balance_before' => (float) $inviteeRow['Balance'],
            'invitee_balance_after' => (float) $inviteeRow['Balance'] + $amount,
            'inviter_balance_before' => (float) $inviterRow['Balance'],
            'inviter_balance_after' => (float) $inviterRow['Balance'] + $amount,
            'invitee_username' => (string) ($invitee['username'] ?? ''),
        ];
    }

    public static function rxReferralGiftNotify(array $result): void
    {
        if (($result['status'] ?? '') !== 'paid') {
            return;
        }
        $texts = isset($GLOBALS['datatextbot']) && is_array($GLOBALS['datatextbot']) ? $GLOBALS['datatextbot'] : [];
        $inviteeId = (string) $result['invitee'];
        $inviterId = (string) $result['inviter'];
        if (function_exists('sendmessage')) {
            try {
                sendmessage($inviterId, $texts['dyn_affiliates_extra_gift_credited_inviter'] ?? "🎉 یک نفر با معرفی شما وارد شد! هدیه به حساب شما واریز شد.", null, 'html');
            } catch (Throwable $e) {
                error_log('rxReferralGiftNotify inviter message failed: ' . $e->getMessage());
            }
            try {
                sendmessage($inviteeId, $texts['dyn_affiliates_extra_gift_activated'] ?? "🎉 هدیه عضویت برای شما فعال شد!", null, 'html');
            } catch (Throwable $e) {
                error_log('rxReferralGiftNotify invitee message failed: ' . $e->getMessage());
            }
        }
        if (!function_exists('telegram')) {
            return;
        }
        $setting = isset($GLOBALS['setting']) && is_array($GLOBALS['setting']) ? $GLOBALS['setting'] : null;
        if ($setting === null && \Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select");
            } catch (Throwable $e) {
                $setting = null;
            }
        }
        $channel = is_array($setting) ? (string) ($setting['Channel_Report'] ?? '') : '';
        if ($channel === '') {
            return;
        }
        $fmt = function ($value) {
            return method_exists(\Faoxima\Core\Support\Formatting::class, 'rxFormatToman') ? \Faoxima\Core\Support\Formatting::rxFormatToman($value) : number_format((float) $value, 0);
        };
        $username = htmlspecialchars((string) ($result['invitee_username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $report = "🎁 پرداخت هدیه عضویت
<blockquote> -آیدی عددی : {$inviteeId}</blockquote>
<blockquote> - نام کاربری : @{$username}</blockquote>
<blockquote> - آیدی عددی معرف : {$inviterId}</blockquote>
<blockquote> - موجودی زیرمجموعه قبل از هدیه : {$fmt($result['invitee_balance_before'])}</blockquote>
<blockquote> - موجودی زیرمجموعه بعد از هدیه : {$fmt($result['invitee_balance_after'])}</blockquote>
<blockquote>  - موجودی معرف قبل از هدیه : {$fmt($result['inviter_balance_before'])}</blockquote>
<blockquote> - موجودی معرف بعد از هدیه : {$fmt($result['inviter_balance_after'])}</blockquote>
 ";
        try {
            telegram('sendmessage', [
                'chat_id' => $channel,
                'message_thread_id' => $GLOBALS['porsantreport'] ?? null,
                'text' => $report,
                'parse_mode' => "HTML"
            ]);
        } catch (Throwable $e) {
            error_log('rxReferralGiftNotify report failed: ' . $e->getMessage());
        }
    }

    public static function isValidInvitationCode($setting, $fromId, $verfy_status)
    {
        global $datatextbot;

        if ($setting['verifybucodeuser'] == "onverify" && $verfy_status != 1) {
            sendmessage($fromId, $datatextbot['dyn_wallet_prompt_auth_success'] ?? "حساب کاربری شما با موفقیت احرازهویت گردید", null, 'html');
            \Faoxima\Core\Database\Db::update("user", "verify", "1", "id", $fromId);
            \Faoxima\Core\Database\Db::update("user", "cardpayment", "1", "id", $fromId);
        }
    }
}
