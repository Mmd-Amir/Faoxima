<?php


declare(strict_types=1);

require_once __DIR__ . '/VerifyHandler.php';

final class PhoneVerifyHandler
{
    public static function run(): void
    {
        global $APIKEY;

        if (!is_string($APIKEY) || $APIKEY === '') {
            FaoximaLogger::critical('Bot APIKEY is not configured');
            self::fail(500, 'Server is not configured (missing bot token)');
        }

        $candidates = FaoximaAuth::collectInitDataCandidates();
        if (empty($candidates)) {
            self::fail(400, 'Telegram init data is missing or invalid');
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
            self::fail($status, $message);
        }

        $userId = (int)$userData['id'];

        $raw = file_get_contents('php://input');
        $body = $raw ? json_decode($raw, true) : null;
        $contactRaw = null;
        if (is_array($body)) {
            $contactRaw = $body['contact_response'] ?? $body['response'] ?? $body['contact'] ?? null;
        }
        if ($contactRaw === null && isset($_POST['contact_response'])) {
            $contactRaw = $_POST['contact_response'];
        }
        if (!is_string($contactRaw) || trim($contactRaw) === '') {
            self::fail(400, 'اطلاعات شماره دریافت نشد.');
        }

        $contact = null;
        try {
            $contact = FaoximaAuth::validateContactResponse($contactRaw, $APIKEY);
        } catch (InvalidArgumentException $e) {
            self::fail(400, $e->getMessage());
        } catch (RuntimeException $e) {
            FaoximaLogger::warn('Contact verification failed', ['tg_id' => $userId, 'msg' => $e->getMessage()]);
            self::fail(403, 'اعتبارسنجی شماره ناموفق بود.');
        }

        $contactUserId = (int)($contact['user_id'] ?? 0);
        if ($contactUserId !== $userId) {
            self::fail(403, 'شمارهٔ به‌اشتراک‌گذاشته‌شده متعلق به شما نیست.');
        }

        $phone = preg_replace('/\D+/', '', (string)($contact['phone_number'] ?? ''));
        if ($phone === '') {
            self::fail(400, 'شمارهٔ موبایل نامعتبر است.');
        }

        $setting = select('setting', '*', null, null, 'select');
        if (!is_array($setting)) {
            $setting = [];
        }

        if (($setting['iran_number'] ?? '') === 'onAuthenticationiran' && !preg_match('/989[0-9]{9}$/', $phone)) {
            self::fail(422, 'لطفاً شمارهٔ موبایل ایران را به اشتراک بگذارید.');
        }

        update('user', 'number', $phone, 'id', $userId);
        if (($setting['verifystart'] ?? '') === 'onverify') {
            update('user', 'verify', '1', 'id', $userId);
        }
        if (function_exists('clearSelectCache')) {
            clearSelectCache('user');
        }

        $userRecord = select('user', '*', 'id', $userId, 'select', ['cache' => false]);
        if (!is_array($userRecord)) {
            self::fail(500, 'Failed to load user after phone verification');
        }

        FaoximaLogger::debug('Phone verified for miniapp user', ['tg_id' => $userId]);

        VerifyHandler::finalizeForUser($userId, $userData, $userRecord);
    }

    private static function fail(int $code, string $msg): void
    {
        if (function_exists('__verify_emit')) {
            __verify_emit($code, ['status' => false, 'msg' => $msg, 'token' => null]);
            exit;
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
        }
        echo json_encode(['status' => false, 'msg' => $msg, 'token' => null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
