<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class ServiceActionHandler extends BaseHandler
{


    private const ACTION_LABELS = [
        'changelink'      => '🔄 درخواست تغییر لینک',
        'refund'          => '💎 درخواست بازگشت وجه',
        'transfer'        => '↪️ درخواست انتقال به کاربر دیگر',
        'change_location' => '📍 درخواست تغییر موقعیت',
    ];


    private function validatePayload(string $action, array $payload): array
    {
        switch ($action) {
            case 'transfer':
                $target = trim((string)($payload['target_user_id'] ?? ''));
                if ($target === '') {
                    return ['شناسه کاربر مقصد را وارد کنید.', ''];
                }
                if (!ctype_digit($target)) {
                    return ['شناسه کاربر باید عدد باشد.', ''];
                }
                if ($target === (string)$this->user['id']) {
                    return ['نمی‌توانید سرویس را به خودتان انتقال دهید.', ''];
                }
                return [null, "👤 کاربر مقصد: <code>" . htmlspecialchars($target, ENT_QUOTES) . "</code>"];

            case 'change_location':
                $target = trim((string)($payload['target_panel'] ?? ''));
                if ($target === '') {
                    return ['موقعیت جدید را انتخاب کنید.', ''];
                }
                if (mb_strlen($target) > 200) {
                    return ['نام موقعیت بیش از حد طولانی است.', ''];
                }
                return [null, "🌍 موقعیت پیشنهادی: <code>" . htmlspecialchars($target, ENT_QUOTES) . "</code>"];

            case 'refund':
                $reason = trim((string)($payload['reason'] ?? ''));
                if (mb_strlen($reason) > 500) $reason = mb_substr($reason, 0, 500);
                return [null, $reason !== '' ? "📝 توضیح کاربر: " . htmlspecialchars($reason, ENT_QUOTES) : ''];

            case 'changelink':
            default:
                return [null, ''];
        }
    }


    private const LEGACY_ACTIONS = [
        'renew', 'extra_time', 'extra_volume',
        'toggle_status', 'note', 'report_problem',
        'update_info', 'config', 'subscription',
    ];

    public function handle(): void
    {
        $this->requireMethod('POST');

        $action   = FaoximaInput::string($this->data, 'action');
        $username = FaoximaInput::string($this->data, 'username');

        if ($action === '' || !isset(self::ACTION_LABELS[$action])) {


            if (in_array($action, self::LEGACY_ACTIONS, true)) {
                FaoximaResponse::fail(409,
                    '⚠️ نسخهٔ مینی‌اپ شما قدیمی است. لطفاً صفحه را بازنشانی کنید (Pull-to-refresh یا بستن و باز کردن مینی‌اپ).');
            }
            FaoximaResponse::badRequest('Invalid action');
        }
        if ($username === '') {
            FaoximaResponse::badRequest('username is required');
        }


        $invoice = FaoximaDb::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            FaoximaResponse::notFound('Service not found');
        }


        if ($action === 'changelink') {
            $this->performChangeLink($invoice);
            return;
        }


        $payload = FaoximaInput::array($this->data, 'payload');
        [$err, $extraBlock] = $this->validatePayload($action, $payload);
        if ($err !== null) {
            FaoximaResponse::fail(422, '❌ ' . $err);
        }


        $adminIds = [];
        try {
            $rows = FaoximaDb::fetchAll(
                "SELECT id_admin FROM admin
                  WHERE rule = 'administrator'
                     OR rule = 'Seller'"
            );
            foreach ($rows as $r) {
                $id = trim((string)($r['id_admin'] ?? ''));
                if ($id !== '' && ctype_digit($id)) $adminIds[] = $id;
            }
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('admin table fetch failed (service_action)', ['err' => $e->getMessage()]);
        }
        if (empty($adminIds)) {
            FaoximaResponse::fail(503, '❌ هیچ ادمینی روی سرور تنظیم نشده است.');
        }


        global $APIKEY;
        $apiKey = is_string($APIKEY ?? null) ? $APIKEY : '';
        if ($apiKey === '') {
            $rowKey = select('setting', 'token_bot', null, null, 'select');
            $apiKey = is_array($rowKey) ? (string)($rowKey['token_bot'] ?? '') : '';
        }
        if ($apiKey === '') {
            FaoximaResponse::fail(503, '❌ توکن ربات روی سرور تنظیم نشده است.');
        }


        $userId   = (string)$this->user['id'];
        $userName = (string)($this->user['username'] ?? '');
        $userFull = trim((string)($this->user['first_name'] ?? '') . ' ' . (string)($this->user['last_name'] ?? ''));
        if ($userFull === '') $userFull = $userId;

        $label = self::ACTION_LABELS[$action];

        $textParts = [];
        $textParts[] = $label . ' (از مینی‌اپ)';
        $textParts[] = '';
        $textParts[] = '👤 کاربر: <a href="tg://user?id=' . $userId . '">' . htmlspecialchars($userFull, ENT_QUOTES) . '</a>'
                     . ($userName !== '' ? ' (@' . htmlspecialchars($userName, ENT_QUOTES) . ')' : '');
        $textParts[] = '🪪 شناسه عددی: <code>' . htmlspecialchars($userId, ENT_QUOTES) . '</code>';
        $textParts[] = '';
        $textParts[] = '🛍 محصول: ' . htmlspecialchars((string)$invoice['name_product'], ENT_QUOTES);
        $textParts[] = '👤 نام کاربری سرویس: <code>' . htmlspecialchars((string)$invoice['username'], ENT_QUOTES) . '</code>';
        $textParts[] = '🌍 موقعیت سرویس: ' . htmlspecialchars((string)$invoice['Service_location'], ENT_QUOTES);
        $textParts[] = '🆔 کد فاکتور: <code>' . htmlspecialchars((string)$invoice['id_invoice'], ENT_QUOTES) . '</code>';
        if ($extraBlock !== '') {
            $textParts[] = '';
            $textParts[] = $extraBlock;
        }
        $textParts[] = '';
        $textParts[] = '🕒 زمان: ' . jdate('Y/m/d H:i:s');
        $text = implode("\n", $textParts);


        global $usernamebot;
        $botUsername = '';
        if (isset($usernamebot) && is_string($usernamebot)) {
            $botUsername = trim($usernamebot, " @\t\r\n");
        }

        $manageUserBtn = ($botUsername !== '' && strcasecmp($botUsername, 'NOT_USERNAME') !== 0)
            ? ['text' => '⚙️ مدیریت کاربر در ربات', 'url' => 'https://t.me/' . $botUsername . '?start=manageuser_' . $userId]
            : null;

        $kbRows = [];


        if ($action === 'refund') {
            $invId = (string)($invoice['id_invoice'] ?? '');
            if ($invId !== '') {
                $kbRows[] = [
                    ['text' => '🔄 بازگشت خودکار (مبلغ خرید)', 'callback_data' => 'mafurefauto-' . $invId],
                ];
                $kbRows[] = [
                    ['text' => '✋ بازگشت دستی (تعیین مبلغ)',  'callback_data' => 'mafurefmanu-' . $invId],
                ];
            }
        }

        $kbRows[] = [['text' => '👁 مشاهده کاربر', 'url' => 'tg://user?id=' . $userId]];
        if ($manageUserBtn !== null) {
            $kbRows[] = [$manageUserBtn];
        }
        $keyboard = json_encode(['inline_keyboard' => $kbRows], JSON_UNESCAPED_UNICODE);


        $sent = 0;
        foreach ($adminIds as $adminId) {
            if ($this->sendAdminMessage($apiKey, $adminId, $text, $keyboard)) {
                $sent++;
            }
        }
        if ($sent === 0) {
            FaoximaResponse::fail(502, '❌ ارسال درخواست به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.');
        }

        FaoximaLogger::debug('Service action request notified', [
            'user'    => $this->user['id'],
            'action'  => $action,
            'invoice' => $invoice['id_invoice'],
            'admins'  => $sent,
        ]);

        FaoximaResponse::ok([
            'kind'    => 'request_sent',
            'message' => '✅ درخواست شما برای ادمین ارسال شد. ادمین پس از بررسی، با شما تماس می‌گیرد.',
            'action'  => $action,
        ]);
    }

    private function performChangeLink(array $invoice): void
    {
        $panelName = (string)($invoice['Service_location'] ?? '');
        $svcUsername = (string)($invoice['username'] ?? '');
        if ($panelName === '' || $svcUsername === '') {
            FaoximaResponse::fail(422, '❌ اطلاعات سرویس ناقص است.');
        }

        $panel = select('marzban_panel', '*', 'name_panel', $panelName, 'select');
        if (!is_array($panel) || empty($panel)) {
            FaoximaResponse::fail(404, '❌ پنل سرویس پیدا نشد.');
        }
        if (function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel((array)$panel)) {
            $emergencyMap = nmEmergencyReplacementMap();
            $srcCode = (string)$panel['code_panel'];
            if (isset($emergencyMap['by_code'][$srcCode])) {
                $panel = $emergencyMap['by_code'][$srcCode];
            }
        }


        try {
            $managePanel = new ManagePanel();
            if (!method_exists($managePanel, 'Revoke_sub')) {
                FaoximaResponse::fail(503, '❌ امکان تغییر لینک روی این سرور فعال نیست.');
            }
            $result = $managePanel->Revoke_sub($panelName, $svcUsername);
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'changelink Revoke_sub threw', [
                'user'    => $this->user['id'],
                'invoice' => $invoice['id_invoice'] ?? null,
            ]);
            FaoximaResponse::fail(502, '❌ خطایی در تغییر لینک رخ داد. لطفاً دوباره تلاش کنید.');
        }

        if (!is_array($result) || (isset($result['status']) && $result['status'] === 'Unsuccessful')) {
            $msg = is_array($result) && isset($result['msg']) ? (string)$result['msg'] : '❌ خطایی در تغییر لینک رخ داد.';
            FaoximaLogger::warn('changelink Revoke_sub failed', [
                'user'    => $this->user['id'],
                'invoice' => $invoice['id_invoice'] ?? null,
                'result'  => $result,
            ]);
            FaoximaResponse::fail(502, $msg);
        }


        $newSubLink = '';
        if (($panel['sublink'] ?? '') === 'onsublink') {
            $newSubLink = (string)($result['subscription_url'] ?? '');
        }
        $newConfigs = is_array($result['configs'] ?? null)
            ? array_values(array_filter(array_map('strval', $result['configs']), static function ($c) { return trim($c) !== ''; }))
            : [];


        $timejalali = function_exists('jdate') ? jdate('Y/m/d H:i:s') : date('Y/m/d H:i:s');
        $channel = $this->setting['Channel_Report'] ?? '';
        if ((string)$channel !== '' && function_exists('telegram')) {
            $userName = (string)($this->user['username'] ?? '');
            $userFirst = (string)($this->user['first_name'] ?? '');
            $agent    = (string)($this->user['agent'] ?? '');
            $text = "📣 جزئیات تغییر لینک از مینی‌اپ ثبت شد .
▫️آیدی عددی کاربر : <code>{$this->user['id']}</code>
▫️نام کاربری کاربر :@{$userName}
▫️نام کاربری کانفیگ :{$svcUsername}
▫️نام کاربر : {$userFirst}
▫️موقعیت سرویس : {$panelName}
▫️نوع کاربر : {$agent}
▫️زمان تغییر لینک : {$timejalali}";

            $otherservice = (string)(select('topicid', 'idreport', 'report', 'otherservice', 'select')['idreport'] ?? '');
            try {
                telegram('sendmessage', [
                    'chat_id'           => $channel,
                    'message_thread_id' => $otherservice,
                    'text'              => $text,
                    'parse_mode'        => 'HTML',
                ]);
            } catch (Throwable $_) {  }
        }

        FaoximaLogger::debug('Service changelink completed', [
            'user'    => $this->user['id'],
            'invoice' => $invoice['id_invoice'] ?? null,
        ]);

        FaoximaResponse::ok([
            'kind'             => 'changelink_done',
            'message'          => '✅ لینک سرویس شما با موفقیت تغییر کرد.',
            'username'         => $svcUsername,
            'subscription_url' => $newSubLink,
            'configs'          => $newConfigs,
        ]);
    }

    private function sendAdminMessage(string $apiKey, string $chatId, string $text, string $keyboardJson): bool
    {
        $url = 'https://api.telegram.org/bot' . $apiKey . '/sendMessage';
        $payload = [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => $keyboardJson,
            'disable_web_page_preview' => true,
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            FaoximaLogger::warn('sendMessage to admin failed', ['admin' => $chatId, 'http' => $httpCode, 'err' => $err]);
            return false;
        }
        $tg = json_decode((string)$response, true);
        if (!is_array($tg) || empty($tg['ok'])) {
            $desc = is_array($tg) ? (string)($tg['description'] ?? '') : '';
            FaoximaLogger::warn('Telegram rejected admin message', ['admin' => $chatId, 'desc' => $desc]);
            return false;
        }
        return true;
    }
}

