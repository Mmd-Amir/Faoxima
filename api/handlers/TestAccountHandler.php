<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/service_output.php';

final class TestAccountHandler extends BaseHandler
{
    public $mode = 'info';

    public function handle(): void
    {
        if ($this->mode === 'create') {
            $this->handleCreate();
            return;
        }
        $this->handleInfo();
    }


    private function policyErrorResponse(string $error): void
    {
        switch ($error) {
            case 'feature_disabled':
            case 'no_panel':
                FaoximaResponse::fail(409, faoxima_textbot_get('dyn_testaccount_service_unavailable', '📌 سرویس تست در حال حاضر در دسترس نیست.'));
                break;
            case 'verify_phone':
                FaoximaResponse::fail(403, faoxima_textbot_get('dyn_testaccount_phone_required', '📱 برای دریافت اکانت تست ابتدا باید شماره موبایل خود را تأیید کنید.'));
                break;
            case 'verify_verify':
                FaoximaResponse::fail(403, faoxima_textbot_get('dyn_testaccount_verify_required', '⚠️ حساب شما هنوز احراز هویت نشده است.'));
                break;
            case 'panel_required':
                FaoximaResponse::badRequest('country_id is required');
                break;
            case 'audience_restricted':
                FaoximaResponse::fail(403, rx_test_audience_denied_text());
                break;
            case 'limit_reached':
                FaoximaResponse::fail(403, faoxima_textbot_get('dyn_testaccount_limit_reached', '❌ سقف دریافت اکانت تست شما به پایان رسیده است.'));
                break;
            case 'stock_empty':
                FaoximaResponse::fail(409, faoxima_textbot_get('dyn_testaccount_manualsale_stock_depleted', '❌ موجودی این سرویس به پایان رسیده.'));
                break;
            default:
                FaoximaResponse::fail(404, faoxima_textbot_get('dyn_testaccount_location_unavailable', '❌ سرویس تست برای این موقعیت در دسترس نیست.'));
        }
    }


    private function freshUser(): array
    {
        $row = select('user', '*', 'id', (string)$this->user['id'], 'select', ['cache' => false]);
        return is_array($row) && !empty($row) ? $row : $this->user;
    }


    private function handleInfo(): void
    {
        $this->requireMethod('GET');

        $user = $this->freshUser();
        $isAdmin = $this->userIsAdmin();
        $legacyLeft = (int)($user['limit_usertest'] ?? 0);

        $overview = rx_test_overview($user, $isAdmin);
        if (!$overview['enabled']) {
            FaoximaResponse::ok(['available' => false, 'panels' => [], 'limit_left' => 0, 'reason' => 'feature_disabled']);
        }

        $panels = [];
        foreach ($overview['panels'] as $entry) {
            $row = $entry['panel'];
            $settings = $entry['settings'];
            $quota = $entry['quota'];
            $panels[] = [
                'id'               => (string)$row['code_panel'],
                'name'             => (string)$row['name_panel'],
                'available'        => (bool)$quota['can_create'],
                'reason'           => (string)$quota['reason'],
                'limit_left'       => $quota['remaining'] === null ? null : (int)$quota['remaining'],
                'quota_mode'       => (string)$settings['mode'],
                'quota_source'     => (string)$quota['source'],
                'manual_override'  => (bool)$quota['manual_override'],
                'audience_mode'    => (string)$quota['audience_mode'],
                'time_hours'       => rx_test_stored_hours($settings),
                'unlimited_time'   => !empty($settings['time_unlimited']),
                'volume_mb'        => rx_test_stored_mb($settings),
                'unlimited_volume' => !empty($settings['volume_unlimited']),
            ];
        }

        $quotaSummary = ['type' => 'none', 'remaining' => null, 'unlimited' => false, 'panel_id' => null];
        if (count($panels) === 1) {
            $quotaSummary = [
                'type'      => 'single_panel',
                'remaining' => $panels[0]['limit_left'],
                'unlimited' => $panels[0]['limit_left'] === null,
                'panel_id'  => $panels[0]['id'],
            ];
        } elseif (count($panels) > 1) {
            $quotaSummary = ['type' => 'per_panel', 'remaining' => null, 'unlimited' => false, 'panel_id' => null];
        }

        FaoximaResponse::ok([
            'available'      => $overview['gate'] === null && $overview['any_available'],
            'panels'         => $panels,
            'quota_summary'  => $quotaSummary,
            'limit_left'     => $legacyLeft,
            'reason'         => $overview['reason'],
            'reason_message' => $overview['reason'] === 'audience_restricted' ? rx_test_audience_denied_text() : '',
        ]);
    }


