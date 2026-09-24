<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/service_output.php';

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
            FaoximaResponse::fail(200, 'اطلاعات سرویس در حال حاضر از پنل در دسترس نیست؛ لطفاً چند لحظه دیگر دوباره تلاش کنید.');
        }

        FaoximaResponse::ok($payload);
    }


    public function lookupInvoice(string $username): ?array
    {
        return FaoximaDb::fetchOne(
            "SELECT * FROM invoice
              WHERE id_user = :user_id
                AND (Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume'
                     OR Status = 'sendedwarn' OR Status = 'send_on_hold'
                     OR Status = 'disabled' OR Status = 'disablebyadmin')
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

        if (($panel['national_net_status'] ?? '') === 'on_national_net') {
            FaoximaResponse::fail(200, '🌐 وضعیت نت ملی این پنل روشن است؛ این سرویس فعلاً نمایش داده نمی‌شود. وقتی نت ملی خاموش شود، سرویس‌های قبلی دوباره در دسترس قرار می‌گیرند.');
        }

        $rxPanelCacheKey = 'faoxima:panelcache:' . $invoice['Service_location'] . ':' . $invoice['username'];
        $remote = null;
        if (function_exists('rx_redis_is_active') && rx_redis_is_active()) {
            $rxPanelCached = rx_redis_get($rxPanelCacheKey);
            if ($rxPanelCached !== null) {
                $rxPanelDecoded = json_decode($rxPanelCached, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $remote = $rxPanelDecoded;
                }
            }
        }

        if ($remote === null) {
            $managePanel = new ManagePanel();
            $remote = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);

            if (is_array($remote) && function_exists('rx_redis_is_active') && rx_redis_is_active()) {
                $rxPanelEncoded = json_encode($remote, JSON_UNESCAPED_UNICODE);
                if ($rxPanelEncoded !== false) {
                    rx_redis_set($rxPanelCacheKey, $rxPanelEncoded, 20);
                }
            }
        }

        if (!is_array($remote) || !array_key_exists('data_limit', $remote) || !array_key_exists('used_traffic', $remote)) {
            FaoximaLogger::userFacing('Panel returned incomplete user data', [
                'user_id'  => $this->user['id'],
                'panel'    => $invoice['Service_location'],
                'username' => $invoice['username'],
                'remote'   => $remote,
            ]);
            return null;
        }

        $remoteStatus = strtolower((string)($remote['status'] ?? ''));
        $expireTs = isset($remote['expire']) && is_numeric($remote['expire']) ? (int)$remote['expire'] : 0;
        $dataLimitBytes = is_numeric($remote['data_limit'] ?? null) ? (float)$remote['data_limit'] : 0.0;
        $usedBytes      = is_numeric($remote['used_traffic'] ?? null) ? (float)$remote['used_traffic'] : 0.0;

        if ($expireTs > 0 && $expireTs <= time()) {
            $remoteStatus = 'expired';
            $remote['status'] = 'expired';
        } elseif ($dataLimitBytes > 0.0 && $usedBytes >= $dataLimitBytes) {
            $remoteStatus = 'limited';
            $remote['status'] = 'limited';
        }

        $dbStatus = null;
        if ($remoteStatus === 'expired') {
            $dbStatus = 'end_of_time';
        } elseif ($remoteStatus === 'limited') {
            $dbStatus = 'end_of_volume';
        } elseif ($remoteStatus === 'on_hold') {
            $dbStatus = 'send_on_hold';
        } elseif ($remoteStatus === 'disabled') {
            $dbStatus = ($invoice['Status'] ?? '') === 'disablebyadmin' ? 'disablebyadmin' : 'disabled';
        } elseif ($remoteStatus === 'active') {
            $dbStatus = 'active';
        }

        if ($dbStatus !== null && $dbStatus !== ($invoice['Status'] ?? '')) {
            update('invoice', 'Status', $dbStatus, 'id_invoice', $invoice['id_invoice']);
            $invoice['Status'] = $dbStatus;
        }

        try {
            update('invoice', 'user_info', json_encode($remote, JSON_UNESCAPED_UNICODE), 'id_invoice', $invoice['id_invoice']);
        } catch (Throwable $e) {
        }

        $dataLimitBytes = is_numeric($remote['data_limit']) ? (float)$remote['data_limit'] : 0.0;
        $usedBytes      = is_numeric($remote['used_traffic']) ? (float)$remote['used_traffic'] : 0.0;
        $remainingBytes = max($dataLimitBytes - $usedBytes, 0);

        $totalGb     = $dataLimitBytes / pow(1024, 3);
        $usedGb      = $usedBytes / pow(1024, 3);
        $remainingGb = $remainingBytes / pow(1024, 3);

        $config = [];
        $type = $panel['type'] ?? '';
        $filePrefix = trim((string)($panel['namecustom'] ?? ''));
        if (strtolower($filePrefix) === 'none') {
            $filePrefix = '';
        }
        if (in_array($type, ['marzban', 'x-ui_single', 'eylanpanel', 'pasarguard'], true)) {
            if (($panel['sublink'] ?? '') === 'onsublink' && !empty($remote['subscription_url'])) {
                $config[] = [
                    'type'  => 'link',
                    'value' => $remote['subscription_url'],
                ];
            }
            if (($panel['config'] ?? '') === 'onconfig') {
                $xuiSingleConfigFile = $type === 'x-ui_single' ? ($remote['single_config_file'] ?? null) : null;
                $configLinks = is_array($remote['links'] ?? null) ? $remote['links'] : [];
                if (is_array($xuiSingleConfigFile) && !empty($configLinks)) {
                    $configLinks = array_slice($configLinks, 1);
                    $config[] = [
                        'type'     => 'file',
                        'value'    => 'data:application/octet-stream;base64,' . base64_encode((string)($xuiSingleConfigFile['value'] ?? '')),
                        'filename' => (string)($xuiSingleConfigFile['filename'] ?? 'wireguard.conf'),
                    ];
                }
                if (!empty($configLinks)) {
                    $config[] = [
                        'type'  => 'config',
                        'value' => $configLinks,
                    ];
                }
            }
        } elseif ($type === 'WGDashboard') {
            $config[] = [
                'type'     => 'file',
                'value'    => $remote['subscription_url'] ?? '',
                'filename' => ($panel['inboundid'] ?? 'cfg') . '-' . $invoice['id_user'] . '-' . $invoice['id_invoice'] . '.conf',
            ];
        } elseif ($type === 'guard') {
            $guardSubUrl = $remote['subscription_url'] ?? '';
            if ($guardSubUrl === '' && !empty($remote['links']) && is_array($remote['links'])) {
                $guardSubUrl = (string)($remote['links'][0] ?? '');
            }
            if ($guardSubUrl !== '') {
                $config[] = [
                    'type'  => 'link',
                    'value' => $guardSubUrl,
                ];
            }
        } elseif ($type === 'remnawave' || $type === 'rebecca') {
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
        } elseif ($type === 'Manualsale') {
            $manualItemsOut = is_array($remote['manual_items'] ?? null) ? $remote['manual_items'] : [];
            $config = array_merge($config, faoxima_build_service_output([
                'panel_type' => 'Manualsale',
                'content'    => (string)($remote['subscription_url'] ?? ''),
                'sub_link'   => (string)($remote['manual_sub_link'] ?? $remote['sub_link'] ?? ''),
                'file_ext'   => (string)($remote['file_ext'] ?? ''),
                'username'   => (string)($invoice['username'] ?? 'config'),
                'items'      => $manualItemsOut,
            ]));
        }

        foreach ($config as &$configEntry) {
            if (($configEntry['type'] ?? '') !== 'file') {
                continue;
            }
            $configFilename = (string)($configEntry['filename'] ?? 'config.conf');
            $isWireGuardFile = strtolower((string)pathinfo($configFilename, PATHINFO_EXTENSION)) === 'conf';
            if (function_exists('normalizeConfigFilename')) {
                $configEntry['filename'] = normalizeConfigFilename($configFilename, $filePrefix, $isWireGuardFile);
            } else {
                $configEntry['filename'] = str_replace('_', '-', $configFilename);
            }
        }
        unset($configEntry);

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

        $allowedUsers = null;
        $ipSummary = null;
        $symbolicLimitLabel = function_exists('faoxima_symbolic_limit_label') ? faoxima_symbolic_limit_label($invoice) : null;
        if ($symbolicLimitLabel !== null) {
            $allowedUsers = $symbolicLimitLabel;
        } elseif ($type === 'x-ui_single' && ($panel['ip_limit_guard'] ?? '') === 'onipguard') {
            $ipLimitConfigured = (int)($invoice['ip_limit'] ?? 0);
            $allowedUsers = $ipLimitConfigured > 0 ? "{$ipLimitConfigured} کاربر" : 'نامحدود';

            if (function_exists('xui_panel_uses_token') && xui_panel_uses_token($panel) && function_exists('xui_client_ip_summary')) {
                $xuiIpSummary = xui_client_ip_summary($panel, $remote['username'] ?? $invoice['username']);
                if (!empty($xuiIpSummary['status']) && function_exists('faoxima_cap_ip_summary')) {
                    $xuiIpSummary = faoxima_cap_ip_summary($xuiIpSummary, $ipLimitConfigured);
                }
                if (!empty($xuiIpSummary['status'])) {
                    $ipSummary = [
                        'count' => (int)($xuiIpSummary['count'] ?? 0),
                        'ips'   => array_values($xuiIpSummary['raw'] ?? []),
                    ];
                }
            }
        } elseif ($type === 'rebecca' && ($panel['ip_limit_guard'] ?? '') === 'onipguard') {
            $ipLimitConfigured = (int)($invoice['ip_limit'] ?? 0);
            $allowedUsers = $ipLimitConfigured > 0 ? "{$ipLimitConfigured} کاربر" : 'نامحدود';

            $rebeccaInsightsAllowed = !function_exists('rebeccaAccessInsightsPermissionStatus')
                || (rebeccaAccessInsightsPermissionStatus($panel['name_panel'])['status'] ?? false) === true;
            if ($rebeccaInsightsAllowed && function_exists('rebeccaClientIpSummary')) {
                $rebeccaIpSummary = rebeccaClientIpSummary($panel['name_panel'], $remote['username'] ?? $invoice['username']);
                if (!empty($rebeccaIpSummary['status']) && function_exists('faoxima_cap_ip_summary')) {
                    $rebeccaIpSummary = faoxima_cap_ip_summary($rebeccaIpSummary, $ipLimitConfigured);
                }
                if (!empty($rebeccaIpSummary['status'])) {
                    $ipSummary = [
                        'count' => (int)($rebeccaIpSummary['count'] ?? 0),
                        'ips'   => array_values($rebeccaIpSummary['raw'] ?? []),
                    ];
                }
            }
        }

        $hwidInfo = null;
        if ($type === 'pasarguard') {
            $hwidLimitConfigured = (int)($remote['hwid_limit'] ?? $invoice['hwid_limit'] ?? 0);
            if ($hwidLimitConfigured > 0 && function_exists('pasarguardGetUserHwids')) {
                $hwidResponse = pasarguardGetUserHwids($remote['username'] ?? $invoice['username'], $panel['name_panel']);
                if (empty($hwidResponse['error']) && !empty($hwidResponse['body'])) {
                    $hwidData = json_decode($hwidResponse['body'], true);
                    $hwidList = is_array($hwidData['hwids'] ?? null) ? $hwidData['hwids'] : [];
                    $devices = [];
                    foreach ($hwidList as $hwidEntry) {
                        $deviceOs = (string)($hwidEntry['device_os'] ?? '');
                        $deviceModel = (string)($hwidEntry['device_model'] ?? '');
                        $lastUsedRaw = (string)($hwidEntry['last_used_at'] ?? '');
                        $lastSeen = null;
                        if ($lastUsedRaw !== '') {
                            try {
                                $dtHwid = new DateTime($lastUsedRaw, new DateTimeZone('UTC'));
                                $dtHwid->setTimezone(new DateTimeZone('Asia/Tehran'));
                                $lastSeen = jdate('Y/m/d H:i:s', $dtHwid->getTimestamp());
                            } catch (Throwable $e) {
                                FaoximaLogger::info('Failed to parse hwid last_used_at', ['err' => $e->getMessage()]);
                            }
                        }
                        $devices[] = [
                            'device_os'    => $deviceOs !== '' ? $deviceOs : null,
                            'device_model' => $deviceModel !== '' ? $deviceModel : null,
                            'last_seen'    => $lastSeen,
                        ];
                    }
                    $hwidInfo = [
                        'limit'   => $hwidLimitConfigured,
                        'used'    => count($devices),
                        'devices' => $devices,
                    ];
                }
            } elseif ($hwidLimitConfigured > 0) {
                $hwidInfo = [
                    'limit'   => $hwidLimitConfigured,
                    'used'    => 0,
                    'devices' => [],
                ];
            }
        } elseif ($type === 'remnawave') {
            $hwidLimitConfigured = (int)($remote['hwid_limit'] ?? $invoice['hwid_limit'] ?? 0);
            if ($hwidLimitConfigured > 0 && function_exists('remnawave_get_user_hwid_devices')) {
                $hwidResponse = remnawave_get_user_hwid_devices($panel['name_panel'], $remote['username'] ?? $invoice['username']);
                if (($hwidResponse['status'] ?? '') === 'successful') {
                    $hwidList = is_array($hwidResponse['devices'] ?? null) ? $hwidResponse['devices'] : [];
                    $devices = [];
                    foreach ($hwidList as $hwidEntry) {
                        $devicePlatform = (string)($hwidEntry['platform'] ?? '');
                        $deviceModel = (string)($hwidEntry['deviceModel'] ?? '');
                        $lastUsedRaw = (string)($hwidEntry['updatedAt'] ?? '');
                        $lastSeen = null;
                        if ($lastUsedRaw !== '') {
                            try {
                                $dtHwid = new DateTime($lastUsedRaw, new DateTimeZone('UTC'));
                                $dtHwid->setTimezone(new DateTimeZone('Asia/Tehran'));
                                $lastSeen = jdate('Y/m/d H:i:s', $dtHwid->getTimestamp());
                            } catch (Throwable $e) {
                                FaoximaLogger::info('Failed to parse remnawave hwid updatedAt', ['err' => $e->getMessage()]);
                            }
                        }
                        $devices[] = [
                            'device_os'    => $devicePlatform !== '' ? $devicePlatform : null,
                            'device_model' => $deviceModel !== '' ? $deviceModel : null,
                            'last_seen'    => $lastSeen,
                        ];
                    }
                    $hwidInfo = [
                        'limit'   => $hwidLimitConfigured,
                        'used'    => count($devices),
                        'devices' => $devices,
                    ];
                }
            } elseif ($hwidLimitConfigured > 0) {
                $hwidInfo = [
                    'limit'   => $hwidLimitConfigured,
                    'used'    => 0,
                    'devices' => [],
                ];
            }
        } elseif ($type === 'x-ui_single') {
            $hwidLimitConfigured = (int)($remote['hwid_limit'] ?? $invoice['hwid_limit'] ?? 0);
            if ($hwidLimitConfigured > 0 && function_exists('xui_client_hwid_devices')) {
                $hwidResponse = xui_client_hwid_devices($panel, $remote['username'] ?? $invoice['username']);
                if (!empty($hwidResponse['status'])) {
                    $hwidList = is_array($hwidResponse['devices'] ?? null) ? $hwidResponse['devices'] : [];
                    $devices = [];
                    foreach ($hwidList as $hwidEntry) {
                        $deviceOs = (string)($hwidEntry['deviceOs'] ?? '');
                        $deviceModel = (string)($hwidEntry['deviceModel'] ?? '');
                        $lastUsedRaw = (int)($hwidEntry['lastSeen'] ?? 0);
                        $lastSeen = null;
                        if ($lastUsedRaw > 0) {
                            try {
                                $dtHwid = new DateTime('@' . intval($lastUsedRaw / 1000));
                                $dtHwid->setTimezone(new DateTimeZone('Asia/Tehran'));
                                $lastSeen = jdate('Y/m/d H:i:s', $dtHwid->getTimestamp());
                            } catch (Throwable $e) {
                                FaoximaLogger::info('Failed to parse xui hwid lastSeen', ['err' => $e->getMessage()]);
                            }
                        }
                        $devices[] = [
                            'device_os'    => $deviceOs !== '' ? $deviceOs : null,
                            'device_model' => $deviceModel !== '' ? $deviceModel : null,
                            'last_seen'    => $lastSeen,
                        ];
                    }
                    $hwidInfo = [
                        'limit'   => $hwidLimitConfigured,
                        'used'    => count($devices),
                        'devices' => $devices,
                    ];
                }
            } elseif ($hwidLimitConfigured > 0) {
                $hwidInfo = [
                    'limit'   => $hwidLimitConfigured,
                    'used'    => 0,
                    'devices' => [],
                ];
            }
        }

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
            'display_username'         => guardDisplayUsername((string)($remote['username'] ?? $invoice['username']), (string)($invoice['Service_location'] ?? '')),
            'product_name'             => $invoice['name_product'],
            'is_test'                  => $isTest,
            'is_stock'                 => false,
            'invoice_status'           => $invoice['Status'] ?? '',
            'panel_type'               => $type,
            'file_prefix'              => preg_replace('/[^A-Za-z0-9-]/', '', str_replace('_', '-', $filePrefix)),
            'volume_value'             => is_numeric($invoice['Volume'] ?? null) ? (float)$invoice['Volume'] : 0,
            'volume_unit'              => function_exists('rxInvoiceVolumeUnit') ? rxInvoiceVolumeUnit($invoice) : (($isTest) ? 'MB' : 'GB'),
            'total_traffic_bytes'      => $dataLimitBytes,
            'used_traffic_bytes'       => $usedBytes,
            'remaining_traffic_bytes'  => $remainingBytes,
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
            'allowed_users'            => $allowedUsers,
            'ip_summary'               => $ipSummary,
            'hwid_info'                => $hwidInfo,
            'has_queued_renewal'       => $this->hasQueuedRenewal($invoice['username']),
        ];
    }


    private function hasQueuedRenewal(string $username): bool
    {
        $row = FaoximaDb::fetchOne(
            "SELECT id FROM queued_renewal WHERE username = :username AND status = 'pending' LIMIT 1",
            [':username' => $username]
        );
        return $row !== null;
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


                $filename = 'wg-' . ($invoice['id_invoice'] ?? 'config') . '.conf';
                $output[] = ['type' => 'file', 'value' => $content, 'filename' => $filename];
            } else {


                $output[] = ['type' => 'config', 'value' => $content];
            }
        }


        $expirationDate = 'نامحدود';
        $sellTs = $this->parseInvoiceTime((string)($invoice['time_sell'] ?? ''));
        $serviceTime = (int)($invoice['Service_time'] ?? 0);
        if ($sellTs !== null && $serviceTime > 0) {
            $isTestStock = ($invoice['name_product'] ?? '') === 'سرویس تست';
            $expireTs = $sellTs + ($isTestStock ? ($serviceTime * 3600) : ($serviceTime * 86400));
            $expirationDate = jdate('Y/m/d', $expireTs);
        }


        $invoiceStatus = (string)($invoice['Status'] ?? 'active');
        $stockStatus   = (string)($stock['status']   ?? 'delivered');
        $stockIsExpired = isset($expireTs) && $expireTs > 0 && $expireTs <= time();
        if ($stockStatus === 'disabled' || in_array($invoiceStatus, ['end_of_time', 'end_of_volume'], true) || $stockIsExpired) {
            $statusLabel = 'expired';
            if ($stockIsExpired && $invoiceStatus !== 'end_of_time') {
                update('invoice', 'Status', 'end_of_time', 'id_invoice', $invoice['id_invoice']);
                $invoiceStatus = 'end_of_time';
            }
        } else {
            $statusLabel = 'active';
        }


        $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (!is_array($panel)) {
            $panel = [];
        }

        $featureOn = function ($key) use ($panel) {
            return function_exists('panel_feature_enabled') ? panel_feature_enabled($panel, $key) : true;
        };

        $disabled = [
            'extra_time', 'extra_volume',
            'toggle_status', 'transfer', 'change_location',
            'changelink', 'update_info',
        ];

        if ((string)($panel['status_extend'] ?? 'on_extend') === 'off_extend') {
            $disabled[] = 'renew';
        }
        if (!$featureOn('refund'))    $disabled[] = 'refund';
        if (!$featureOn('disorder'))  $disabled[] = 'report_problem';
        if (!$featureOn('configbtn')) $disabled[] = 'config';
        if ((string)($this->setting['statusnamecustom'] ?? 'offnamecustom') === 'offnamecustom') {
            $disabled[] = 'note';
        }

        $hasLink   = $subLink !== '';
        $hasConfig = ($content !== '' && $content !== $subLink);
        if (!$hasLink)   $disabled[] = 'subscription';
        if (!$hasConfig) $disabled[] = 'config';

        return [
            'status'                   => $statusLabel,
            'username'                 => (string)$invoice['username'],
            'product_name'             => (string)$invoice['name_product'],
            'is_test'                  => false,
            'is_stock'                 => true,
            'invoice_status'           => $invoiceStatus,
            'panel_type'               => 'stock',


            'total_traffic_gb'         => null,
            'total_traffic_bytes'      => null,
            'used_traffic_gb'          => null,
            'used_traffic_bytes'       => null,
            'remaining_traffic_gb'     => null,
            'remaining_traffic_bytes'  => null,
            'expiration_time'          => $expirationDate,
            'last_subscription_update' => null,
            'online_at'                => null,
            'service_output'           => $output,
            'subscription_url'         => $subLink,
            'disabled_actions'         => array_values(array_unique($disabled)),
            'note'                     => (string)($invoice['note'] ?? ''),
            'has_queued_renewal'       => $this->hasQueuedRenewal((string)$invoice['username']),
        ];
    }


    private function detectStockFormat(string $content): string
    {
        $content = trim($content);
        if ($content === '') return 'text';
        if (preg_match('/^https?:\/\//i', $content)) return 'subscription';
        if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic|wireguard|wg|vpn|awg|amneziawg|tg):\/\//i', $content)) return 'single';
        if (stripos($content, '[Interface]') !== false || stripos($content, 'PrivateKey') !== false) return 'wireguard';
        return 'text';
    }



    private function parseInvoiceTime(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        if (is_numeric($raw)) return (int)$raw;

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

        $statusTimeExtra        = panel_feature_enabled($panel, 'timeextra')     ? 'ontimeextraa' : 'offtimeextraa';
        $statusExtraVolume      = panel_feature_enabled($panel, 'extravolume')   ? 'onextra'      : 'offextra';
        $statusDisorder         = panel_feature_enabled($panel, 'disorder')      ? 'ondisorder'   : 'offdisorder';
        $statusChangeService    = panel_feature_enabled($panel, 'changeservice') ? 'onstatus'     : 'offstatus';
        $statusShowConfig       = panel_feature_enabled($panel, 'configbtn')     ? 'onconfig'     : 'offconfig';
        $statusRemoveService    = panel_feature_enabled($panel, 'refund')        ? 'on'           : 'off';
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
        if (($invoice['Status'] ?? '') === 'disablebyadmin') $disabled[] = 'toggle_status';
        if ($statusShowConfig === 'offconfig')       $disabled[] = 'config';
        if ($statusRemoveService === 'off')          $disabled[] = 'refund';
        if ($statusNameCustom === 'offnamecustom')   $disabled[] = 'note';

        if ($panelExtend === 'off_extend') {
            $disabled[] = 'renew';
            $disabled[] = 'extra_time';
            $disabled[] = 'extra_volume';
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
        if ($panelType === 'Manualsale') {
            $disabled[] = 'extra_time';
            $disabled[] = 'extra_volume';
            $disabled[] = 'toggle_status';
            $disabled[] = 'transfer';
            $disabled[] = 'change_location';
            $disabled[] = 'changelink';
            $disabled[] = 'update_info';
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
            if (in_array($t, ['config', 'file', 'password', 'text'], true)) {
                $hasInlineConfig = true;
            }
            if ($t === 'link') {
                $hasInlineSub = true;
            }
        }


        if (!$hasInlineConfig) {
            $shopAllowsConfig = ($statusShowConfig !== 'offconfig');
            $hasFetchableSub  = $hasInlineSub
                || in_array($panelType, ['marzban', 'x-ui_single', 'remnawave', 'rebecca'], true);

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
