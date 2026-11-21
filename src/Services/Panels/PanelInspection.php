<?php

namespace Faoxima\Services\Panels;

use Faoxima\Services\Panels\Remnawave\RemnawaveManager;
use PDO;

/**
 * Reads: panel rows, live-connection checks and the user/service data lookup across every provider.
 * Part of ManagePanel; the methods keep their original names, signatures and bodies.
 */
trait PanelInspection
{
    private function loadPanel(string $key, string $column = 'name_panel'): ?array
    {
        $cacheKey = $column . '|' . $key;
        if (array_key_exists($cacheKey, self::$panelRowCache)) {
            return self::$panelRowCache[$cacheKey];
        }
        $row = \Faoxima\Core\Database\Db::select('marzban_panel', '*', $column, $key, 'select');
        $value = is_array($row) ? $row : null;
        self::$panelRowCache[$cacheKey] = $value;
        return $value;
    }



    public function isPasarGuardDialect(?array $panel): bool
    {
        if (!is_array($panel) || empty($panel['type'])) {
            return false;
        }
        return ($panel['type'] === 'pasarguard')
            || ($panel['type'] === 'marzban' && (string)($panel['version_panel'] ?? '0') === '1');
    }


    public function RequiresLiveConnectionCheck(?array $panel): bool
    {
        if (!is_array($panel) || ($panel['type'] ?? '') === 'Manualsale') {
            return false;
        }
        if (($panel['national_net_status'] ?? '') === 'on_national_net') {
            return false;
        }
        return in_array((string) ($panel['type'] ?? ''), ['marzban', 'pasarguard', 'x-ui_single', 'guard', 'remnawave', 'rebecca'], true);
    }


    public function HasLiveConnectionHistory(?array $panel, array $user): bool
    {
        if (!$this->RequiresLiveConnectionCheck($panel)) {
            return true;
        }
        if (($user['is_online'] ?? null) === true || (float) ($user['used_traffic'] ?? 0) > 0) {
            return true;
        }
        $onlineAt = $user['online_at'] ?? null;
        if (is_numeric($onlineAt)) {
            return (float) $onlineAt > 0;
        }
        $onlineAt = strtolower(trim((string) $onlineAt));
        if ($onlineAt === '' || in_array($onlineAt, ['offline', 'null', 'never', 'none', '0', '-'], true)) {
            return false;
        }
        if ($onlineAt === 'online') {
            return true;
        }
        $onlineTs = strtotime($onlineAt);
        return $onlineTs !== false && $onlineTs > 0;
    }


