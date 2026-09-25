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
                    return [faoxima_textbot_get('dyn_serviceaction_transfer_target_required', 'شناسه کاربر مقصد را وارد کنید.'), ''];
                }
                if (!ctype_digit($target)) {
                    return [faoxima_textbot_get('dyn_serviceaction_transfer_target_must_be_numeric', 'شناسه کاربر باید عدد باشد.'), ''];
                }
                if ($target === (string)$this->user['id']) {
                    return [faoxima_textbot_get('dyn_serviceaction_transfer_cannot_self', 'نمی‌توانید سرویس را به خودتان انتقال دهید.'), ''];
                }
                return [null, faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_transfer_target_block_tpl', '👤 کاربر مقصد: <code>{target}</code>'), ['target' => htmlspecialchars($target, ENT_QUOTES)])];

            case 'refund':
                $reason = trim((string)($payload['reason'] ?? ''));
                if (mb_strlen($reason) > 500) $reason = mb_substr($reason, 0, 500);
                return [null, $reason !== '' ? faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_refund_reason_block_tpl', '📝 توضیح کاربر: {reason}'), ['reason' => htmlspecialchars($reason, ENT_QUOTES)]) : ''];

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
                FaoximaResponse::fail(409, faoxima_textbot_get('dyn_serviceaction_miniapp_outdated', '⚠️ نسخهٔ مینی‌اپ شما قدیمی است. لطفاً صفحه را بازنشانی کنید (Pull-to-refresh یا بستن و باز کردن مینی‌اپ).'));
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

        if ($action === 'change_location') {
            $payload = FaoximaInput::array($this->data, 'payload');
            $targetPanel = trim((string)($payload['target_panel'] ?? ''));
            if ($targetPanel === '') {
                FaoximaResponse::fail(422, faoxima_textbot_get('dyn_serviceaction_select_new_location', '❌ موقعیت جدید را انتخاب کنید.'));
            }
            $this->performChangeLocation($invoice, $targetPanel);
            return;
        }


        $payload = FaoximaInput::array($this->data, 'payload');
        [$err, $extraBlock] = $this->validatePayload($action, $payload);
        if ($err !== null) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_validation_error_tpl', '❌ {err}'), ['err' => $err]));
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
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_receipt_no_admin_configured', '❌ هیچ ادمینی روی سرور تنظیم نشده است.'));
        }


        global $APIKEY;
        $apiKey = is_string($APIKEY ?? null) ? $APIKEY : '';
        if ($apiKey === '') {
            $rowKey = select('setting', 'token_bot', null, null, 'select');
            $apiKey = is_array($rowKey) ? (string)($rowKey['token_bot'] ?? '') : '';
        }
        if ($apiKey === '') {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_receipt_bot_token_missing', '❌ توکن ربات روی سرور تنظیم نشده است.'));
        }


        $userId   = (string)$this->user['id'];
        $userName = (string)($this->user['username'] ?? '');
        $userFull = trim((string)($this->user['first_name'] ?? '') . ' ' . (string)($this->user['last_name'] ?? ''));
        if ($userFull === '') $userFull = $userId;

        $label = faoxima_textbot_get('dyn_serviceaction_label_' . $action, self::ACTION_LABELS[$action]);

        $textParts = [];
        $textParts[] = $label . faoxima_textbot_get('dyn_serviceaction_from_miniapp_suffix', ' (از مینی‌اپ)');
        $textParts[] = '';
        $textParts[] = faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_user_line_tpl', '👤 کاربر: <a href="tg://user?id={user_id}">{user_full}</a>{username_part}'), [
            'user_id' => $userId,
            'user_full' => htmlspecialchars($userFull, ENT_QUOTES),
            'username_part' => $userName !== '' ? ' (@' . htmlspecialchars($userName, ENT_QUOTES) . ')' : '',
        ]);
        $textParts[] = faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_user_id_line_tpl', '🪪 شناسه عددی: <code>{user_id}</code>'), ['user_id' => htmlspecialchars($userId, ENT_QUOTES)]);
        $textParts[] = '';
        $textParts[] = faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_product_line_tpl', '🛍 محصول: {product}'), ['product' => htmlspecialchars((string)$invoice['name_product'], ENT_QUOTES)]);
        $textParts[] = faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_service_username_line_tpl', '👤 نام کاربری سرویس: <code>{username}</code>'), ['username' => htmlspecialchars(guardDisplayUsername((string)$invoice['username'], (string)($invoice['Service_location'] ?? '')), ENT_QUOTES)]);
        $textParts[] = faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_location_line_tpl', '🌍 موقعیت سرویس: {location}'), ['location' => htmlspecialchars((string)$invoice['Service_location'], ENT_QUOTES)]);
        $textParts[] = faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_invoice_id_line_tpl', '🆔 کد فاکتور: <code>{invoice_id}</code>'), ['invoice_id' => htmlspecialchars((string)$invoice['id_invoice'], ENT_QUOTES)]);
        if ($extraBlock !== '') {
            $textParts[] = '';
            $textParts[] = $extraBlock;
        }
        $textParts[] = '';
        $textParts[] = faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_time_line_tpl', '🕒 زمان: {when}'), ['when' => jdate('Y/m/d H:i:s')]);
        $text = implode("\n", $textParts);


        global $usernamebot;
        $botUsername = '';
        if (isset($usernamebot) && is_string($usernamebot)) {
            $botUsername = trim($usernamebot, " @\t\r\n");
        }

        $manageUserBtn = ($botUsername !== '' && strcasecmp($botUsername, 'NOT_USERNAME') !== 0)
            ? ['text' => faoxima_textbot_get('dyn_serviceaction_manage_user_in_bot_btn', '⚙️ مدیریت کاربر در ربات'), 'url' => 'https://t.me/' . $botUsername . '?start=manageuser_' . $userId]
            : null;

        $kbRows = [];


        if ($action === 'refund') {
            $invId = (string)($invoice['id_invoice'] ?? '');
            if ($invId !== '') {
                $kbRows[] = [
                    ['text' => faoxima_textbot_get('dyn_serviceaction_refund_auto_btn', '🔄 بازگشت خودکار (مبلغ خرید)'), 'callback_data' => 'mafurefauto-' . $invId],
                ];
                $kbRows[] = [
                    ['text' => faoxima_textbot_get('dyn_serviceaction_refund_manual_btn', '✋ بازگشت دستی (تعیین مبلغ)'),  'callback_data' => 'mafurefmanu-' . $invId],
                ];
            }
        }

        $kbRows[] = [['text' => faoxima_textbot_get('dyn_receipt_view_user_btn', '👁 مشاهده کاربر'), 'url' => 'tg://user?id=' . $userId]];
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
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_serviceaction_admin_delivery_failed', '❌ ارسال درخواست به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        FaoximaLogger::debug('Service action request notified', [
            'user'    => $this->user['id'],
            'action'  => $action,
            'invoice' => $invoice['id_invoice'],
            'admins'  => $sent,
        ]);

        FaoximaResponse::ok([
            'kind'    => 'request_sent',
            'message' => faoxima_textbot_get('dyn_serviceaction_request_sent_success', '✅ درخواست شما برای ادمین ارسال شد. ادمین پس از بررسی، با شما تماس می‌گیرد.'),
            'action'  => $action,
        ]);
    }

    private function performChangeLink(array $invoice): void
    {
        $panelName = (string)($invoice['Service_location'] ?? '');
        $svcUsername = (string)($invoice['username'] ?? '');
        if ($panelName === '' || $svcUsername === '') {
            FaoximaResponse::fail(422, faoxima_textbot_get('dyn_serviceaction_incomplete_service_info', '❌ اطلاعات سرویس ناقص است.'));
        }

        $panel = select('marzban_panel', '*', 'name_panel', $panelName, 'select');
        if (!is_array($panel) || empty($panel)) {
            FaoximaResponse::fail(404, faoxima_textbot_get('dyn_serviceaction_service_panel_not_found', '❌ پنل سرویس پیدا نشد.'));
        }


        try {
            $managePanel = new ManagePanel();
            if (!method_exists($managePanel, 'Revoke_sub')) {
                FaoximaResponse::fail(503, faoxima_textbot_get('dyn_serviceaction_changelink_unavailable', '❌ امکان تغییر لینک روی این سرور فعال نیست.'));
            }
            $result = $managePanel->Revoke_sub($panelName, $svcUsername);
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'changelink Revoke_sub threw', [
                'user'    => $this->user['id'],
                'invoice' => $invoice['id_invoice'] ?? null,
            ]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_serviceaction_changelink_error_retry', '❌ خطایی در تغییر لینک رخ داد. لطفاً دوباره تلاش کنید.'));
        }

        if (!is_array($result) || (isset($result['status']) && $result['status'] === 'Unsuccessful')) {
            $msg = is_array($result) && isset($result['msg']) ? (string)$result['msg'] : faoxima_textbot_get('dyn_serviceaction_changelink_error_short', '❌ خطایی در تغییر لینک رخ داد.');
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
            $text = faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_changelink_report_tpl', "📣 جزئیات تغییر لینک از مینی‌اپ ثبت شد .\n<blockquote>▫️آیدی عددی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>▫️نام کاربری کاربر :@{username}</blockquote>\n<blockquote>▫️نام کاربری کانفیگ :{config_username}</blockquote>\n<blockquote>▫️نام کاربر : {first_name}</blockquote>\n<blockquote>▫️موقعیت سرویس : {panel_name}</blockquote>\n<blockquote>▫️نوع کاربر : {agent}</blockquote>\n<blockquote>▫️زمان تغییر لینک : {when}</blockquote>"), [
                'user_id' => $this->user['id'],
                'username' => $userName,
                'config_username' => guardDisplayUsername($svcUsername, $panel),
                'first_name' => $userFirst,
                'panel_name' => $panelName,
                'agent' => $agent,
                'when' => $timejalali,
            ]);

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
            'message'          => faoxima_textbot_get('dyn_serviceaction_changelink_success', '✅ لینک سرویس شما با موفقیت تغییر کرد.'),
            'username'         => $svcUsername,
            'subscription_url' => $newSubLink,
            'configs'          => $newConfigs,
        ]);
    }

    private function performChangeLocation(array $invoice, string $targetPanelCode): void
    {
        $svcUsername = (string)($invoice['username'] ?? '');
        $oldPanelName = (string)($invoice['Service_location'] ?? '');
        if ($svcUsername === '' || $oldPanelName === '') {
            FaoximaResponse::fail(422, faoxima_textbot_get('dyn_serviceaction_incomplete_service_info', '❌ اطلاعات سرویس ناقص است.'));
        }

        $oldPanel = select('marzban_panel', '*', 'name_panel', $oldPanelName, 'select');
        if (!is_array($oldPanel) || empty($oldPanel)) {
            FaoximaResponse::fail(404, faoxima_textbot_get('dyn_serviceaction_current_panel_not_found', '❌ پنل فعلی سرویس پیدا نشد.'));
        }

        $newPanel = select('marzban_panel', '*', 'code_panel', $targetPanelCode, 'select');
        if (!is_array($newPanel) || empty($newPanel)) {
            FaoximaResponse::fail(404, faoxima_textbot_get('dyn_serviceaction_new_location_not_found', '❌ موقعیت جدید پیدا نشد.'));
        }

        if (($newPanel['changeloc'] ?? '') === 'offchangeloc') {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_serviceaction_changeloc_unavailable_for_target', '❌ این قابلیت برای موقعیت مقصد در دسترس نیست.'));
        }

        $marzbanCount = (int) FaoximaDb::fetchScalar(
            "SELECT COUNT(*) FROM marzban_panel WHERE status = 'active'"
        );
        if ($marzbanCount <= 1) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_serviceaction_no_other_location', '❌ موقعیت دیگری برای انتقال وجود ندارد.'));
        }
        if ((string)$newPanel['code_panel'] === (string)$oldPanel['code_panel']) {
            FaoximaResponse::fail(422, faoxima_textbot_get('dyn_serviceaction_target_same_as_current', '❌ موقعیت مقصد نمی‌تواند همان موقعیت فعلی باشد.'));
        }

        $limitJson = json_decode((string)($this->setting['limitnumber'] ?? ''), true);
        $limitAll  = (int)($limitJson['all']  ?? 0);
        $limitFreeMax = (int)($limitJson['free'] ?? 0);
        $limitEnforced = (int)($this->setting['statuslimitchangeloc'] ?? 0) === 1;
        $userLimitUsed = (int)($this->user['limitchangeloc'] ?? 0);

        if ($limitEnforced && $userLimitUsed >= $limitAll) {
            FaoximaResponse::fail(403, faoxima_textbot_get('dyn_serviceaction_changeloc_limit_reached', '❌ محدودیت تغییر لوکیشن شما به پایان رسیده است.'));
        }
        $isFree = !$limitEnforced || $userLimitUsed < $limitFreeMax;

        $agent = (string)($this->user['agent'] ?? 'f');

        if ((string)($invoice['name_product'] ?? '') === '🛍 حجم دلخواه' || (string)($invoice['name_product'] ?? '') === '⚙️ سرویس دلخواه') {
            $product = ['inbounds' => null];
        } else {
            $product = FaoximaDb::fetchOne(
                "SELECT * FROM product WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND name_product = :name AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))",
                [':loc' => $invoice['Service_location'], ':name' => $invoice['name_product'], ':agent' => $agent]
            );
        }

        if (($newPanel['type'] ?? '') === 'Manualsale' && ($oldPanel['url_panel'] ?? '') === ($newPanel['url_panel'] ?? '')) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_serviceaction_transfer_to_panel_unavailable', '❌ امکان انتقال به این پنل وجود ندارد.'));
        }

        $managePanel = new ManagePanel();
        $remoteUser = $managePanel->DataUser($oldPanelName, $svcUsername);
        if (!is_array($remoteUser) || ($remoteUser['status'] ?? '') === 'on_hold') {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_serviceaction_config_on_hold_no_transfer', '❌ کانفیگ شما در وضعیت استفاده‌نشده است و امکان انتقال موقعیت وجود ندارد.'));
        }
        if (($remoteUser['status'] ?? '') !== 'active') {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_serviceaction_status_forbids_transfer', '❌ وضعیت سرویس فعلی اجازه انتقال نمی‌دهد.'));
        }

        $priceChange = $isFree ? 0 : (float)($newPanel['priceChangeloc'] ?? 0);

        $discount = (int)($this->user['pricediscount'] ?? 0);
        if ($discount !== 0 && $priceChange > 0) {
            $priceChange = $priceChange - (($priceChange * $discount) / 100);
        }

        $balance = (float)($this->user['Balance'] ?? 0);
        $maxBuyAgent = (int)($this->user['maxbuyagent'] ?? 0);

        if ($priceChange > $balance) {
            if ($agent === 'n2' && $maxBuyAgent !== 0) {
                if (($balance - $priceChange) < (-1 * $maxBuyAgent)) {
                    FaoximaResponse::fail(402, faoxima_textbot_get('dyn_serviceaction_purchase_limit_exhausted', '❌ مبلغ مجاز خرید شما به اتمام رسیده است.'));
                }
            } elseif ($agent !== 'n2') {
                FaoximaResponse::fail(402, faoxima_textbot_get('dyn_serviceaction_insufficient_balance_changeloc', '❌ موجودی کیف پول شما برای انتقال موقعیت کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.'));
            }
        }

        $balanceCharged = false;
        if ($priceChange > 0) {
            $allowNeg = ($agent === 'n2') ? $maxBuyAgent : 0;
            $charge = balance_atomic_charge($this->user['id'], $priceChange, $allowNeg);
            if (empty($charge['ok'])) {
                FaoximaResponse::fail(402, faoxima_textbot_get('dyn_purchase_concurrent_balance_conflict', 'موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.'));
            }
            $balanceCharged = true;
            if (function_exists('wallet_ledger_record')) {
                wallet_ledger_record($this->user['id'], 'debit', $priceChange, 'service_action', faoxima_textbot_get('dyn_serviceaction_changeloc_ledger_debit_note', 'تغییر موقعیت سرویس'), null, 'invoice', (string)($invoice['id_invoice'] ?? ''));
            }
        }

        if (is_array($product) && ($product['inbounds'] ?? null) !== null) {
            $newPanel['inboundid'] = $product['inbounds'];
        }

        $dataLimit = 0;
        if (($remoteUser['data_limit'] ?? 0) != 0) {
            $dataLimit = (int)($remoteUser['data_limit'] ?? 0) - (int)($remoteUser['used_traffic'] ?? 0);
        }

        $datac = [
            'expire'     => $remoteUser['expire'] ?? null,
            'data_limit' => $dataLimit,
            'from_id'    => $this->user['id'],
            'username'   => $svcUsername,
            'type'       => 'usertest',
            'ip_limit'   => (int)($invoice['ip_limit'] ?? 0),
        ];

        try {
            if (($oldPanel['url_panel'] ?? '') === ($newPanel['url_panel'] ?? '')) {
                $managePanel->RemoveUser($oldPanelName, $svcUsername);
                $remoteOut = $managePanel->createUser($newPanel['name_panel'], 'usertest', $svcUsername, $datac);
            } else {
                $remoteOut = $managePanel->createUser($newPanel['name_panel'], 'usertest', $svcUsername, $datac);
                if (empty($remoteOut['username'])) {
                    if ($balanceCharged) {
                        if (balance_atomic_credit($this->user['id'], $priceChange)) {
                            if (function_exists('wallet_ledger_record')) {
                                wallet_ledger_record($this->user['id'], 'credit', $priceChange, 'refund', faoxima_textbot_get('dyn_serviceaction_changeloc_refund_note', 'بازگشت وجه به دلیل خطای تغییر موقعیت'), null, 'invoice', (string)($invoice['id_invoice'] ?? ''));
                            }
                        } else {
                            FaoximaLogger::error('refund credit failed', ['user' => $this->user['id'], 'amount' => $priceChange]);
                        }
                    }
                    $reason = is_array($remoteOut) ? rx_panel_error_text($remoteOut['msg'] ?? $remoteOut, $remoteOut['detail'] ?? null) : htmlspecialchars((string)$remoteOut, ENT_QUOTES, 'UTF-8');
                    FaoximaLogger::error('change_location createUser failed', [
                        'user' => $this->user['id'], 'reason' => $reason,
                    ]);
                    FaoximaResponse::fail(502, faoxima_textbot_get('dyn_serviceaction_changeloc_generic_error', '❌ خطایی هنگام تغییر موقعیت رخ داد. با پشتیبانی در ارتباط باشید.'));
                }
                $managePanel->RemoveUser($oldPanelName, $svcUsername);
            }
        } catch (Throwable $e) {
            if ($balanceCharged) {
                if (balance_atomic_credit($this->user['id'], $priceChange)) {
                    if (function_exists('wallet_ledger_record')) {
                        wallet_ledger_record($this->user['id'], 'credit', $priceChange, 'refund', faoxima_textbot_get('dyn_serviceaction_changeloc_refund_note', 'بازگشت وجه به دلیل خطای تغییر موقعیت'), null, 'invoice', (string)($invoice['id_invoice'] ?? ''));
                    }
                } else {
                    FaoximaLogger::error('refund credit failed', ['user' => $this->user['id'], 'amount' => $priceChange]);
                }
            }
            FaoximaLogger::exception($e, 'change_location panel swap threw', ['user' => $this->user['id']]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_serviceaction_changeloc_generic_error', '❌ خطایی هنگام تغییر موقعیت رخ داد. با پشتیبانی در ارتباط باشید.'));
        }

        if (empty($remoteOut['username'])) {
            if ($balanceCharged) {
                if (balance_atomic_credit($this->user['id'], $priceChange)) {
                    if (function_exists('wallet_ledger_record')) {
                        wallet_ledger_record($this->user['id'], 'credit', $priceChange, 'refund', faoxima_textbot_get('dyn_serviceaction_changeloc_refund_note', 'بازگشت وجه به دلیل خطای تغییر موقعیت'), null, 'invoice', (string)($invoice['id_invoice'] ?? ''));
                    }
                } else {
                    FaoximaLogger::error('refund credit failed', ['user' => $this->user['id'], 'amount' => $priceChange]);
                }
            }
            $reason = is_array($remoteOut) ? rx_panel_error_text($remoteOut['msg'] ?? $remoteOut, $remoteOut['detail'] ?? null) : htmlspecialchars((string)$remoteOut, ENT_QUOTES, 'UTF-8');
            FaoximaLogger::error('change_location createUser failed (same domain)', [
                'user' => $this->user['id'], 'reason' => $reason,
            ]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_serviceaction_changeloc_generic_error', '❌ خطایی هنگام تغییر موقعیت رخ داد. با پشتیبانی در ارتباط باشید.'));
        }

        update('user', 'limitchangeloc', $userLimitUsed + 1, 'id', $this->user['id']);
        update('invoice', 'Service_location', $newPanel['name_panel'], 'username', $svcUsername);
        if (($newPanel['inboundid'] ?? null) !== null) {
            update('invoice', 'inboundid', $newPanel['inboundid'], 'username', $svcUsername);
        }

        $subLink = ($newPanel['sublink'] ?? '') === 'onsublink' ? (string)($remoteOut['subscription_url'] ?? '') : '';
        $newConfigs = is_array($remoteOut['configs'] ?? null)
            ? array_values(array_filter(array_map('strval', $remoteOut['configs']), static function ($c) { return trim($c) !== ''; }))
            : [];

        $channel = (string)($this->setting['Channel_Report'] ?? '');
        if ($channel !== '' && function_exists('telegram')) {
            $userName = (string)($this->user['username'] ?? '');
            $balanceAfter = number_format((float) FaoximaDb::fetchScalar('SELECT Balance FROM user WHERE id = :id', [':id' => $this->user['id']]));
            $timejalali = function_exists('jdate') ? jdate('Y/m/d H:i:s') : date('Y/m/d H:i:s');
            $text = faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_changeloc_report_tpl', "📍 تغییر موقعیت سرویس (از مینی‌اپ)\n\n<blockquote>▫️آیدی عددی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>▫️نام کاربری کاربر : @{username}</blockquote>\n<blockquote>▫️نام کاربری کانفیگ : {config_username}</blockquote>\n<blockquote>▫️پنل قدیم : {old_panel}</blockquote>\n<blockquote>▫️پنل جدید : {new_panel}</blockquote>\n<blockquote>▫️موجودی کاربر : {balance} تومان</blockquote>\n<blockquote>▫️زمان : {when}</blockquote>"), [
                'user_id' => $this->user['id'],
                'username' => $userName,
                'config_username' => guardDisplayUsername($svcUsername, $newPanel),
                'old_panel' => $oldPanelName,
                'new_panel' => $newPanel['name_panel'],
                'balance' => $balanceAfter,
                'when' => $timejalali,
            ]);
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

        FaoximaLogger::debug('Service change_location completed', [
            'user'      => $this->user['id'],
            'invoice'   => $invoice['id_invoice'] ?? null,
            'old_panel' => $oldPanelName,
            'new_panel' => $newPanel['name_panel'],
        ]);

        FaoximaResponse::ok([
            'kind'             => 'change_location_done',
            'message'          => faoxima_render_text(faoxima_textbot_get('dyn_serviceaction_changeloc_success_tpl', '✅ موقعیت سرویس شما با موفقیت به «{panel_name}» تغییر کرد.'), ['panel_name' => $newPanel['name_panel']]),
            'username'         => $svcUsername,
            'panel_name'       => (string)$newPanel['name_panel'],
            'subscription_url' => $subLink,
            'configs'          => $newConfigs,
        ]);
    }

    private function sendAdminMessage(string $apiKey, string $chatId, string $text, string $keyboardJson): bool
    {
        if (function_exists('applyPremiumEmojiTransform')) { $text = applyPremiumEmojiTransform($text, 'HTML'); }
        $url = 'https://api.telegram.org/bot' . $apiKey . '/sendMessage';
        $payload = [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => $keyboardJson,
            'disable_web_page_preview' => true,
        ];
        $ch = curl_init($url);
        if (function_exists('faoxima_apply_curl_proxy')) faoxima_apply_curl_proxy($ch, 'telegram');
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

