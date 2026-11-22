<?php

namespace Faoxima\Admin\Web\Pages;

use Faoxima\Admin\Web\Support\TextbotSavePlanner;
use Faoxima\Core\Database\TextbotTemporary;
use PDO;

final class TextbotTemporaryRequest
{
    public static function isTemporaryRequest(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return strncmp((string) ($_POST['_action'] ?? ''), 'temp_', 5) === 0;
        }
        return (string) ($_GET['temp'] ?? '') === 'status';
    }

    public static function handle(PDO $pdo, string $admin, array $defaults): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::respond(200, ['ok' => true, 'status' => TextbotTemporary::status($pdo, $admin)]);
        }
        $action = (string) ($_POST['_action'] ?? '');
        $sessionId = self::intField('_temp_session');
        $revision = self::intField('_temp_rev');
        $payload = null;
        $rawPayload = $_POST['_text_payload'] ?? null;
        if (is_string($rawPayload) && trim($rawPayload) !== '') {
            $payload = TextbotSavePlanner::parsePayload($rawPayload);
            if ($payload === null) {
                self::fail($pdo, $admin, 422, 'invalid_payload', 'داده‌های فرم کامل یا معتبر نیستند؛ تغییری ذخیره نشد. صفحه را تازه کنید.');
            }
        }

        if ($action === 'temp_save') {
            if ($payload === null) {
                self::fail($pdo, $admin, 422, 'invalid_payload', 'داده‌های فرم کامل یا معتبر نیستند؛ تغییری ذخیره نشد.');
            }
            $duration = TextbotTemporary::durationFromInput($_POST['_temp_duration'] ?? null, $_POST['_temp_custom'] ?? null);
            if ($duration === null) {
                self::fail($pdo, $admin, 422, 'invalid_duration', 'مدت ذخیره موقت معتبر نیست؛ حداقل ۱ دقیقه و حداکثر ۷۲ ساعت.');
            }
            $plan = TextbotSavePlanner::plan($payload, TextbotSavePlanner::currentRows($pdo, $defaults), $pdo);
            $rejected = self::rejections($plan);
            $rows = TextbotSavePlanner::rowsOf($plan['units']);
            if ($rows === []) {
                $unchanged = self::noChangeNotice($plan);
                if ($rejected) {
                    self::fail($pdo, $admin, 422, 'rejected', 'هیچ‌کدام از تغییرات ذخیره نشد؛ دلیل هر مورد در زیر آمده است.', ['rejected' => $rejected + $unchanged]);
                }
                self::fail($pdo, $admin, 422, 'nothing_to_save', $unchanged ? 'محتوای متن‌های انتخاب‌شده با نسخهٔ فعلی یکسان است و چیزی برای ذخیره موقت وجود ندارد.' : 'تغییری برای ذخیره موقت وجود ندارد.', ['rejected' => $unchanged]);
            }
            $result = TextbotTemporary::saveTemporary($pdo, $admin, $rows, $duration, $sessionId, $revision);
            self::finish($pdo, $admin, $defaults, $result, ['rejected' => $rejected, 'saved' => count($plan['units'])]);
        }

        if ($action === 'temp_undo' || $action === 'temp_redo') {
            if ($sessionId === null || $revision === null) {
                self::fail($pdo, $admin, 409, 'session_gone', 'نشست موقت فعالی برای این عملیات وجود ندارد؛ صفحه را تازه کنید.');
            }
            $result = TextbotTemporary::move($pdo, $admin, $sessionId, $revision, $action === 'temp_undo' ? -1 : 1);
            self::finish($pdo, $admin, $defaults, $result, []);
        }

        if ($action === 'temp_restore') {
            if ($sessionId === null || $revision === null) {
                self::fail($pdo, $admin, 409, 'session_gone', 'نشست موقت فعالی برای بازگردانی وجود ندارد؛ صفحه را تازه کنید.');
            }
            $result = TextbotTemporary::restore($pdo, $admin, $sessionId, $revision);
            self::finish($pdo, $admin, $defaults, $result, ['skipped' => $result['skipped'] ?? []]);
        }

        if ($action === 'temp_commit') {
            if ($sessionId === null || $revision === null) {
                self::fail($pdo, $admin, 409, 'session_gone', 'نشست موقت فعالی برای ثبت دائمی وجود ندارد؛ صفحه را تازه کنید.');
            }
            $rows = [];
            $rejected = [];
            $saved = 0;
            if ($payload !== null) {
                $plan = TextbotSavePlanner::plan($payload, TextbotSavePlanner::currentRows($pdo, $defaults), $pdo);
                $rejected = self::rejections($plan);
                if ($rejected) {
                    self::fail($pdo, $admin, 422, 'rejected', 'برخی تغییرات معتبر نیستند؛ ثبت دائمی انجام نشد و نسخهٔ موقت حفظ شد.', ['rejected' => $rejected]);
                }
                $rows = TextbotSavePlanner::rowsOf($plan['units']);
                $saved = count($plan['units']);
            }
            $result = TextbotTemporary::commit($pdo, $admin, $sessionId, $revision, $rows);
            self::finish($pdo, $admin, $defaults, $result, ['saved' => $saved]);
        }

        self::fail($pdo, $admin, 400, 'unknown_action', 'عملیات نامعتبر است.');
    }

    private static function intField(string $name): ?int
    {
        $value = $_POST[$name] ?? null;
        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    private static function rejections(array $plan): array
    {
        $rejected = [];
        foreach ($plan['edgeRejected'] as $key) {
            $rejected[(string) $key] = ['code' => 'edge', 'message' => \Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_edge_error_message()];
        }
        foreach ($plan['tonpayErrors'] as $key => $issue) {
            $rejected[(string) $key] = ['code' => 'invalid', 'message' => (string) $issue];
        }
        foreach ($plan['richRejected'] as $key) {
            $rejected[(string) $key] = ['code' => 'rich_invalid', 'message' => (string) ($plan['richErrors'][$key] ?? 'ساختار پرمیوم این متن معتبر نیست.')];
        }
        foreach ($plan['conflicts'] as $key) {
            $rejected[(string) $key] = ['code' => 'conflict', 'message' => 'این متن پس از بارگذاری صفحه توسط مدیر دیگری یا فرایند دیگری تغییر کرده است؛ صفحه را تازه کنید.'];
        }
        foreach ($plan['missing'] as $key) {
            $rejected[(string) $key] = ['code' => 'missing', 'message' => 'این کلید دیگر در پایگاه داده وجود ندارد.'];
        }
        return $rejected;
    }

    private static function noChangeNotice(array $plan): array
    {
        $unchanged = [];
        foreach ($plan['unchanged'] as $key) {
            $unchanged[(string) $key] = ['code' => 'unchanged', 'message' => 'محتوای این متن با نسخهٔ فعلی یکسان است.'];
        }
        return $unchanged;
    }

    private static function finish(PDO $pdo, string $admin, array $defaults, array $result, array $extra): void
    {
        if (empty($result['ok'])) {
            $extra += isset($result['keys']) ? ['keys' => $result['keys']] : [];
            self::fail($pdo, $admin, (int) ($result['http'] ?? 500), (string) ($result['error'] ?? 'failed'), (string) ($result['message'] ?? 'عملیات انجام نشد.'), $extra);
        }
        $body = $extra + [
            'ok' => true,
            'changed' => TextbotSavePlanner::valuesFor($pdo, $result['affected'] ?? [], $defaults),
            'status' => TextbotTemporary::status($pdo, $admin),
        ];
        self::respond(200, $body);
    }

    private static function fail(PDO $pdo, string $admin, int $http, string $code, string $message, array $extra = []): void
    {
        self::respond($http, $extra + ['ok' => false, 'error' => $code, 'message' => $message, 'status' => TextbotTemporary::status($pdo, $admin)]);
    }

    private static function respond(int $http, array $body): void
    {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
