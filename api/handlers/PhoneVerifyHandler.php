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

        $setting = select('setting', '*', null, null, 'select');
        if (!is_array($setting)) {
            $setting = [];
        }

        $outcome = self::applyPhoneRules($userId, is_array($contact) ? $contact : [], $setting);
        if (!$outcome['ok']) {
            self::fail((int) $outcome['code'], (string) $outcome['msg']);
        }

        $userRecord = select('user', '*', 'id', $userId, 'select', ['cache' => false]);
        if (!is_array($userRecord)) {
            self::fail(500, 'Failed to load user after phone verification');
        }

        FaoximaLogger::debug('Phone verified for miniapp user', ['tg_id' => $userId]);

        VerifyHandler::finalizeForUser($userId, $userData, $userRecord);
    }

    /**
     * Applies the shared-contact rules and persists the number.
     *
     * Delegates to the extracted PhoneVerificationService when the Composer
     * autoloader can resolve it. Until `composer dump-autoload` has run on a
     * given install the legacy inline rules are used instead, so onboarding
     * can never hard-fail on a missing class.
     *
     * @param array<string, mixed> $contact
     * @param array<string, mixed> $setting
     * @return array{ok: bool, code?: int, msg?: string}
     */
    private static function applyPhoneRules(int $userId, array $contact, array $setting): array
    {
        if (class_exists(\Faoxima\Onboarding\PhoneVerificationService::class)
            && class_exists(\Faoxima\Onboarding\Legacy\LegacyUserProfile::class)
        ) {
            $service = new \Faoxima\Onboarding\PhoneVerificationService(
                new \Faoxima\Onboarding\Legacy\LegacyUserProfile()
            );
            $result = $service->apply($userId, $contact, $setting);

            if ($result->accepted) {
                return ['ok' => true];
            }

            return [
                'ok' => false,
                'code' => $result->status,
                'msg' => self::phoneRejectionMessage($result->reason),
            ];
        }

        if (class_exists('FaoximaLogger')) {
            FaoximaLogger::warn('PhoneVerificationService not autoloadable; using legacy inline rules');
        }

        return self::applyPhoneRulesLegacy($userId, $contact, $setting);
    }

    /**
     * User-facing text for each rejection branch. Kept verbatim from the
     * pre-refactor handler so clients keep receiving identical messages.
     */
    private static function phoneRejectionMessage(string $reason): string
    {
        return match ($reason) {
            \Faoxima\Onboarding\PhoneVerificationResult::REASON_CONTACT_NOT_OWNED
                => 'شمارهٔ به‌اشتراک‌گذاشته‌شده متعلق به شما نیست.',
            \Faoxima\Onboarding\PhoneVerificationResult::REASON_IRAN_MOBILE_REQUIRED
                => 'لطفاً شمارهٔ موبایل ایران را به اشتراک بگذارید.',
            default
                => 'شمارهٔ موبایل نامعتبر است.',
        };
    }

    /**
     * Fallback implementation, identical to the pre-refactor handler.
     * Remove once autoloading of the Onboarding namespace is confirmed in
     * every deployment (Stage 6 cleanup).
     *
     * @param array<string, mixed> $contact
     * @param array<string, mixed> $setting
     * @return array{ok: bool, code?: int, msg?: string}
     */
    private static function applyPhoneRulesLegacy(int $userId, array $contact, array $setting): array
    {
        $contactUserId = (int)($contact['user_id'] ?? 0);
        if ($contactUserId !== $userId) {
            return ['ok' => false, 'code' => 403, 'msg' => 'شمارهٔ به‌اشتراک‌گذاشته‌شده متعلق به شما نیست.'];
        }

        $phone = preg_replace('/\D+/', '', (string)($contact['phone_number'] ?? ''));
        if ($phone === '') {
            return ['ok' => false, 'code' => 400, 'msg' => 'شمارهٔ موبایل نامعتبر است.'];
        }

        if (($setting['iran_number'] ?? '') === 'onAuthenticationiran' && !preg_match('/989[0-9]{9}$/', $phone)) {
            return ['ok' => false, 'code' => 422, 'msg' => 'لطفاً شمارهٔ موبایل ایران را به اشتراک بگذارید.'];
        }

        update('user', 'number', $phone, 'id', $userId);
        if (($setting['verifystart'] ?? '') === 'onverify') {
            update('user', 'verify', '1', 'id', $userId);
        }
        if (function_exists('clearSelectCache')) {
            clearSelectCache('user');
        }

        return ['ok' => true];
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
