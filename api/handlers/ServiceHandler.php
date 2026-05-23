<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class ServiceHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $username = FaoximaInput::nullableString($this->data, 'username');
        if ($username === null) {
            FaoximaResponse::badRequest('username is required');
        }

        $invoice = $this->lookupInvoice($username);
        if ($invoice === null) {
            FaoximaResponse::notFound('Service not found');
        }

        $payload = $this->buildPayloadFromInvoice($invoice);
        if ($payload === null) {
            FaoximaResponse::fail(502, 'Service data unavailable');
        }

        FaoximaResponse::ok($payload);
    }


    public function lookupInvoice(string $username): ?array
    {
        return FaoximaDb::fetchOne(
            "SELECT * FROM invoice
              WHERE id_user = :user_id
                AND (Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume'
                     OR Status = 'sendedwarn' OR Status = 'send_on_hold')
                AND username = :username",
            [
                ':user_id'  => $this->user['id'],
                ':username' => $username,
            ]
        );
    }


    public function buildPayloadFromInvoice(array $invoice): ?array
    {

        $stock = $this->resolveStockRow($invoice);
        if ($stock !== null) {
            return $this->buildStockPayload($invoice, $stock);
        }

        $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (empty($panel)) {
            return null;
        }

        $managePanel = new ManagePanel();
        $remote = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);

        if (!is_array($remote) || !array_key_exists('data_limit', $remote) || !array_key_exists('used_traffic', $remote)) {
            FaoximaLogger::userFacing('Panel returned incomplete user data', [
                'user_id'  => $this->user['id'],
                'panel'    => $invoice['Service_location'],
                'username' => $invoice['username'],
                'remote'   => $remote,
            ]);
            return null;
        }

        $dataLimitBytes = is_numeric($remote['data_limit']) ? (float)$remote['data_limit'] : 0.0;
        $usedBytes      = is_numeric($remote['used_traffic']) ? (float)$remote['used_traffic'] : 0.0;
        $remainingBytes = max($dataLimitBytes - $usedBytes, 0);

        $totalGb     = $dataLimitBytes / pow(1024, 3);
        $usedGb      = $usedBytes / pow(1024, 3);
        $remainingGb = $remainingBytes / pow(1024, 3);

        $config = [];
        $type = $panel['type'] ?? '';
        if (in_array($type, ['marzban', 'marzneshin', 'alireza_single', 'x-ui_single', 'hiddify', 'eylanpanel'], true)) {
            if (($panel['sublink'] ?? '') === 'onsublink' && !empty($remote['subscription_url'])) {
                $config[] = [
                    'type'  => 'link',
                    'value' => $remote['subscription_url'],
                ];
            }
            if (($panel['config'] ?? '') === 'onconfig' && !empty($remote['links'])) {
                $config[] = [
                    'type'  => 'config',
                    'value' => $remote['links'],
                ];
            }
        } elseif ($type === 'WGDashboard') {
            $config[] = [
                'type'     => 'file',
                'value'    => $remote['subscription_url'] ?? '',
                'filename' => ($panel['inboundid'] ?? 'cfg') . '_' . $invoice['id_user'] . '_' . $invoice['id_invoice'] . '.config',
            ];
        } elseif (in_array($type, ['mikrotik', 'ibsng'], true)) {
            $config[] = [
                'type'  => 'password',
                'value' => $remote['password'] ?? '',
            ];
        }

        $lastUpdate = null;
        if (!empty($remote['sub_updated_at'])) {
            try {
                $dt = new DateTime($remote['sub_updated_at'], new DateTimeZone('UTC'));
                $dt->setTimezone(new DateTimeZone('Asia/Tehran'));
                $lastUpdate = jdate('Y/m/d H:i:s', $dt->getTimestamp());
            } catch (Throwable $e) {
                FaoximaLogger::userFacing('Failed to parse sub_updated_at', ['err' => $e->getMessage()]);
            }
        }

        $onlineRaw = $remote['online_at'] ?? null;
        if ($onlineRaw === 'online')        $lastOnline = 'آنلاین';
        elseif ($onlineRaw === 'offline')   $lastOnline = 'آفلاین';
        elseif ($onlineRaw === null)        $lastOnline = 'متصل نشده';
        else                                $lastOnline = jdate('Y/m/d H:i:s', strtotime((string)$onlineRaw));

        $expireTs = isset($remote['expire']) && is_numeric($remote['expire']) ? (int)$remote['expire'] : 0;
        $expirationDate = $expireTs > 0 ? jdate('Y/m/d', $expireTs) : 'نامحدود';

        $disabledActions = $this->computeDisabledActions($panel, $invoice, $remote, $config);

        $isTest = ($invoice['name_product'] ?? '') === 'سرویس تست';

        $subscriptionUrl = '';
        foreach ($config as $entry) {
            if (($entry['type'] ?? '') === 'link' && !empty($entry['value'])) {
                $subscriptionUrl = (string)$entry['value'];
                break;
            }
        }

        return [
            'status'                   => $remote['status'] ?? 'unknown',
            'username'                 => $remote['username'] ?? $invoice['username'],
            'product_name'             => $invoice['name_product'],
            'is_test'                  => $isTest,
            'is_stock'                 => false,
            'invoice_status'           => $invoice['Status'] ?? '',
            'panel_type'               => $type,
            'total_traffic_gb'         => round($totalGb, 2),
            'used_traffic_gb'          => round($usedGb, 2),
            'remaining_traffic_gb'     => round($remainingGb, 2),
            'expiration_time'          => $expirationDate,
            'last_subscription_update' => $lastUpdate,
            'online_at'                => $lastOnline,
            'service_output'           => $config,
            'subscription_url'         => $subscriptionUrl,
            'disabled_actions'         => $disabledActions,
            'note'                     => (string)($invoice['note'] ?? ''),
        ];
    }


    private function resolveStockRow(array $invoice): ?array
    {
        $iid          = trim((string)($invoice['id_invoice']    ?? ''));
        $userInfo     = trim((string)($invoice['user_info']     ?? ''));
        $sourcePanel  = trim((string)($invoice['source_panel_code'] ?? ''));


        if ($userInfo === '' && $sourcePanel === '') {
            return null;
        }


        try {
            $row = FaoximaDb::fetchOne(
                "SELECT * FROM nm_config_stock
                  WHERE assigned_invoice = :i
                  ORDER BY CASE status
                              WHEN 'delivered' THEN 0
                              WHEN 'reserved'  THEN 1
                              WHEN 'disabled'  THEN 2
                              ELSE 3
                           END,
                           COALESCE(delivered_at, reserved_at, created_at, 0) DESC,
                           id DESC
                  LIMIT 1",
                [':i' => $iid]
            );
            if (is_array($row)) {
                return $row;
            }


            if ($userInfo !== '') {
                $row = FaoximaDb::fetchOne(
                    "SELECT * FROM nm_config_stock WHERE content = :c LIMIT 1",
                    [':c' => $userInfo]
                );
                if (is_array($row)) {
                    return $row;
                }
            }
        } catch (Throwable $e) {


            FaoximaLogger::info('nm_config_stock lookup failed', ['err' => $e->getMessage()]);
        }


        if ($userInfo !== '' && $sourcePanel !== '') {
            return [
                'id'            => null,
                'content'       => $userInfo,
                'sub_link'      => '',
                'format'        => $this->detectStockFormat($userInfo),
                'status'        => 'delivered',
                'shelf_id'      => null,
            ];
        }
        return null;
    }


    private function buildStockPayload(array $invoice, array $stock): array
    {
        $content = trim((string)($stock['content']  ?? ($invoice['user_info'] ?? '')));
        $subLink = trim((string)($stock['sub_link'] ?? ''));


        if ($subLink === '' && preg_match('/^https?:\/\//i', $content)) {
            $subLink = $content;
        }


        $output = [];
        if ($subLink !== '') {
            $output[] = ['type' => 'link', 'value' => $subLink];
        }
        if ($content !== '' && $content !== $subLink) {

            $format = strtolower((string)($stock['format'] ?? '')) ?: $this->detectStockFormat($content);
            if ($format === 'wireguard') {


                $filename = 'wg_' . ($invoice['id_invoice'] ?? 'config') . '.conf';
                $output[] = ['type' => 'file', 'value' => $content, 'filename' => $filename];
            } else {


                $output[] = ['type' => 'config', 'value' => $content];
            }
        }


        $expirationDate = 'نامحدود';
        $sellTs = $this->parseInvoiceTime((string)($invoice['time_sell'] ?? ''));
        $days = (int)($invoice['Service_time'] ?? 0);
        if ($sellTs !== null && $days > 0) {
            $expireTs = $sellTs + ($days * 86400);
            $expirationDate = jdate('Y/m/d', $expireTs);
        }


        $invoiceStatus = (string)($invoice['Status'] ?? 'active');
        $stockStatus   = (string)($stock['status']   ?? 'delivered');
        if ($stockStatus === 'disabled' || in_array($invoiceStatus, ['end_of_time', 'end_of_volume'], true)) {
            $statusLabel = 'expired';
        } else {
            $statusLabel = 'active';
        }


        $disabled = [
            'renew', 'extra_time', 'extra_volume',
            'toggle_status', 'transfer', 'change_location',
            'refund', 'changelink', 'update_info', 'note',
        ];


        $hasLink   = $subLink !== '';
        $hasConfig = ($content !== '' && $content !== $subLink);
        if (!$hasLink)   $disabled[] = 'subscription';
        if (!$hasConfig) $disabled[] = 'config';


        $rowDis = select('shopSetting', '*', 'Namevalue', 'statusdisorder', 'select');
        if (is_array($rowDis) && (string)($rowDis['value'] ?? '') === 'offdisorder') {
            $disabled[] = 'report_problem';
        }

        return [
            'status'                   => $statusLabel,
            'username'                 => (string)$invoice['username'],
            'product_name'             => (string)$invoice['name_product'],
            'is_test'                  => false,
            'is_stock'                 => true,
            'invoice_status'           => $invoiceStatus,
            'panel_type'               => 'stock',


            'total_traffic_gb'         => null,
            'used_traffic_gb'          => null,
            'remaining_traffic_gb'     => null,
            'expiration_time'          => $expirationDate,
            'last_subscription_update' => null,
            'online_at'                => null,
            'service_output'           => $output,
            'subscription_url'         => $subLink,
            'disabled_actions'         => array_values(array_unique($disabled)),
            'note'                     => (string)($invoice['note'] ?? ''),
        ];
    }


    private function detectStockFormat(string $content): string
    {
        $content = trim($content);
        if ($content === '') return 'text';
        if (preg_match('/^https?:\/\//i', $content)) return 'subscription';
        if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic|wireguard):\/\//i', $content)) return 'single';
        if (stripos($content, '[Interface]') !== false || stripos($content, 'PrivateKey') !== false) return 'wireguard';
        return 'text';
    }


    private function parseInvoiceTime(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') return null;


        $raw = strtr($raw, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);


        if (preg_match('#^(\d{4})[/\-](\d{1,2})[/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?#', $raw, $m)) {
            $jy = (int)$m[1]; $jm = (int)$m[2]; $jd = (int)$m[3];
            $h = isset($m[4]) ? (int)$m[4] : 0;
            $mi = isset($m[5]) ? (int)$m[5] : 0;
            $s = isset($m[6]) ? (int)$m[6] : 0;

            if (function_exists('jalali_to_gregorian')) {
                $jy = $jy < 1700 ? $jy : null;
                if ($jy !== null) {
                    [$gy, $gm, $gd] = jalali_to_gregorian((int)$m[1], $jm, $jd);
                    $ts = mktime($h, $mi, $s, $gm, $gd, $gy);
                    if ($ts !== false) return $ts;
                }
            }
        }


        $ts = strtotime(str_replace('/', '-', $raw));
        return $ts === false ? null : $ts;
    }


    private function computeDisabledActions(array $panel, array $invoice, array $remote, array $configList): array
    {
        $disabled = [];

        $shopValue = static function (string $key, string $fallback = ''): string {
            $row = select('shopSetting', '*', 'Namevalue', $key, 'select');
            if (!is_array($row)) return $fallback;
            $v = $row['value'] ?? '';
            return is_string($v) ? $v : $fallback;
        };

        $statusTimeExtra        = $shopValue('statustimeextra',     'offtimeextraa');
        $statusExtraVolume      = $shopValue('statusextra',         'offextra');
        $statusDisorder         = $shopValue('statusdisorder',      'offdisorder');
        $statusChangeService    = $shopValue('statuschangeservice', 'offstatus');
        $statusShowConfig       = $shopValue('configshow',          'offconfig');
        $statusRemoveService    = $shopValue('backserviecstatus',   'off');
        $statusNameCustom       = (string)($this->setting['statusnamecustom'] ?? 'offnamecustom');

        $panelType   = (string)($panel['type'] ?? '');
        $panelExtend = (string)($panel['status_extend'] ?? 'on_extend');
        $panelChloc  = (string)($panel['changeloc'] ?? 'onchangeloc');

        $panelCount = (int) FaoximaDb::fetchScalar(
            "SELECT COUNT(*) FROM marzban_panel WHERE status = 'active'"
        );

        if ($statusTimeExtra === 'offtimeextraa')    $disabled[] = 'extra_time';
        if ($statusExtraVolume === 'offextra')       $disabled[] = 'extra_volume';
        if ($statusDisorder === 'offdisorder')       $disabled[] = 'report_problem';
        if ($statusChangeService === 'offstatus')    $disabled[] = 'toggle_status';
        if ($statusShowConfig === 'offconfig')       $disabled[] = 'config';
        if ($statusRemoveService === 'off')          $disabled[] = 'refund';
        if ($statusNameCustom === 'offnamecustom')   $disabled[] = 'note';

        if ($panelExtend === 'off_extend') {
            $disabled[] = 'renew';
            $disabled[] = 'extra_time';
            $disabled[] = 'extra_volume';
        }

        if ($panelType === 'mikrotik' || $panelType === 'ibsng') {
            $disabled[] = 'subscription';
            $disabled[] = 'config';
            $disabled[] = 'renew';
            $disabled[] = 'toggle_status';
            $disabled[] = 'change_location';
            $disabled[] = 'changelink';
            $disabled[] = 'extra_volume';
            $disabled[] = 'extra_time';
        }
        if ($panelType === 'eylanpanel') {
            $disabled[] = 'config';
            $disabled[] = 'changelink';
        }
        if ($panelType === 'WGDashboard') {
            $disabled[] = 'config';
            $disabled[] = 'toggle_status';
            $disabled[] = 'change_location';
            $disabled[] = 'changelink';
        }
        if ($panelType === 'hiddify') {
            $disabled[] = 'changelink';
            $disabled[] = 'toggle_status';
            $disabled[] = 'config';
        }

        if (($invoice['name_product'] ?? '') === 'سرویس تست') {
            $disabled[] = 'transfer';
            $disabled[] = 'extra_time';
            $disabled[] = 'refund';
        }

        if ((string)($invoice['Volume'] ?? '') === '0' || (int)($invoice['Volume'] ?? 0) === 0) {
            $disabled[] = 'extra_volume';
            $disabled[] = 'extra_time';
        }
        if ((string)($invoice['Service_time'] ?? '') === '0' || (int)($invoice['Service_time'] ?? 0) === 0) {
            $disabled[] = 'extra_time';
        }

        if ($panelChloc === 'offchangeloc' || $panelCount === 1) {
            $disabled[] = 'change_location';
        }

        $hasInlineConfig = false;
        $hasInlineSub = false;
        foreach ($configList as $entry) {
            $t = strtolower((string)($entry['type'] ?? ''));
            if (in_array($t, ['config', 'file', 'password'], true)) {
                $hasInlineConfig = true;
            }
            if ($t === 'link') {
                $hasInlineSub = true;
            }
        }


        if (!$hasInlineConfig) {
            $shopAllowsConfig = ($statusShowConfig !== 'offconfig');
            $hasFetchableSub  = $hasInlineSub
                || in_array($panelType, ['marzban', 'marzneshin', 'alireza_single', 'x-ui_single'], true);

            if (!($shopAllowsConfig && $hasFetchableSub)) {
                $disabled[] = 'config';
            }
        }
        if (!$hasInlineSub) {
            $disabled[] = 'subscription';
        }

        return array_values(array_unique($disabled));
    }
}

