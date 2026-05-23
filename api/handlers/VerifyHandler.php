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
            FaoximaLogger::debug('Unknown Telegram user attempted miniapp login', ['tg_id' => $userId]);
            self::sendVerifyError(404, 'User not found');
        }

        try {
            $token = FaoximaAuth::issueToken($userId);
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