    function DataUser($name_panel, $username, $client_type = 'links')
    {
        $Output = array();
        global $pdo, $domainhosts;
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if (!$Get_Data_Panel || !is_array($Get_Data_Panel)) {
            error_log("[REMNAWAVE-DATAUSER-ENTER] Username passed: " . ($username ?? 'NULL') . " | Panel Name: " . ($name_panel ?? 'NULL') . " | RESULT: panel not found by loadPanel");
            return array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        if (isset($Get_Data_Panel['subvip']) && $Get_Data_Panel['subvip'] == "onsubvip") {
            $inoice = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $username, "select");
        } else {
            $inoice = false;
        }
        if ($Get_Data_Panel['type'] == "marzban") {
            $UsernameData = \Faoxima\Services\Panels\Drivers\Marzban::getuser($username, $Get_Data_Panel['name_panel']);
            if (!empty($UsernameData['error'])) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            } elseif (!empty($UsernameData['status']) && $UsernameData['status'] == 500) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['status']
                );
            } else {
                $UsernameData = json_decode($UsernameData['body'], true);
                if (!empty($UsernameData['detail'])) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $UsernameData['detail']
                    );
                }
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $UsernameData['subscription_url'])) {
                    $UsernameData['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($UsernameData['subscription_url'], "/");
                }
                if ((string)($Get_Data_Panel['version_panel'] ?? '0') === '1') {
                    $UsernameData['expire'] = empty($UsernameData['expire']) ? 0 : strtotime((string) $UsernameData['expire']);
                    $UsernameData['links'] = base64_decode(\Faoxima\Services\Delivery\ServiceDelivery::outputlunk($UsernameData['subscription_url']));
                    $UsernameData['links'] = explode("\n", $UsernameData['links']);
                    $sublist_update = \Faoxima\Services\Panels\Drivers\Marzban::get_list_update($name_panel, $username);
                    if (!empty($sublist_update['error'])) {
                        return array(
                            'status' => 'Unsuccessful',
                            'msg' => $sublist_update['error']
                        );
                    } elseif (!empty($sublist_update['status']) && $sublist_update['status'] == 500) {
                        return array(
                            'status' => 'Unsuccessful',
                            'msg' => $sublist_update['status']
                        );
                    }
                    $sublist_update_body = json_decode($sublist_update['body'], true);
                    if (!empty($sublist_update_body['updates']) && is_array($sublist_update_body['updates'])) {
                        $first_update = $sublist_update_body['updates'][0];
                        $UsernameData['sub_updated_at'] = isset($first_update['created_at']) ? $first_update['created_at'] : null;
                        $UsernameData['sub_last_user_agent'] = isset($first_update['user_agent']) ? $first_update['user_agent'] : null;
                    } else {
                        $UsernameData['sub_updated_at'] = isset($UsernameData['sub_updated_at']) ? $UsernameData['sub_updated_at'] : null;
                        $UsernameData['sub_last_user_agent'] = isset($UsernameData['sub_last_user_agent']) ? $UsernameData['sub_last_user_agent'] : null;
                    }
                } else {
                    $UsernameData['expire'] = $UsernameData['expire'];
                }
                if ($inoice != false) {
                    $UsernameData['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                if ((string)($Get_Data_Panel['version_panel'] ?? '0') === '1') {
                    $UsernameData['proxies'] = isset($UsernameData['proxy_settings']) ? $UsernameData['proxy_settings'] : null;
                }
                $Output = array(
                    'status' => $UsernameData['status'],
                    'username' => $UsernameData['username'],
                    'data_limit' => $UsernameData['data_limit'],
                    'expire' => $UsernameData['expire'],
                    'online_at' => $UsernameData['online_at'],
                    'used_traffic' => $UsernameData['used_traffic'],
                    'links' => $UsernameData['links'],
                    'subscription_url' => $UsernameData['subscription_url'],
                    'sub_updated_at' => $UsernameData['sub_updated_at'],
                    'sub_last_user_agent' => $UsernameData['sub_last_user_agent'],
                    'uuid' => $UsernameData['proxies'],
                    'data_limit_reset' => $UsernameData['data_limit_reset_strategy'],
                    'group_ids' => $UsernameData['group_ids'] ?? null,
                    'inbounds' => $UsernameData['inbounds'] ?? null,
                    'on_hold_expire_duration' => $UsernameData['on_hold_expire_duration'] ?? null
                );
            }
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $pasarguardUserRaw = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardGetUser($username, $Get_Data_Panel['name_panel']);
            $UsernameData = $pasarguardUserRaw;
            if (!empty($UsernameData['error'])) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            } elseif (!empty($UsernameData['status']) && $UsernameData['status'] == 500) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['status']
                );
            } else {
                $UsernameData = json_decode($UsernameData['body'], true);
                if (!empty($UsernameData['detail'])) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $UsernameData['detail']
                    );
                }
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $UsernameData['subscription_url'])) {
                    $UsernameData['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($UsernameData['subscription_url'], "/");
                }
                if ($inoice != false) {
                    $UsernameData['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                $pasarguardExpireTs = 0;
                if (!empty($UsernameData['expire'])) {
                    $pasarguardExpireTs = is_numeric($UsernameData['expire']) ? (int) $UsernameData['expire'] : (int) strtotime((string) $UsernameData['expire']);
                }
                $pasarguardLinks = [];
                $pasarguardClientType = 'links';
                $pasarguardLinksResponse = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardGetSubscriptionLinks($username, $Get_Data_Panel['name_panel'], $client_type, $pasarguardUserRaw);
                if (empty($pasarguardLinksResponse['error']) && !empty($pasarguardLinksResponse['links'])) {
                    $pasarguardLinks = $pasarguardLinksResponse['links'];
                    $pasarguardClientType = (string) ($pasarguardLinksResponse['client_type'] ?? 'links');
                }
                $pasarguardLinksDiagnostic = array(
                    'panel_type' => 'pasarguard',
                    'outcome' => !empty($pasarguardLinksResponse['error'])
                        ? (string) ($pasarguardLinksResponse['outcome'] ?? 'failed')
                        : (empty($pasarguardLinks) ? 'empty' : 'ok'),
                    'stage' => $pasarguardLinksResponse['stage'] ?? null,
                    'reason' => $pasarguardLinksResponse['reason'] ?? null,
                    'http_status' => $pasarguardLinksResponse['http_status'] ?? null,
                    'error' => !empty($pasarguardLinksResponse['error']) ? (string) $pasarguardLinksResponse['error'] : null,
                );
                $Output = array(
                    'status' => $UsernameData['status'],
                    'username' => $UsernameData['username'],
                    'data_limit' => $UsernameData['data_limit'],
                    'expire' => $pasarguardExpireTs,
                    'online_at' => $UsernameData['online_at'],
                    'used_traffic' => $UsernameData['used_traffic'],
                    'links' => $pasarguardLinks,
                    'subscription_url' => $UsernameData['subscription_url'],
                    'sub_updated_at' => $UsernameData['sub_updated_at'] ?? null,
                    'sub_last_user_agent' => $UsernameData['sub_last_user_agent'] ?? null,
                    'uuid' => $UsernameData['proxies'] ?? null,
                    'data_limit_reset' => $UsernameData['data_limit_reset_strategy'],
                    'hwid_limit' => $UsernameData['hwid_limit'] ?? null,
                    'group_ids' => $UsernameData['group_ids'] ?? null,
                    'client_type' => $pasarguardClientType,
                    'on_hold_expire_duration' => $UsernameData['on_hold_expire_duration'] ?? null,
                    'links_diagnostic' => $pasarguardLinksDiagnostic
                );
            }
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $subscription = \Faoxima\Services\Panels\Drivers\Guard::guardGetSubscription($Get_Data_Panel['name_panel'], $username);
            if ($subscription['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $subscription['msg']
                );
            }
            $subscriptionData = $subscription['data'];
            if (isset($subscriptionData['data']) && is_array($subscriptionData['data'])) {
                $subscriptionData = $subscriptionData['data'];
            }
            if (isset($subscriptionData['subscription']) && is_array($subscriptionData['subscription'])) {
                $subscriptionData = $subscriptionData['subscription'];
            }
            if (is_array($subscriptionData) && isset($subscriptionData[0])) {
                $subscriptionData = $subscriptionData[0];
            }
            if (!is_array($subscriptionData)) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'User not found'
                );
            }
            $guardEnabled = array_key_exists('enabled', $subscriptionData)
                ? filter_var($subscriptionData['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null;
            $guardRawExpire = isset($subscriptionData['limit_expire']) ? intval($subscriptionData['limit_expire']) : 0;
            $guardOnHold = $guardRawExpire < 0
                || (!empty($subscriptionData['on_hold_timeout_days']) && empty($subscriptionData['on_hold_since']));
            if (!empty($subscriptionData['expired']) || !empty($subscriptionData['expired_at'])) {
                $statusGuard = "expired";
            } elseif (!empty($subscriptionData['limited']) || !empty($subscriptionData['limited_at'])) {
                $statusGuard = "limited";
            } elseif ($guardEnabled === false) {
                $statusGuard = "disabled";
            } elseif ($guardOnHold) {
                $statusGuard = "on_hold";
            } elseif (isset($subscriptionData['status']) && is_string($subscriptionData['status']) && $subscriptionData['status'] !== '') {
                $statusGuard = $subscriptionData['status'];
            } else {
                $statusGuard = "active";
            }
            $limitExpire = $guardRawExpire > 0 ? $guardRawExpire : 0;
            $dataLimit = isset($subscriptionData['limit_usage']) ? intval($subscriptionData['limit_usage']) : 0;
            $subscriptionUrl = $subscriptionData['subscription_url'] ?? ($subscriptionData['subscription_link'] ?? ($subscriptionData['subscription'] ?? ''));
            $serviceIds = isset($subscriptionData['service_ids']) && is_array($subscriptionData['service_ids'])
                ? $subscriptionData['service_ids']
                : (isset($subscriptionData['services']) && is_array($subscriptionData['services']) ? $subscriptionData['services'] : array());
            $usage = 0;
            if (isset($subscriptionData['total_usage'])) {
                $usage = intval($subscriptionData['total_usage']);
            } elseif (isset($subscriptionData['usage'])) {
                $usage = intval($subscriptionData['usage']);
            } elseif (isset($subscriptionData['used_traffic'])) {
                $usage = intval($subscriptionData['used_traffic']);
            }
            $usageResponse = \Faoxima\Services\Panels\Drivers\Guard::guardGetSubscriptionUsages($Get_Data_Panel['name_panel'], $username);
            if ($usageResponse && $usageResponse['status'] !== false && isset($usageResponse['data'])) {
                $usageData = $usageResponse['data'];
                if (isset($usageData['total_usage'])) {
                    $usage = intval($usageData['total_usage']);
                } elseif (isset($usageData['hourly_usage']) && is_array($usageData['hourly_usage'])) {
                    $usage = 0;
                    foreach ($usageData['hourly_usage'] as $hourlyPoint) {
                        $usage += intval($hourlyPoint['usage'] ?? 0);
                    }
                } else {
                    $usageList = array();
                    if (isset($usageData['usages']) && is_array($usageData['usages'])) {
                        $usageList = $usageData['usages'];
                    } elseif (is_array($usageData) && isset($usageData[0])) {
                        $usageList = $usageData;
                    }
                    foreach ($usageList as $usageItem) {
                        $usage += intval($usageItem['total_usage'] ?? $usageItem['usage'] ?? (($usageItem['download'] ?? 0) + ($usageItem['upload'] ?? 0)));
                    }
                }
            }
            $onlineAt = $subscriptionData['online_at'] ?? ($subscriptionData['last_online_at'] ?? null);
            $isOnline = null;
            if (array_key_exists('is_online', $subscriptionData)) {
                $isOnline = $subscriptionData['is_online'];
            } elseif (array_key_exists('online', $subscriptionData)) {
                $isOnline = $subscriptionData['online'];
            }
            if ($isOnline !== null) {
                $isOnline = filter_var($isOnline, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
            $links = array();
            $linksResponse = \Faoxima\Services\Panels\Drivers\Guard::guardGetSubscriptionLinks($Get_Data_Panel['name_panel'], $username);
            if ($linksResponse && $linksResponse['status'] !== false && isset($linksResponse['data']['links']) && is_array($linksResponse['data']['links'])) {
                foreach ($linksResponse['data']['links'] as $linkItem) {
                    if (is_array($linkItem) && !empty($linkItem['link'])) {
                        $links[] = $linkItem['link'];
                    } elseif (is_string($linkItem) && $linkItem !== '') {
                        $links[] = $linkItem;
                    }
                }
            }
            if (empty($links) && !empty($subscriptionUrl)) {
                $links[] = $subscriptionUrl;
            }
            if ($inoice != false) {
                $subscriptionUrl = "https://$domainhosts/sub/" . $inoice['id_invoice'];
            }
            $Output = array(
                'status' => $statusGuard,
                'username' => $subscriptionData['username'] ?? $username,
                'data_limit' => $dataLimit,
                'expire' => $limitExpire,
                'online_at' => $onlineAt,
                'is_online' => $isOnline,
                'used_traffic' => $usage,
                'links' => $links,
                'subscription_url' => $subscriptionUrl,
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
                'uuid' => null,
                'data_limit_reset' => null,
                'service_ids' => $serviceIds
            );
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            $user_data = \Faoxima\Services\Panels\Drivers\XuiSingle::get_clinets($username, $Get_Data_Panel['name_panel']);
            if (!empty($user_data['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $user_data['error']
                );
            } elseif (!empty($user_data['status']) && $user_data['status'] != 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => json_encode($user_data)
                );
            }
            $user_data = json_decode($user_data['body'], true);

            if (!is_array($user_data)) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'object invalid'
                );
            }
            if (empty($user_data['obj'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => "User not found"
                );
            }
            $user_data = $user_data['obj'];
            $expire = $user_data['expiryTime'] / 1000;
            if ($user_data['enable']) {
                $user_data['enable'] = "active";
            } else {
                $user_data['enable'] = "disabled";
            }
            if ((intval($user_data['total'])) != 0) {
                if ((intval($user_data['total']) - ($user_data['up'] + $user_data['down'])) <= 0)
                    $user_data['enable'] = "limited";
            }
            if (intval($user_data['expiryTime']) != 0) {
                if ($expire - time() <= 0)
                    $user_data['enable'] = "expired";
            }
            $xuiOnHoldDuration = null;
            if ($user_data['expiryTime'] < -10000) {
                $user_data['enable'] = "on_hold";
                $xuiOnHoldDuration = (int) round(abs((float) $user_data['expiryTime']) / 1000);
                $expire = 0;
            }
            $subscriptionUrl = rtrim($Get_Data_Panel['linksubx'], '/') . "/{$user_data['subId']}";
            $linksub = $subscriptionUrl;
            $links_user_raw = \Faoxima\Services\Delivery\ServiceDelivery::outputlunk($subscriptionUrl);
            if (!is_string($links_user_raw)) {
                $links_user_raw = '';
            }
            if (\Faoxima\Core\Support\Formatting::isBase64($links_user_raw)) {
                $links_user_raw = base64_decode($links_user_raw);
            }
            $links_user = preg_split('/\R/', trim($links_user_raw));
            if (!is_array($links_user)) {
                $links_user = [];
            }
            $links_user = array_values(array_filter(array_map('trim', $links_user), function ($ln) {
                return $ln !== '';
            }));
            $singleLink = $links_user[0] ?? null;
            $singleConfigFile = null;
            if (!$singleLink || !preg_match('/^(vless|vmess|trojan):\/\//i', $singleLink)) {
                if (is_file(\Faoxima\Services\Panels\Drivers\XuiSingle::xuisingle_cookie_path())) {
                    @unlink(\Faoxima\Services\Panels\Drivers\XuiSingle::xuisingle_cookie_path());
                }
                \Faoxima\Services\Panels\Drivers\XuiSingle::login($Get_Data_Panel['code_panel']);
                $singleConfig = \Faoxima\Services\Panels\Drivers\XuiSingle::get_single_link_smart(
                    $Get_Data_Panel['url_panel'],
                    $Get_Data_Panel['inboundid'],
                    $subscriptionUrl,
                    $username,
                    $Get_Data_Panel['name_panel'],
                    $Get_Data_Panel['code_panel'] ?? null
                );
                if (is_file(\Faoxima\Services\Panels\Drivers\XuiSingle::xuisingle_cookie_path())) {
                    @unlink(\Faoxima\Services\Panels\Drivers\XuiSingle::xuisingle_cookie_path());
                }
                if (!$singleConfig) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => 'Unable to build single link'
                    );
                }
                if ($singleConfig['type'] === 'file') {
                    $singleConfigFile = $singleConfig;
                    $xuiFileScheme = ($singleConfig['protocol'] ?? '') === 'amneziawg' ? 'xuiawgfile' : 'xuifile';
                    $singleLink = $xuiFileScheme . '://' . rawurlencode($singleConfig['filename']);
                    array_unshift($links_user, $singleLink);
                } else {
                    $singleLink = $singleConfig['value'];
                    array_unshift($links_user, $singleLink);
                }
            }
            if ($inoice != false)
                $linksub = "https://$domainhosts/sub/" . $inoice['id_invoice'];
            $user_data['lastOnline'] = $user_data['lastOnline'] == 0 ? "offline" : date('Y-m-d H:i:s', $user_data['lastOnline'] / 1000);
            $Output = array(
                'status' => $user_data['enable'],
                'username' => $user_data['email'],
                'data_limit' => $user_data['total'],
                'expire' => $expire,
                'online_at' => $user_data['lastOnline'],
                'used_traffic' => $user_data['up'] + $user_data['down'],
                'links' => $links_user,
                'subscription_url' => $linksub,
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
                'single_config_file' => $singleConfigFile,
                'on_hold_expire_duration' => $xuiOnHoldDuration,
            );

        } elseif ($Get_Data_Panel['type'] == "Manualsale") {
            $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE username = :username ORDER BY id ASC");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $manualRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $configman = (is_array($manualRows) && !empty($manualRows)) ? $manualRows[0] : false;
            $manualItems = array();
            $manualUnifiedSub = '';
            if (is_array($manualRows)) {
                foreach ($manualRows as $mr) {
                    $manualItems[] = array(
                        'record_id' => (int) ($mr['id'] ?? 0),
                        'content' => (string) ($mr['contentrecord'] ?? ''),
                        'file_ext' => (string) ($mr['file_ext'] ?? ''),
                        'sub_link' => (string) ($mr['sub_link'] ?? ''),
                        'name' => (string) ($mr['namerecord'] ?? ''),
                    );
                    if ($manualUnifiedSub === '' && trim((string) ($mr['sub_link'] ?? '')) !== '') {
                        $manualUnifiedSub = trim((string) $mr['sub_link']);
                    }
                }
            }
            $service = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $username, "select");
            if (!is_array($service)) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'User not found'
                );
            }
            $volumeValue = (float) ($service['Volume'] ?? 0);
            $volumeUnit = method_exists(\Faoxima\Core\Support\Formatting::class, 'rxInvoiceVolumeUnit') ? \Faoxima\Core\Support\Formatting::rxInvoiceVolumeUnit($service) : ((($service['name_product'] ?? '') === 'سرویس تست') ? 'MB' : 'GB');
            $data_limit = $volumeValue == 0 ? null : (method_exists(\Faoxima\Core\Support\Formatting::class, 'rxVolumeToBytes') ? \Faoxima\Core\Support\Formatting::rxVolumeToBytes($volumeValue, $volumeUnit) : $volumeValue * ($volumeUnit === 'MB' ? pow(1024, 2) : pow(1024, 3)));
            $manualSellTs = is_numeric($service['time_sell'] ?? null) ? (int) $service['time_sell'] : strtotime((string) ($service['time_sell'] ?? ''));
            $manualServiceTime = (int) ($service['Service_time'] ?? 0);
            $manualIsTest = (($service['name_product'] ?? '') === 'سرویس تست');
            $manualServiceSeconds = $manualIsTest ? ($manualServiceTime * 3600) : ($manualServiceTime * 86400);
            $manualExpire = ($manualSellTs !== false && $manualServiceTime > 0) ? ($manualSellTs + $manualServiceSeconds) : null;
            $Output = array(
                'status' => $service['Status'],
                'username' => $service['username'],
                'data_limit' => $data_limit,
                'expire' => $manualExpire,
                'online_at' => null,
                'used_traffic' => 0,
                'links' => [],
                'subscription_url' => is_array($configman) ? ($configman['contentrecord'] ?? '') : '',
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
                'file_ext' => is_array($configman) ? ($configman['file_ext'] ?? '') : '',
                'sub_link' => is_array($configman) ? ($configman['sub_link'] ?? '') : '',
                'manual_items' => $manualItems,
                'manual_sub_link' => $manualUnifiedSub,
                'uuid' => null
            );
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $UsernameData = \Faoxima\Services\Panels\Drivers\WgDashboard::get_userwg($username, $Get_Data_Panel['name_panel']);
            if (isset($UsernameData['status']) && $UsernameData['status'] === false && !isset($UsernameData['id'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => isset($UsernameData['msg']) ? $UsernameData['msg'] : ''
                );
            }
            $invoiceinfo = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $username, "select");
            $infoconfig = isset($invoiceinfo['user_info']) ? json_decode($invoiceinfo['user_info'], true) : json_encode(array());
            if (!isset($UsernameData['id'])) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => isset($UsernameData['msg']) ? $UsernameData['msg'] : ''
                );
            } else {
                $jobtime = [];
                $jobvolume = [];
                foreach ($UsernameData['jobs'] as $job) {
                    if ($job['Field'] == "total_data") {
                        $jobvolume = $job;
                    } elseif ($job['Field'] == "date") {
                        $jobtime = $job;
                    }
                }
                if (isset($jobtime['Value'])) {
                    $expire = strtotime($jobtime['Value']);
                } else {
                    $expire = 0;
                }
                $status = "active";
                if (!$UsernameData['configuration']['Status'])
                    $status = "disabled";
                if ($expire != 0 and $expire - time() < 0) {
                    $status = "expired";
                }
                $data_useage = ($UsernameData['total_data'] * pow(1024, 3)) + ($UsernameData['cumu_data'] * pow(1024, 3));
                if (isset($jobvolume['Value']) && ($jobvolume['Value'] * pow(1024, 3)) < $data_useage) {
                    $status = "limited";
                }
                $download_config = \Faoxima\Services\Panels\Drivers\WgDashboard::downloadconfig($Get_Data_Panel['name_panel'], $UsernameData['id']);
                if (isset($download_config['status']) && $download_config['status'] === false) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => isset($download_config['msg']) ? $download_config['msg'] : ''
                    );
                }
                if (!empty($download_config['status']) && $download_config['status'] != 200) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $download_config['status']
                    );
                }
                if (!empty($download_config['error'])) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $download_config['error']
                    );
                }
                $download_config = json_decode($download_config['body'], true)['data'];
                $Output = array(
                    'status' => $status,
                    'username' => $UsernameData['name'],
                    'data_limit' => $jobvolume['Value'] * pow(1024, 3),
                    'expire' => $expire,
                    'online_at' => null,
                    'used_traffic' => $data_useage,
                    'links' => [],
                    'subscription_url' => strval($download_config['file']),
                    'sub_updated_at' => null,
                    'sub_last_user_agent' => null,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            try {
                $localUser = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_find_user($username);
                if (!$localUser) {
                    $Output = array('status' => 'Unsuccessful', 'msg' => 'remnawave user not found in db');
                } else {
                    if (empty($localUser['panel_user_id'])) {
                        error_log("[REMNAWAVE-DATAUSER-LOCAL-NOTFOUND] panel_user_id empty for username: " . $username . " (row id=" . ($localUser['id'] ?? '?') . ")");
                        $Output = array('status' => 'Unsuccessful', 'msg' => 'remnawave user id missing');
                    } else {
                        $mgr = new RemnawaveManager($Get_Data_Panel);
                        $apiRes = $mgr->getUserById($localUser['panel_user_id'], $pdo);
                        if (empty($apiRes['ok']) || !is_array($apiRes['data'])) {
                            error_log("[REMNAWAVE-DATAUSER-API-FAIL] getUserById failed for id " . $localUser['panel_user_id']);
                            $Output = array('status' => 'Unsuccessful', 'msg' => 'remnawave api fetch failed');
                        } else {
                            $d = $apiRes['data'];
                            $expireTs = !empty($d['expireAt']) ? strtotime((string) $d['expireAt']) : 0;
                            $trafficLimit = (int) ($d['trafficLimitBytes'] ?? 0);
                            $rawStatus = strtoupper((string) ($d['status'] ?? 'ACTIVE'));
                            $statusMap = array('ACTIVE' => 'active', 'DISABLED' => 'disabled', 'LIMITED' => 'limited', 'EXPIRED' => 'expired');
                            $accountStatus = $statusMap[$rawStatus] ?? 'active';
                            $subUrl = (string) ($d['subscriptionUrl'] ?? ($localUser['subscription_url'] ?? ''));
                            $rwFreshShort = (string) ($d['shortUuid'] ?? '');
                            if ($rwFreshShort !== '' && $rwFreshShort !== (string) ($localUser['short_uuid'] ?? '')) {
                                try {
                                    $rwSync = $pdo->prepare("UPDATE remnawave_users SET short_uuid = :su, subscription_url = :sub WHERE id = :id");
                                    $rwSync->execute([':su' => $rwFreshShort, ':sub' => $subUrl, ':id' => $localUser['id']]);
                                    $localUser['short_uuid'] = $rwFreshShort;
                                } catch (\Throwable $e) {
                                    error_log("[REMNAWAVE-DATAUSER-SYNC] " . $e->getMessage());
                                }
                            }
                            $rawConfigs = [];
                            if (method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_get_configs')) {
                                $cfgRes = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_get_configs($Get_Data_Panel['name_panel'], $username);
                                if (($cfgRes['status'] ?? '') === 'successful' && !empty($cfgRes['links']) && is_array($cfgRes['links'])) {
                                    $rawConfigs = array_values(array_filter($cfgRes['links'], function ($c) {
                                        return is_string($c) && trim($c) !== '';
                                    }));
                                }
                            }
                            $rwSublinkOn = (($Get_Data_Panel['sublink'] ?? '') === 'onsublink');
                            $linksOut = !empty($rawConfigs) ? $rawConfigs : ($rwSublinkOn ? [$subUrl] : []);
                            $rwTraffic = is_array($d['userTraffic'] ?? null) ? $d['userTraffic'] : [];
                            $rwOnlineAt = $rwTraffic['onlineAt'] ?? ($d['onlineAt'] ?? null);
                            $rwOnlineTs = (!empty($rwOnlineAt) && !is_numeric($rwOnlineAt)) ? strtotime((string) $rwOnlineAt) : (int) $rwOnlineAt;
                            $rwIsOnline = ($rwOnlineTs > 0) && ((time() - $rwOnlineTs) <= 300);
                            $rwNodeUuid = (string) ($rwTraffic['lastConnectedNodeUuid'] ?? ($d['lastConnectedNodeUuid'] ?? ''));
                            $rwNodeName = ($rwNodeUuid !== '' && method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_node_name')) ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_node_name($Get_Data_Panel['name_panel'], $rwNodeUuid) : '';
                            $Output = array(
                                'status' => $accountStatus,
                                'username' => $username,
                                'data_limit' => $trafficLimit,
                                'used_traffic' => (int) ($rwTraffic['usedTrafficBytes'] ?? ($d['usedTrafficBytes'] ?? 0)),
                                'expire' => $expireTs ?: 0,
                                'data_limit_reset' => 'no_reset',
                                'account_status' => $accountStatus,
                                'online_at' => $rwOnlineAt,
                                'is_online' => $rwIsOnline,
                                'node_name' => $rwNodeName,
                                'links' => $linksOut,
                                'subscription_url' => $subUrl,
                                'sub_updated_at' => null,
                                'sub_last_user_agent' => null,
                                'configs' => $linksOut,
                                'hwid_limit' => (int) ($d['hwidDeviceLimit'] ?? 0),
                                'uuid' => $localUser['panel_user_id'],
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("[REMNAWAVE-DATAUSER-CRASH] Exception Message: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
                $Output = array('status' => 'Unsuccessful', 'msg' => 'remnawave exception');
            }
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $userResponse = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaGetUser($Get_Data_Panel['name_panel'], $username);
            if ($userResponse['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $userResponse['msg']
                );
            }
            $userData = $userResponse['data'];
            if (!is_array($userData)) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'User not found'
                );
            }
            $rebeccaStatus = strtolower((string) ($userData['status'] ?? 'active'));
            if (!in_array($rebeccaStatus, ["active", "disabled", "expired", "limited", "on_hold"], true)) {
                $rebeccaStatus = "active";
            }
            $subscriptionUrl = $userData['subscription_url'] ?? '';
            if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $subscriptionUrl)) {
                $subscriptionUrl = rtrim((string) ($Get_Data_Panel['url_panel'] ?? ''), '/') . "/" . ltrim($subscriptionUrl, "/");
            }
            if (method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaBuildDisplayLinks')) {
                $links = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaBuildDisplayLinks($Get_Data_Panel['name_panel'], $userData, $username);
                if (empty($links) && !empty($subscriptionUrl)) {
                    $links = [$subscriptionUrl];
                }
            } else {
                $links = !empty($subscriptionUrl) ? [$subscriptionUrl] : [];
            }
            if ($inoice != false) {
                $subscriptionUrl = "https://$domainhosts/sub/" . $inoice['id_invoice'];
            }
            $Output = array(
                'status' => $rebeccaStatus,
                'username' => $userData['username'] ?? $username,
                'data_limit' => intval($userData['data_limit'] ?? 0),
                'expire' => intval($userData['expire'] ?? 0),
                'online_at' => $userData['online_at'] ?? null,
                'used_traffic' => intval($userData['used_traffic'] ?? 0),
                'links' => $links,
                'links_diagnostic' => array('panel_type' => 'rebecca', 'outcome' => empty($links) ? 'empty' : 'ok'),
                'subscription_url' => $subscriptionUrl,
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
                'uuid' => null,
                'data_limit_reset' => $userData['data_limit_reset_strategy'] ?? null,
                'ip_limit' => intval($userData['ip_limit'] ?? 0),
                'on_hold_expire_duration' => $userData['on_hold_expire_duration'] ?? null,
            );
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            $Output = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelDataUser($Get_Data_Panel, $username);
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
}
