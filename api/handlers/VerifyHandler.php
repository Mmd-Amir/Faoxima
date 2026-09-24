<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class VerifyHandler
{
    public static function run(): void
    {
        global $APIKEY;

        if (!is_string($APIKEY) || $APIKEY === '') {
            FaoximaLogger::critical('Bot APIKEY is not configured');
            self::sendVerifyError(500, 'Server is not configured (missing bot token)');
        }

        $candidates = FaoximaAuth::collectInitDataCandidates();
        if (empty($candidates)) {
            self::sendVerifyError(400, 'Telegram init data is missing or invalid');
        }

        $userData = null;
        $lastException = null;
        foreach ($candidates as $candidate) {
            try {
                $userData = FaoximaAuth::validateInitData($candidate, $APIKEY);
                break;
            } catch (InvalidArgumentException $e) {
                $lastException = $e;
                continue;
            } catch (RuntimeException $e) {
                $lastException = $e;
                continue;
            }
        }

        if ($userData === null) {
            $message = $lastException ? $lastException->getMessage() : 'Telegram init data is missing or invalid';
            $status = $lastException instanceof RuntimeException ? 403 : 400;
            FaoximaLogger::warn('initData verification failed', ['msg' => $message]);
            self::sendVerifyError($status, $message);
        }

        $userId = (int)$userData['id'];
        $userRecord = select('user', '*', 'id', $userId, 'select');
        if (empty($userRecord)) {
            $userRecord = self::autoRegisterUser($userId, $userData);
            if (empty($userRecord) || !is_array($userRecord)) {
                FaoximaLogger::warn('Auto-registration failed for miniapp user', ['tg_id' => $userId]);
                self::sendVerifyError(500, 'Failed to register user');
            }
        }

        self::finalizeForUser($userId, $userData, is_array($userRecord) ? $userRecord : []);
    }

    public static function finalizeForUser(int $userId, array $userData, array $userRecord): void
    {
        $gate = self::evaluateGates($userId, $userRecord);
        if ($gate !== null) {
            self::emit(200, array_merge(['status' => false, 'token' => null], $gate));
            return;
        }

        try {
            $existingToken = (string)($userRecord['token'] ?? '');
            $token = $existingToken !== '' ? $existingToken : FaoximaAuth::issueToken($userId);
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'Failed to issue token');
            self::sendVerifyError(500, 'Failed to generate session token');
        }

        FaoximaLogger::debug('User verified', ['user_id' => $userId]);

        self::emit(200, [
            'status' => true,
            'msg'    => 'User verified',
            'token'  => $token,
        ]);
    }

    private static function evaluateGates(int $userId, array $userRecord): ?array
    {
        global $setting;

        if (!is_array($setting)) {
            $setting = select('setting', '*', null, null, 'select');
        }
        if (!is_array($setting)) {
            $setting = [];
        }

        $GLOBALS['from_id'] = $userId;

        $adminIds = select('admin', 'id_admin', null, null, 'FETCH_COLUMN', ['cache' => false]);
        $isAdmin = is_array($adminIds) && in_array($userId, array_map('intval', $adminIds), true);

        if (!$isAdmin) {
            $channelsId = select('channels', 'link', null, null, 'FETCH_COLUMN', ['cache' => false]);
            if (is_array($channelsId) && count($channelsId) > 0 && function_exists('channel')) {
                $checkFailed = [];
                $missing = channel($channelsId, $checkFailed);
                if (is_array($missing) && count($missing) === 0 && !empty($checkFailed)) {
                    self::sendVerifyError(503, 'بررسی عضویت در کانال در حال حاضر با خطا مواجه شد؛ لطفاً چند لحظه دیگر دوباره تلاش کنید.');
                }
                if (is_array($missing) && count($missing) > 0) {
                    if (($userRecord['joinchannel'] ?? '') === 'active') {
                        update('user', 'joinchannel', '0', 'id', $userId);
                    }
                    $list = [];
                    foreach ($missing as $chan) {
                        $row = select('channels', '*', 'link', $chan, 'select', ['cache' => false]);
                        $title = is_array($row) && !empty($row['remark']) ? (string)$row['remark'] : (string)$chan;
                        $link  = is_array($row) && !empty($row['linkjoin']) ? (string)$row['linkjoin'] : ('https://t.me/' . ltrim((string)$chan, '@'));
                        $list[] = ['title' => $title, 'link' => $link];
                    }
                    return [
                        'gate'     => 'force_join',
                        'channels' => $list,
                        'msg'      => 'برای استفاده از مینی‌اپ، ابتدا در کانال‌های زیر عضو شوید.',
                    ];
                }
                if (($userRecord['joinchannel'] ?? '') !== 'active') {
                    update('user', 'joinchannel', 'active', 'id', $userId);
                    $userRecord['joinchannel'] = 'active';
                }
            }
        }

        $needPhone  = ((($setting['get_number'] ?? '') === 'onAuthenticationphone') || (($setting['iran_number'] ?? '') === 'onAuthenticationiran'))
            && (($userRecord['number'] ?? 'none') === 'none');
        $needVerify = (($setting['verifystart'] ?? '') === 'onverify')
            && (intval($userRecord['verify'] ?? 0) === 0)
            && !$isAdmin;

        if ($needPhone || $needVerify) {
            return [
                'gate' => 'phone_required',
                'iran' => (($setting['iran_number'] ?? '') === 'onAuthenticationiran'),
                'msg'  => 'برای ادامه، شمارهٔ موبایل خود را تأیید کنید.',
            ];
        }

        return null;
    }

    private static function autoRegisterUser(int $userId, array $userData): ?array
    {
        global $pdo, $setting;

        if (!($pdo instanceof PDO)) {
            FaoximaLogger::critical('Cannot auto-register miniapp user: database connection unavailable');
            return null;
        }

        if (!is_array($setting)) {
            $setting = select('setting', '*', null, null, 'select');
        }
        if (!is_array($setting)) {
            FaoximaLogger::critical('Cannot auto-register miniapp user: settings unavailable');
            return null;
        }

        $username     = isset($userData['username']) ? (string)$userData['username'] : '';
        $date         = time();
        $valueverify  = (($setting['verifystart'] ?? '') !== 'onverify') ? 1 : 0;
        $randomString = bin2hex(random_bytes(6));
        $initialProcessingValue     = '0';
        $initialProcessingValueOne  = 'none';
        $initialProcessingValueTow  = 'none';
        $initialProcessingValueFour = '0';
        $initialRollStatus          = '0';

        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user (id , step,limit_usertest,User_Status,number,Balance,pagenumber,username,agent,message_count,last_message_time,affiliates,affiliatescount,cardpayment,number_username,namecustom,register,verify,codeInvitation,pricediscount,maxbuyagent,joinchannel,score,status_cron,roll_Status,Processing_value,Processing_value_one,Processing_value_tow,Processing_value_four) VALUES (:from_id, 'none',:limit_usertest_all,'Active','none','0','1',:username,'f','0','0','0','0',:showcard,'100','none',:date,:verifycode,:codeInvitation,'0','0','0','0','1',:roll_status,:processing_value,:processing_value_one,:processing_value_tow,:processing_value_four)");
            $stmt->bindValue(':from_id', $userId);
            $stmt->bindValue(':limit_usertest_all', $setting['limit_usertest_all'] ?? null);
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':showcard', $setting['showcard'] ?? null);
            $stmt->bindValue(':date', $date);
            $stmt->bindValue(':verifycode', $valueverify);
            $stmt->bindValue(':codeInvitation', $randomString);
            $stmt->bindValue(':roll_status', $initialRollStatus);
            $stmt->bindValue(':processing_value', $initialProcessingValue);
            $stmt->bindValue(':processing_value_one', $initialProcessingValueOne);
            $stmt->bindValue(':processing_value_tow', $initialProcessingValueTow);
            $stmt->bindValue(':processing_value_four', $initialProcessingValueFour);
            $stmt->execute();
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'Auto-registration insert failed');
            return null;
        }

        if (function_exists('clearSelectCache')) {
            clearSelectCache('user');
        }

        $userRecord = select('user', '*', 'id', $userId, 'select', ['cache' => false]);
        if (empty($userRecord) || !is_array($userRecord)) {
            return null;
        }

        FaoximaLogger::debug('Auto-registered new miniapp user', ['tg_id' => $userId]);
        return $userRecord;
    }

    private static function sendVerifyError(int $code, string $msg): void
    {
        self::emit($code, [
            'status' => false,
            'msg'    => $msg,
            'token'  => null,
        ]);
    }

    private static function emit(int $http, array $payload): void
    {
        if (function_exists('__verify_emit')) {
            __verify_emit($http, $payload);
            exit;
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($http);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