    private function handleCreate(): void
    {
        $this->requireMethod('POST');

        $user = $this->freshUser();
        $isAdmin = $this->userIsAdmin();
        $codePanel = FaoximaInput::string($this->data, 'country_id');
        $customUsername = FaoximaInput::nullableString($this->data, 'custom_username');

        $policy = rx_test_check_policy($user, $isAdmin, $codePanel === '' ? null : $codePanel);
        if (empty($policy['ok'])) {
            $this->policyErrorResponse((string)($policy['error'] ?? ''));
        }
        $panel = $policy['panel'];
        $settings = $policy['settings'];

        $methodUsername = (string)($panel['MethodUsername'] ?? '');
        $requiresCustom = ($methodUsername === 'نام کاربری دلخواه + عدد رندوم' || $methodUsername === 'متن دلخواه کاربر + رندوم');
        if ($requiresCustom) {
            $trimmedCustom = trim((string)$customUsername);
            if ($trimmedCustom === '') {
                FaoximaResponse::fail(422, faoxima_textbot_get('dyn_testaccount_username_required', 'لطفاً یک نام کاربری وارد کنید.'));
            }
            $trimmedCustom = str_replace('_', '-', $trimmedCustom);
            if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $trimmedCustom)) {
                FaoximaResponse::fail(422, faoxima_textbot_get('dyn_testaccount_username_invalid', 'نام کاربری معتبر نیست.'));
            }
            $customUsername = $trimmedCustom;
        }

        if (!rx_test_manual_stock_available($panel)) {
            $this->policyErrorResponse('stock_empty');
        }

        $reserveError = '';
        $reservation = rx_test_reserve($user, $panel, $settings, $isAdmin, 'miniapp', $reserveError);
        if ($reservation === null) {
            $this->policyErrorResponse($reserveError !== '' ? $reserveError : 'limit_reached');
        }

        $randomString = bin2hex(random_bytes(4));
        try {
            $usernameAc = rx_test_build_username($user, $panel, (string)($customUsername ?? ''), $randomString);
        } catch (Throwable $e) {
            rx_test_reservation_update($reservation, 'failed');
            rx_test_release($reservation);
            FaoximaLogger::exception($e, 'TestAccount username build failed', ['user_id' => $user['id']]);
            FaoximaResponse::serverError(faoxima_textbot_get('dyn_testaccount_create_failed_customer', '❌ خطایی در ساخت اکانت تست رخ داد. با پشتیبانی تماس بگیرید.'));
        }

        $provision = rx_test_provision($user, $panel, $settings, $reservation, $usernameAc, $randomString);
        $orderId = (string)$provision['order_id'];
        $expireTs = (int)$provision['expire'];
        $dataLimitBytes = (int)$provision['data_limit'];

        if (empty($provision['ok'])) {
            if ($provision['error'] === 'invoice_failed') {
                FaoximaLogger::error('TestAccount invoice insert failed', ['user_id' => $user['id'], 'reason' => $provision['msg']]);
                FaoximaResponse::serverError(faoxima_textbot_get('dyn_purchase_invoice_save_failed', 'خطا در ذخیره فاکتور'));
            }
            if ($provision['error'] === 'stock_empty') {
                $this->policyErrorResponse('stock_empty');
            }
            $reason = (string)$provision['msg'];
            FaoximaLogger::error('TestAccount createUser failed', [
                'user_id' => $user['id'],
                'panel'   => $panel['name_panel'],
                'reason'  => $reason,
            ]);

            $errorText = faoxima_render_text(faoxima_textbot_get('dyn_testaccount_create_failed_report_tpl', "⭕️ یک کاربر قصد دریافت اکانت تست از مینی‌اپ داشت که ساخت کانفیگ با خطا مواجه شده\n<blockquote>✍️ دلیل خطا :</blockquote>\n<blockquote>{reason}</blockquote>\n<blockquote>آیدی کابر : {user_id}</blockquote>\n<blockquote>نام کاربری کاربر : @{username}</blockquote>\n<blockquote>نام پنل : {panel_name}</blockquote>"), [
                'reason' => $reason,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'panel_name' => $panel['name_panel'],
            ]);
            $channel = $this->setting['Channel_Report'] ?? '';
            if ((string)$channel !== '') {
                $errRow = select('topicid', 'idreport', 'report', 'errorreport', 'select');
                $errTopic = is_array($errRow) ? (string)($errRow['idreport'] ?? '') : '';
                try {
                    telegram('sendmessage', [
                        'chat_id'           => $channel,
                        'message_thread_id' => $errTopic,
                        'text'              => $errorText,
                        'parse_mode'        => 'HTML',
                    ]);
                } catch (Throwable $_) {  }
            }

            FaoximaResponse::serverError(faoxima_textbot_get('dyn_testaccount_create_failed_customer', '❌ خطایی در ساخت اکانت تست رخ داد. با پشتیبانی تماس بگیرید.'));
        }

        $dataoutput = is_array($provision['output']) ? $provision['output'] : [];

        $configList = is_array($dataoutput['configs'] ?? null) ? $dataoutput['configs'] : [];
        $subLink = ($panel['sublink'] ?? '') === 'onsublink' ? (string)($dataoutput['subscription_url'] ?? '') : '';

        // For a Manualsale, the copyable "sub link" the admin attached alongside
        // a file lives in sub_link (never in the file's raw content). Resolve
        // $subLink from it so a file delivery still surfaces the link.
        $manualHasSubLink = false;
        if (($panel['type'] ?? '') === 'Manualsale') {
            $manualExtRaw = strtolower(ltrim(trim((string)($dataoutput['file_ext'] ?? '')), '.'));
            $manualSubLink = trim((string)($dataoutput['manual_sub_link'] ?? $dataoutput['sub_link'] ?? ''));
            $manualContentRaw = (string)($dataoutput['subscription_url'] ?? '');
            if ($manualExtRaw === 'sub') {
                $subLink = $manualContentRaw !== '' ? $manualContentRaw : $manualSubLink;
            } else {
                $subLink = $manualSubLink;
            }
            $manualHasSubLink = ($manualSubLink !== '');
        }

        $serviceTime = (int)$provision['hours'];
        $volumeMb = (int)$provision['volume_mb'];
        $totalBytes = $dataLimitBytes;
        $unlimitedTime = !empty($settings['time_unlimited']);
        $unlimitedVolume = !empty($settings['volume_unlimited']);

        $configsArr = array_values(array_filter(array_map(static function ($c) {
            return is_string($c) ? trim($c) : '';
        }, $configList), static function ($c) { return $c !== ''; }));

        $template = $this->resolveTestTemplate((string)($panel['type'] ?? ''));
        if (trim($template) === '') {
            $template = faoxima_textbot_get('dyn_testaccount_default_template', "✅ اکانت تست شما ساخته شد\n\n👤 نام کاربری : {username}\n🌿 نام سرویس : {name_service}\n🇺🇳 لوکیشن : {location}\n⏳ مدت زمان: {day}\n🗜 حجم بسته: {volume}");
        }
        $displayDay    = rx_test_display_time($settings);
        $displayVolume = rx_test_display_volume($settings);
        $template = str_replace('{username}', "<code>" . htmlspecialchars(guardDisplayUsername($usernameAc, $panel), ENT_QUOTES, 'UTF-8') . "</code>", $template);
        $template = str_replace('{name_service}', htmlspecialchars(faoxima_textbot_get('dyn_testaccount_product_name', 'سرویس تست'), ENT_QUOTES, 'UTF-8'), $template);
        $template = str_replace('{location}', htmlspecialchars((string)($panel['name_panel'] ?? ''), ENT_QUOTES, 'UTF-8'), $template);
        $template = str_replace('{day}', htmlspecialchars($displayDay, ENT_QUOTES, 'UTF-8'), $template);
        $template = preg_replace('/\{volume\}[ \t\x{200c}]*(?:گیگابایت|گیگ|GB|مگابایت|مگ|MB)?/iu', htmlspecialchars($displayVolume, ENT_QUOTES, 'UTF-8'), $template);
        if (function_exists('applyConnectionPlaceholders')) {
            $template = applyConnectionPlaceholders($template, $subLink, '');
        }
        if (trim($template) === '') {
            $template = faoxima_render_text(faoxima_textbot_get('dyn_testaccount_minimal_template', "✅ اکانت تست شما ساخته شد\n\n👤 نام کاربری : <code>{username}</code>"), ['username' => guardDisplayUsername($usernameAc, $panel)]);
        }

        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => faoxima_textbot_get('dyn_purchase_view_tutorial_btn', '📚 مشاهده آموزش استفاده '), 'callback_data' => 'helpbtn'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        global $setting;
        if (empty($setting) && !empty($this->setting)) {
            $setting = $this->setting;
        }


        $manualDelivered  = false;
        $manualFileName   = '';
        $manualFileB64    = '';
        $manualContentForOutput = '';
        $manualExtForOutput = '';
        $manualItemsForOutput = [];
        if (($panel['type'] ?? '') === 'Manualsale') {
            $manualRows = select('manualsell', '*', 'username', $usernameAc, 'fetchAll');
            if (!is_array($manualRows)) {
                $manualRows = [];
            }
            $manualRow = !empty($manualRows) ? $manualRows[0] : null;
            foreach ($manualRows as $mr) {
                if (!is_array($mr)) {
                    continue;
                }
                $mrContent = (string)($mr['contentrecord'] ?? '');
                if (trim($mrContent) === '') {
                    continue;
                }
                $manualItemsForOutput[] = [
                    'content'  => $mrContent,
                    'file_ext' => strtolower(ltrim(trim((string)($mr['file_ext'] ?? '')), '.')),
                    'sub_link' => trim((string)($mr['sub_link'] ?? '')),
                ];
            }
            $manualExt = '';
            $manualContent = '';
            if (is_array($manualRow)) {
                $manualExt = strtolower(ltrim(trim((string)($manualRow['file_ext'] ?? '')), '.'));
                $manualContent = (string)($manualRow['contentrecord'] ?? '');
            }
            $manualContentForOutput = $manualContent;
            $manualExtForOutput = $manualExt;
            $isManualLink = ($manualExt === 'sub');
            $isManualText = ($manualExt === 'text');
            if ($manualContent !== '' && !$isManualLink && !$isManualText) {
                $ext = ($manualExt !== '') ? $manualExt : 'bin';
                $cleanUser = preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace('_', '-', $usernameAc));
                if ($cleanUser === '') $cleanUser = 'config';
                $manualDelivered = true;
                $manualFileName = $cleanUser . '.' . $ext;
                $manualFileB64  = base64_encode($manualContent);
                $configsArr = [];
            } elseif ($isManualText && $manualContent !== '') {
                if (!in_array($manualContent, $configsArr, true)) {
                    $configsArr[] = $manualContent;
                }
            }
            if (!$manualHasSubLink && $manualDelivered) {
                $subLink = '';
            }
        }

        $deliveryOk = false;
        try {
            sendMessageService(
                $panel,
                $configsArr,
                $subLink,
                $usernameAc,
                $keyboard,
                $template,
                $orderId,
                $user['id']
            );
            $deliveryOk = true;
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'TestAccount delivery to user threw', ['user_id' => $user['id']]);
        }
        rx_test_mark_delivery($reservation, $deliveryOk);

        if (!$deliveryOk) {
            FaoximaLogger::error('TestAccount delivery to user failed', [
                'user_id'  => $user['id'],
                'order_id' => $orderId,
                'panel'    => $panel['name_panel'],
            ]);
        }

        $channel = $this->setting['Channel_Report'] ?? '';
        if ((string)$channel !== '') {
            $topicRow = select('topicid', 'idreport', 'report', 'paymentreport', 'select');
            $topicId = is_array($topicRow) ? (string)($topicRow['idreport'] ?? '') : '';
            $text = faoxima_render_text(faoxima_textbot_get('dyn_testaccount_report_tpl', "🧪 دریافت اکانت تست از مینی‌اپ\n\n<blockquote>▫️آیدی عددی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>▫️نام کاربری کاربر :@{username}</blockquote>\n<blockquote>▫️نام کاربری کانفیگ :{config_username}</blockquote>\n<blockquote>▫️موقعیت سرویس : {panel_name}</blockquote>"), [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'config_username' => guardDisplayUsername($usernameAc, $panel),
                'panel_name' => $panel['name_panel'],
            ]);
            try {
                telegram('sendmessage', [
                    'chat_id'           => $channel,
                    'message_thread_id' => $topicId,
                    'text'              => $text,
                    'parse_mode'        => 'HTML',
                ]);
            } catch (Throwable $_) {  }
        }

        FaoximaLogger::debug('Test account created via miniapp', [
            'user_id'  => $user['id'],
            'order_id' => $orderId,
            'panel'    => $panel['name_panel'],
        ]);

        FaoximaResponse::ok([
            'success'  => true,
            'order_id' => $orderId,
            'delivery_failed' => !$deliveryOk,
            'delivery_message' => $deliveryOk ? '' : faoxima_textbot_get('dyn_testaccount_delivery_failed_saved', '⚠️ اکانت تست ساخته شد اما ارسال آن در تلگرام ناموفق بود. اطلاعات سرویس در همین صفحه و بخش سرویس‌های من در دسترس است.'),
            'service'  => [
                'id'                => $orderId,
                'username'          => (string)($dataoutput['username'] ?? $usernameAc),
                'display_username'  => guardDisplayUsername((string)($dataoutput['username'] ?? $usernameAc), $panel),
                'status'            => 'active',
                'active'            => true,
                'expire'            => $expireTs,
                'product_name'      => faoxima_textbot_get('dyn_testaccount_product_name', 'سرویس تست'),
                'panel_name'        => (string)($panel['name_panel'] ?? ''),
                'panel_type'        => (string)($panel['type'] ?? ''),
                'service_time_days' => $serviceTime,
                'days_left'         => $serviceTime,
                'volume_gb'         => $totalBytes > 0 ? $totalBytes / (1024 ** 3) : 0,
                'volume_mb'         => $volumeMb,
                'volume_value'      => $volumeMb,
                'volume_unit'       => 'MB',
                'used_bytes'        => 0,
                'total_bytes'       => $totalBytes,
                'used_traffic_gb'   => 0,
                'total_traffic_gb'  => $totalBytes > 0 ? $totalBytes / (1024 ** 3) : 0,
                'unlimited_volume'  => $unlimitedVolume,
                'unlimited_time'    => $unlimitedTime,
                'subscription_url'  => $subLink,
                'configs'           => $configsArr,
                'delivered_as_file' => $manualDelivered,
                'file_name'         => $manualFileName,
                'file_content_b64'  => $manualFileB64,
                'service_output'    => faoxima_build_service_output([
                    'panel_type'   => (string)($panel['type'] ?? ''),
                    'subscription' => $subLink,
                    'configs'      => $configsArr,
                    'sub_link'     => $subLink,
                    'content'      => $manualContentForOutput,
                    'file_ext'     => $manualExtForOutput,
                    'items'        => $manualItemsForOutput,
                    'username'     => $usernameAc,
                ]),
            ],
        ]);
    }

    private function resolveTestTemplate(string $panelType): string
    {
        $rows = select('textbot', '*', null, null, 'fetchAll') ?: [];
        $bag = [
            'textafterpay' => '',
            'textmanual' => '',
            'text_wgdashboard' => '',
        ];
        foreach ($rows as $row) {
            if (isset($bag[$row['id_text']])) {
                $bag[$row['id_text']] = $row['text'];
            }
        }

        if ($panelType === 'Manualsale')  return $bag['textmanual'] ?: $bag['textafterpay'];
        if ($panelType === 'WGDashboard') return $bag['text_wgdashboard'] ?: $bag['textafterpay'];
        return $bag['textafterpay'];
    }
}
