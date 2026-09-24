<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Marzban.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/PasarGuard.php';
require_once __DIR__ . '/x-ui_single.php';
require_once __DIR__ . '/WGDashboard.php';
require_once __DIR__ . '/remnawave.php';
require_once __DIR__ . '/rebecca.php';

class ManagePanel
{
    public $pdo, $domainhosts, $name_panel;


    private static $panelRowCache = [];


    private function loadPanel(string $key, string $column = 'name_panel'): ?array
    {
        $cacheKey = $column . '|' . $key;
        if (array_key_exists($cacheKey, self::$panelRowCache)) {
            return self::$panelRowCache[$cacheKey];
        }
        $row = select('marzban_panel', '*', $column, $key, 'select');
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
    function createUser($name_panel, $code_product, $usernameC, array $Data_Config)
    {
        $Output = [];
        global $pdo, $domainhosts;
        if (strlen($usernameC) < 3) {
            return array(
                "status" => "Unsuccessful",
                "msg" => "Username must be at least 3 characters long."
            );
        }


        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if ($Get_Data_Panel == false) {
            $Output['status'] = 'Unsuccessful';
            $Output['msg'] = 'Panel Not Found';
            return $Output;
        }
        if ($Get_Data_Panel['subvip'] == "onsubvip") {
            $inoice = select("invoice", "*", "username", $usernameC, "select");
        } else {
            $inoice = false;
        }
        if (!in_array($code_product, ["usertest", "🛍 حجم دلخواه", "customvolume"])) {

            $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:name_panel, Location) > 0 OR Location = '/all')  AND code_product = :code_product");
            $stmt->bindParam(':name_panel', $name_panel);
            $stmt->bindParam(':code_product', $code_product);
            $stmt->execute();
            $Get_Data_Product = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            if ($code_product == "usertest") {
                $Get_Data_Product['name_product'] = "usertest";
            } else {
                $Get_Data_Product['name_product'] = false;
            }
            $Get_Data_Product['data_limit_reset'] = "no_reset";
        }
        $expire = $Data_Config['expire'];
        $data_limit = $Data_Config['data_limit'];
        $note = "{$Data_Config['from_id']} | {$Data_Config['username']} | {$Data_Config['type']}";
        if ($Get_Data_Panel['type'] == "marzban") {

            $ConnectToPanel = adduser($Get_Data_Panel['name_panel'], $data_limit, $usernameC, $expire, $note, $Get_Data_Product['data_limit_reset'], $Get_Data_Product['name_product']);
            if (!empty($ConnectToPanel['status']) && (int)$ConnectToPanel['status'] !== 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['status']
                );
            }
            if (!empty($ConnectToPanel['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['error']
                );
            }
            $data_Output = json_decode($ConnectToPanel['body'], true);
            if (!empty($data_Output['detail']) && $data_Output['detail']) {
                $Output['status'] = 'Unsuccessful';
                if ($data_Output['detail']) {
                    $Output['msg'] = $data_Output['detail'];
                } else {
                    $Output['msg'] = '';
                }
            } else {
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $data_Output['subscription_url'])) {
                    $data_Output['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($data_Output['subscription_url'], "/");
                }
                if ((string)($Get_Data_Panel['version_panel'] ?? '0') === '1') {
                    $out_put_link = outputlunk($data_Output['subscription_url']);
                    if (isBase64($out_put_link)) {
                        $data_Output['links'] = base64_decode(outputlunk($data_Output['subscription_url']));
                    }
                    $data_Output['links'] = explode("\n", $data_Output['links']);
                }
                if ($inoice != false) {
                    $data_Output['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                $Output['status'] = 'successful';
                $Output['username'] = $data_Output['username'];
                $Output['subscription_url'] = $data_Output['subscription_url'];
                $Output['configs'] = $data_Output['links'];
            }
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $ConnectToPanel = pasarguardAddUser($Get_Data_Panel['name_panel'], $data_limit, $usernameC, $expire, $note, $Get_Data_Product['data_limit_reset'], $Get_Data_Product['name_product']);
            if (!empty($ConnectToPanel['status']) && (int)$ConnectToPanel['status'] !== 201) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['status']
                );
            }
            if (!empty($ConnectToPanel['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['error']
                );
            }
            $data_Output = json_decode($ConnectToPanel['body'], true);
            if (!empty($data_Output['detail']) && $data_Output['detail']) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['detail'];
            } else {
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $data_Output['subscription_url'])) {
                    $data_Output['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($data_Output['subscription_url'], "/");
                }
                if ($inoice != false) {
                    $data_Output['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                $Output['status'] = 'successful';
                $Output['username'] = $data_Output['username'];
                $Output['subscription_url'] = $data_Output['subscription_url'];
                $Output['configs'] = $data_Output['links'] ?? array();
            }
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            $subId = bin2hex(random_bytes(8));
            if (isset($Get_Data_Product['inbounds']) and $Get_Data_Product['inbounds'] != null) {
                $inbounds = $Get_Data_Product['inbounds'];
            } else {
                $inbounds = $Get_Data_Panel['inboundid'];
            }
            $productIpLimit = isset($Data_Config['ip_limit'])
                ? intval($Data_Config['ip_limit'])
                : (isset($Get_Data_Product['ip_limit']) ? intval($Get_Data_Product['ip_limit']) : 0);
            $productHwidLimit = isset($Data_Config['hwid_limit'])
                ? intval($Data_Config['hwid_limit'])
                : (isset($Get_Data_Product['hwid_limit']) ? intval($Get_Data_Product['hwid_limit']) : 0);
            $data_Output = addClient($Get_Data_Panel['name_panel'], $usernameC, $expire, $data_limit, generateUUID(), "", $subId, $inbounds, $Get_Data_Product['name_product'], $note, $productIpLimit, $productHwidLimit);
            if (!empty($data_Output['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $data_Output['error']
                );
            } elseif (!empty($data_Output['status']) && $data_Output['status'] != 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $data_Output['status']
                );
            } else {
                $data_Output = json_decode($data_Output['body'], true);
                if (!$data_Output['success']) {
                    $Output['status'] = 'Unsuccessful';
                    $Output['msg'] = $data_Output['msg'];
                } else {
                    $subscriptionUrl = rtrim($Get_Data_Panel['linksubx'], '/') . "/{$subId}";
                    $singleConfig = get_single_link_after_create(
                        $Get_Data_Panel['url_panel'],
                        $inbounds,
                        $subscriptionUrl,
                        $usernameC,
                        $Get_Data_Panel['name_panel'],
                        $Get_Data_Panel['code_panel'] ?? null
                    );
                    $links_user = [];
                    if ($singleConfig && $singleConfig['type'] === 'link') {
                        $links_user[] = $singleConfig['value'];
                    }
                    $subscriptionLinks = get_subscription_links_with_retry($subscriptionUrl);
                    if (is_array($subscriptionLinks)) {
                        foreach ($subscriptionLinks as $linkItem) {
                            if (!in_array($linkItem, $links_user, true)) {
                                $links_user[] = $linkItem;
                            }
                        }
                    }
                    if (empty($links_user)) {
                        $links_user[] = 'در دسترس نیست';
                    }
                    $Output['status'] = 'successful';
                    $Output['username'] = $usernameC;
                    $Output['subscription_url'] = $subscriptionUrl;
                    $Output['configs'] = $links_user;
                    $Output['ip_limit'] = $productIpLimit;
                    $Output['hwid_limit'] = $productHwidLimit;
                    if ($inoice != false) {
                        $Output['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                    }
                }
            }
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $serviceIdsSource = $Get_Data_Panel['guard_service_ids'] ?? null;
            if (!empty($Get_Data_Product['inbounds'])) {
                $decodedServices = json_decode($Get_Data_Product['inbounds'], true);
                if (is_array($decodedServices)) {
                    $serviceIdsSource = $decodedServices;
                }
            }
            $serviceResult = guardResolveServiceIds($Get_Data_Panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $serviceResult['msg']
                );
            }
            $guardIsTestAccount = ($Get_Data_Product['name_product'] ?? '') === 'usertest';
            $guardOnHoldEnabled = $guardIsTestAccount
                ? (($Get_Data_Panel['on_hold_test'] ?? '0') !== '0')
                : (($Get_Data_Panel['conecton'] ?? '') === 'onconecton');
            $guardSubscriptionEntry = array(
                "username" => $usernameC,
                "limit_usage" => $data_limit,
                "service_ids" => $serviceResult['service_ids'],
                "note" => $note
            );
            if ($guardOnHoldEnabled && $expire != 0) {
                $guardOnHoldSeconds = max(60, $expire - time());
                $guardSubscriptionEntry['limit_expire'] = -$guardOnHoldSeconds;
            } else {
                $guardSubscriptionEntry['limit_expire'] = guardNormalizeExpire($expire);
            }
            $payload = array($guardSubscriptionEntry);
            $createResponse = guardCreateSubscription($Get_Data_Panel['name_panel'], $payload);
            if ($createResponse['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $createResponse['msg']
                );
            }
            $subscriptionUrl = '';
            $configs = array();
            $createdData = $createResponse['data'];
            if (is_array($createdData) && isset($createdData[0])) {
                $first = $createdData[0];
                if (isset($first['subscription_url'])) {
                    $subscriptionUrl = $first['subscription_url'];
                } elseif (isset($first['subscription_link'])) {
                    $subscriptionUrl = $first['subscription_link'];
                } elseif (isset($first['subscription'])) {
                    $subscriptionUrl = $first['subscription'];
                }
            } elseif (is_array($createdData)) {
                if (isset($createdData['subscription_url'])) {
                    $subscriptionUrl = $createdData['subscription_url'];
                } elseif (isset($createdData['subscription_link'])) {
                    $subscriptionUrl = $createdData['subscription_link'];
                } elseif (isset($createdData['subscription'])) {
                    $subscriptionUrl = $createdData['subscription'];
                }
            }
            if (!empty($subscriptionUrl)) {
                $configs[] = $subscriptionUrl;
            }
            $userData = $this->DataUser($Get_Data_Panel['name_panel'], $usernameC);
            if (!empty($userData) && (!isset($userData['status']) || $userData['status'] != "Unsuccessful")) {
                if (!empty($userData['subscription_url'])) {
                    $subscriptionUrl = $userData['subscription_url'];
                }
                if (!empty($userData['links'])) {
                    $configs = $userData['links'];
                }
            }
            if ($inoice != false) {
                $subscriptionUrl = "https://$domainhosts/sub/" . $inoice['id_invoice'];
            }
            $Output['status'] = 'successful';
            $Output['username'] = $usernameC;
            $Output['subscription_url'] = $subscriptionUrl;
            $Output['configs'] = $configs;
        } elseif ($Get_Data_Panel['type'] == "Manualsale") {
            $statement = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :code_panel AND status = 'active' AND codeproduct = '$code_product' ORDER BY RAND() LIMIT 1");
            $statement->execute(array(':code_panel' => $Get_Data_Panel['code_panel']));
            $configman = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($configman) || empty($configman['id'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'Manualsale stock not found'
                );
            }
            $Output['status'] = 'successful';
            $Output['username'] = $usernameC;
            $Output['subscription_url'] = $configman['contentrecord'];
            $Output['configs'] = "";
            $Output['file_ext'] = $configman['file_ext'];
            $Output['sub_link'] = $configman['sub_link'] ?? '';
            $manualClaimedRows = array($configman);
            if (!empty($configman['group_id'])) {
                $claimGroup = $pdo->prepare("UPDATE manualsell SET status = 'selled', username = :username WHERE group_id = :group_id AND status = 'active'");
                $claimGroup->execute(array(':username' => $usernameC, ':group_id' => $configman['group_id']));
                $groupFetch = $pdo->prepare("SELECT * FROM manualsell WHERE group_id = :group_id AND username = :username ORDER BY id ASC");
                $groupFetch->execute(array(':group_id' => $configman['group_id'], ':username' => $usernameC));
                $groupRows = $groupFetch->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($groupRows)) {
                    $manualClaimedRows = $groupRows;
                }
            } else {
                update("manualsell", "status", "selled", "id", $configman['id']);
                update("manualsell", "username", $usernameC, "id", $configman['id']);
            }
            $manualItemsOut = array();
            $manualUnifiedSubOut = '';
            foreach ($manualClaimedRows as $mRow) {
                $mContent = (string)($mRow['contentrecord'] ?? '');
                $mSub = trim((string)($mRow['sub_link'] ?? ''));
                if ($manualUnifiedSubOut === '' && $mSub !== '') {
                    $manualUnifiedSubOut = $mSub;
                }
                if ($mContent === '') {
                    continue;
                }
                $manualItemsOut[] = array(
                    'content'  => $mContent,
                    'file_ext' => (string)($mRow['file_ext'] ?? ''),
                    'sub_link' => $mSub,
                );
            }
            $Output['manual_items'] = $manualItemsOut;
            $Output['manual_sub_link'] = $manualUnifiedSubOut;
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $data_limit = round($data_limit / (1024 * 1024 * 1024), 2);
            $data_Output = addpear($Get_Data_Panel['name_panel'], $usernameC);
            if (isset($data_Output['status']) && $data_Output['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => isset($data_Output['msg']) ? $data_Output['msg'] : ''
                );
            }
            if (!empty($data_Output['status']) && $data_Output['status'] != 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $data_Output['status']
                );
            }
            if (!empty($data_Output['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $data_Output['error']
                );
            }
            $data_Output = $data_Output['body'];
            $response = json_decode($data_Output['response'], true);
            if ($data_limit != 0) {
                $jobResponse = setjob($Get_Data_Panel['name_panel'], "total_data", $data_limit, $data_Output['public_key']);
                if (isset($jobResponse['status']) && $jobResponse['status'] === false) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => isset($jobResponse['msg']) ? $jobResponse['msg'] : ''
                    );
                }
            }
            if ($expire != 0) {
                $jobResponse = setjob($Get_Data_Panel['name_panel'], "date", date('Y-m-d H:i:s', $expire), $data_Output['public_key']);
                if (isset($jobResponse['status']) && $jobResponse['status'] === false) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => isset($jobResponse['msg']) ? $jobResponse['msg'] : ''
                    );
                }
            }
            update("invoice", "user_info", json_encode($data_Output), "username", $usernameC);
            if (!$response['status']) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['msg'];
            } else {
                $download_config = downloadconfig($Get_Data_Panel['name_panel'], $data_Output['public_key']);
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
                $Output['status'] = 'successful';
                $Output['username'] = $usernameC;
                $Output['subscription_url'] = strval($download_config['file']);
                $Output['configs'] = [];
            }
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            $squad = (string) ($Get_Data_Panel['remna_squad'] ?? '');
            $invoiceId = (string) ($Data_Config['id_order'] ?? ($Data_Config['invoice'] ?? ''));
            $remnaHwidLimit = isset($Data_Config['hwid_limit'])
                ? intval($Data_Config['hwid_limit'])
                : (isset($Get_Data_Product['hwid_limit']) ? intval($Get_Data_Product['hwid_limit']) : 0);
            $data_Output = remnawave_adduser($Get_Data_Panel['name_panel'], $data_limit, $usernameC, $expire, $squad, $invoiceId, $remnaHwidLimit);
            if (($data_Output['status'] ?? '') !== 'successful') {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['msg'] ?? '';
            } else {
                $Output['status'] = 'successful';
                $Output['username'] = $usernameC;
                $Output['subscription_url'] = $data_Output['subscription_url'];
                $Output['configs'] = (!empty($data_Output['configs']) && is_array($data_Output['configs'])) ? $data_Output['configs'] : [$data_Output['subscription_url']];
            }
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $rebeccaServiceValue = $Get_Data_Panel['rebecca_service_id'] ?? null;
            if (!empty($Get_Data_Product['inbounds'])) {
                $rebeccaServiceValue = $Get_Data_Product['inbounds'];
            }
            $rebeccaIpLimit = isset($Data_Config['ip_limit'])
                ? intval($Data_Config['ip_limit'])
                : (isset($Get_Data_Product['ip_limit']) ? intval($Get_Data_Product['ip_limit']) : 0);
            $rebeccaOnHoldDuration = null;
            $rebeccaIsTestAccount = ($Get_Data_Product['name_product'] ?? '') === 'usertest';
            $rebeccaOnHoldEnabled = $rebeccaIsTestAccount
                ? (($Get_Data_Panel['on_hold_test'] ?? '0') !== '0')
                : (($Get_Data_Panel['conecton'] ?? '') === 'onconecton');
            if ($rebeccaOnHoldEnabled && $expire != 0) {
                $rebeccaOnHoldDuration = $expire - time();
            }
            $createResponse = rebeccaCreateUser($Get_Data_Panel['name_panel'], $usernameC, $data_limit, $expire, $rebeccaServiceValue, $note, $rebeccaIpLimit, $rebeccaOnHoldDuration);
            if ($createResponse['status'] === false) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $createResponse['msg'];
            } else {
                $createdData = $createResponse['data'];
                $subscriptionUrl = is_array($createdData) ? ($createdData['subscription_url'] ?? '') : '';
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $subscriptionUrl)) {
                    $subscriptionUrl = rtrim((string) ($Get_Data_Panel['url_panel'] ?? ''), '/') . "/" . ltrim($subscriptionUrl, "/");
                }
                $configs = function_exists('rebeccaBuildDisplayLinks') && is_array($createdData)
                    ? rebeccaBuildDisplayLinks($Get_Data_Panel['name_panel'], $createdData, $usernameC)
                    : [];
                if (empty($configs) && !empty($subscriptionUrl)) {
                    $configs[] = $subscriptionUrl;
                }
                if ($inoice != false) {
                    $subscriptionUrl = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                $Output['status'] = 'successful';
                $Output['username'] = $usernameC;
                $Output['subscription_url'] = $subscriptionUrl;
                $Output['configs'] = $configs;
            }
        } else {
            $Output['status'] = 'Unsuccessful';
            $Output['msg'] = 'Panel Not Found';
        }
        if (function_exists('normalizeServiceConfigs')) {
            if (isset($Output['status']) && $Output['status'] === 'successful') {
                $Output['configs'] = normalizeServiceConfigs($Output['configs'] ?? null, $Output['subscription_url'] ?? null);
            } else {
                $Output['configs'] = normalizeServiceConfigs($Output['configs'] ?? null);
            }
        } else {
            if (!isset($Output['configs'])) {
                $Output['configs'] = [];
            } elseif (!is_array($Output['configs'])) {
                $value = trim((string) $Output['configs']);
                $Output['configs'] = $value === '' ? [] : [$value];
            }
        }
        return $Output;
    }
    function DataUser($name_panel, $username)
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
            $inoice = select("invoice", "*", "username", $username, "select");
        } else {
            $inoice = false;
        }
        if ($Get_Data_Panel['type'] == "marzban") {
            $UsernameData = getuser($username, $Get_Data_Panel['name_panel']);
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
                    $UsernameData['expire'] = strtotime($UsernameData['expire']);
                    $UsernameData['links'] = base64_decode(outputlunk($UsernameData['subscription_url']));
                    $UsernameData['links'] = explode("\n", $UsernameData['links']);
                    $sublist_update = get_list_update($name_panel, $username);
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
                    'inbounds' => $UsernameData['inbounds'] ?? null
                );
            }
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $UsernameData = pasarguardGetUser($username, $Get_Data_Panel['name_panel']);
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
                $pasarguardLinksResponse = pasarguardGetSubscriptionLinks($username, $Get_Data_Panel['name_panel']);
                if (empty($pasarguardLinksResponse['error']) && !empty($pasarguardLinksResponse['links'])) {
                    $pasarguardLinks = $pasarguardLinksResponse['links'];
                }
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
                    'group_ids' => $UsernameData['group_ids'] ?? null
                );
            }
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $subscription = guardGetSubscription($Get_Data_Panel['name_panel'], $username);
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
            $usageResponse = guardGetSubscriptionUsages($Get_Data_Panel['name_panel'], $username);
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
            $linksResponse = guardGetSubscriptionLinks($Get_Data_Panel['name_panel'], $username);
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
            $user_data = get_clinets($username, $Get_Data_Panel['name_panel']);
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
            if ($user_data['expiryTime'] < -10000) {
                $user_data['enable'] = "on_hold";
                $expire = 0;
            }
            $subscriptionUrl = rtrim($Get_Data_Panel['linksubx'], '/') . "/{$user_data['subId']}";
            $linksub = $subscriptionUrl;
            $links_user_raw = outputlunk($subscriptionUrl);
            if (!is_string($links_user_raw)) {
                $links_user_raw = '';
            }
            if (isBase64($links_user_raw)) {
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
                if (is_file(xuisingle_cookie_path())) {
                    @unlink(xuisingle_cookie_path());
                }
                login($Get_Data_Panel['code_panel']);
                $singleConfig = get_single_link_smart(
                    $Get_Data_Panel['url_panel'],
                    $Get_Data_Panel['inboundid'],
                    $subscriptionUrl,
                    $username,
                    $Get_Data_Panel['name_panel'],
                    $Get_Data_Panel['code_panel'] ?? null
                );
                if (is_file(xuisingle_cookie_path())) {
                    @unlink(xuisingle_cookie_path());
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
                        'content' => (string) ($mr['contentrecord'] ?? ''),
                        'file_ext' => (string) ($mr['file_ext'] ?? ''),
                        'sub_link' => (string) ($mr['sub_link'] ?? ''),
                    );
                    if ($manualUnifiedSub === '' && trim((string) ($mr['sub_link'] ?? '')) !== '') {
                        $manualUnifiedSub = trim((string) $mr['sub_link']);
                    }
                }
            }
            $service = select("invoice", "*", "username", $username, "select");
            if (!is_array($service)) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'User not found'
                );
            }
            $volumeValue = (float) ($service['Volume'] ?? 0);
            $volumeUnit = function_exists('rxInvoiceVolumeUnit') ? rxInvoiceVolumeUnit($service) : ((($service['name_product'] ?? '') === 'سرویس تست') ? 'MB' : 'GB');
            $data_limit = $volumeValue == 0 ? null : (function_exists('rxVolumeToBytes') ? rxVolumeToBytes($volumeValue, $volumeUnit) : $volumeValue * ($volumeUnit === 'MB' ? pow(1024, 2) : pow(1024, 3)));
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
            $UsernameData = get_userwg($username, $Get_Data_Panel['name_panel']);
            if (isset($UsernameData['status']) && $UsernameData['status'] === false && !isset($UsernameData['id'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => isset($UsernameData['msg']) ? $UsernameData['msg'] : ''
                );
            }
            $invoiceinfo = select("invoice", "*", "username", $username, "select");
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
                if (intval($invoiceinfo['Service_time']) == 0) {
                    $expire = 0;
                } else {
                    if (isset($jobtime['Value'])) {
                        $expire = strtotime($jobtime['Value']);
                    } else {
                        $expire = 0;
                    }
                }
                $status = "active";
                if (!$UsernameData['configuration']['Status'])
                    $status = "disabled";
                if ($expire != 0 and $expire - time() < 0) {
                    $status = "expired";
                }
                $data_useage = ($UsernameData['total_data'] * pow(1024, 3)) + ($UsernameData['cumu_data'] * pow(1024, 3));
                if (($jobvolume['Value'] * pow(1024, 3)) < $data_useage) {
                    $status = "limited";
                }
                $download_config = downloadconfig($Get_Data_Panel['name_panel'], $UsernameData['id']);
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
                $localUser = remnawave_find_user($username);
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
                            if (function_exists('remnawave_get_configs')) {
                                $cfgRes = remnawave_get_configs($Get_Data_Panel['name_panel'], $username);
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
                            $rwNodeName = ($rwNodeUuid !== '' && function_exists('remnawave_node_name')) ? remnawave_node_name($Get_Data_Panel['name_panel'], $rwNodeUuid) : '';
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
            $userResponse = rebeccaGetUser($Get_Data_Panel['name_panel'], $username);
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
            if (function_exists('rebeccaBuildDisplayLinks')) {
                $links = rebeccaBuildDisplayLinks($Get_Data_Panel['name_panel'], $userData, $username);
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
                'subscription_url' => $subscriptionUrl,
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
                'uuid' => null,
                'data_limit_reset' => $userData['data_limit_reset_strategy'] ?? null,
                'ip_limit' => intval($userData['ip_limit'] ?? 0),
            );
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function Revoke_sub($name_panel, $username)
    {
        $Output = array();
        $ManagePanel = new ManagePanel();
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if ($Get_Data_Panel['type'] == "marzban") {
            $revoke_sub = revoke_sub($username, $name_panel);
            if (isset($revoke_sub['detail']) && $revoke_sub['detail']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['detail']
                );
            } else {
                $config = new ManagePanel();
                $Data_User = $config->DataUser($name_panel, $username);
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $Data_User['subscription_url'])) {
                    $Data_User['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($Data_User['subscription_url'], "/");
                }
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'],
                    'subscription_url' => $Data_User['subscription_url']
                );
            }
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $revoke_sub = pasarguardRevokeSub($username, $name_panel);
            if (isset($revoke_sub['detail']) && $revoke_sub['detail']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['detail']
                );
            } else {
                $config = new ManagePanel();
                $Data_User = $config->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? null
                );
            }
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $revoke_sub = guardRevokeSubscriptions($name_panel, array($username));
            if ($revoke_sub['status'] === false) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['msg']
                );
            } else {
                $Data_User = $ManagePanel->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? null
                );
            }
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            $subId = bin2hex(random_bytes(8));
            $config = array(
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "id" => generateUUID(),
                                "enable" => true,
                                "subId" => $subId,
                            )
                        ),
                    )
                )
            );
            $updateinbound = $ManagePanel->Modifyuser($username, $Get_Data_Panel['name_panel'], $config);
            if (!$updateinbound['status']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => 'Unsuccessful'
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'configs' => [outputlunk($Get_Data_Panel['linksubx'] . "/{$subId}")],
                    'subscription_url' => $Get_Data_Panel['linksubx'] . "/{$subId}",
                );
            }
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            $revoke = remnawave_revoke($name_panel, $username);
            if (($revoke['status'] ?? '') !== 'successful') {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke['msg'] ?? 'revoke failed'
                );
            } else {
                $Data_User = $ManagePanel->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? ($revoke['subscription_url'] ?? '')
                );
            }
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $revoke = rebeccaRevokeSub($name_panel, $username);
            if ($revoke['status'] === false) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke['msg']
                );
            } else {
                $Data_User = $ManagePanel->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? null
                );
            }
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function RemoveUser($name_panel, $username)
    {
        $Output = array();
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if ($Get_Data_Panel['type'] == "marzban") {
            $UsernameData = removeuser($Get_Data_Panel['name_panel'], $username);
            if (!empty($UsernameData['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            }
            $httpStatus = isset($UsernameData['status']) ? (int)$UsernameData['status'] : 0;
            if (in_array($httpStatus, [200, 201, 202, 204, 404], true)) {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            } else {
                $bodyDecoded = (!empty($UsernameData['body']) && is_string($UsernameData['body'])) ? json_decode($UsernameData['body'], true) : null;
                $msg = is_array($bodyDecoded) ? ($bodyDecoded['detail'] ?? ($bodyDecoded['message'] ?? $httpStatus)) : ($httpStatus ?: 'remove failed');
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $msg
                );
            }
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $UsernameData = pasarguardRemoveUser($Get_Data_Panel['name_panel'], $username);
            if (!empty($UsernameData['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            }
            $httpStatus = isset($UsernameData['status']) ? (int)$UsernameData['status'] : 0;
            if (in_array($httpStatus, [200, 201, 202, 204, 404], true)) {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            } else {
                $bodyDecoded = (!empty($UsernameData['body']) && is_string($UsernameData['body'])) ? json_decode($UsernameData['body'], true) : null;
                $msg = is_array($bodyDecoded) ? ($bodyDecoded['detail'] ?? ($bodyDecoded['message'] ?? $httpStatus)) : ($httpStatus ?: 'remove failed');
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $msg
                );
            }
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            $UsernameData = removeClient($Get_Data_Panel['name_panel'], $username);
            if (!empty($UsernameData['status']) && $UsernameData['status'] != 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['status']
                );
            } elseif (!empty($UsernameData['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            }
            $UsernameData = json_decode($UsernameData['body'], true);
            if (!$UsernameData['success']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['msg']
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "Manualsale") {
            update("manualsell", "status", "delete", "username", $username);
            $Output = array(
                'status' => 'successful',
                'username' => $username,
            );
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $UsernameData = remove_userwg($Get_Data_Panel['name_panel'], $username);
            if (!$UsernameData['status']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['msg']
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $UsernameData = guardDeleteSubscriptions($Get_Data_Panel['name_panel'], array($username));
            if ($UsernameData['status'] === false) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['msg']
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            $res = remnawave_remove_user($Get_Data_Panel['name_panel'], $username);
            if (($res['status'] ?? '') !== 'successful') {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $res['msg'] ?? 'remove failed'
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $removeResponse = rebeccaRemoveUser($Get_Data_Panel['name_panel'], $username);
            if ($removeResponse['status'] === false) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $removeResponse['msg']
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function Modifyuser($username, $name_panel, $config = array())
    {

        $Output = array();
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if (!$Get_Data_Panel || !is_array($Get_Data_Panel) || empty($Get_Data_Panel['type'])) {
            return array(
                'status' => false,
                'msg' => 'Panel Not Found'
            );
        }
        if ($Get_Data_Panel['type'] == "marzban") {
            if ((string)($Get_Data_Panel['version_panel'] ?? '0') === '1') {
                $result = getuser($username, $name_panel);
                if (!empty($result['body'])) {
                    $result = json_decode($result['body'], true);
                    if (is_array($result) && isset($result['proxy_settings'])) {
                        $config['proxy_settings'] = $result['proxy_settings'];
                    }
                }
            }
            $modify = Modifyuser($name_panel, $username, $config);
            if (!empty($modify['error'])) {
                return array(
                    'status' => false,
                    'msg' => $modify['error']
                );
            } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $modify['status']
                );
            }
            $modifycheck = json_decode($modify['body'], true);
            if (!empty($modifycheck['detail'])) {
                return array(
                    'status' => false,
                    'msg' => $modifycheck['detail']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $modify = pasarguardModifyUser($name_panel, $username, $config);
            if (!empty($modify['error'])) {
                return array(
                    'status' => false,
                    'msg' => $modify['error']
                );
            } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $modify['status']
                );
            }
            $modifycheck = json_decode($modify['body'], true);
            if (!empty($modifycheck['detail'])) {
                return array(
                    'status' => false,
                    'msg' => $modifycheck['detail']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            if (xui_panel_uses_token($Get_Data_Panel)) {
                $fullClient = xui_get_full_client($Get_Data_Panel, $username);
                if (empty($fullClient['status'])) {
                    return array(
                        'status' => false,
                        'msg' => $fullClient['msg'] ?? 'User not found'
                    );
                }
                $baseClient = $fullClient['client'];
                $configs = array(
                    'settings' => json_encode(array('clients' => array($baseClient))),
                );
                $configs['settings'] = json_encode(array_replace_recursive(json_decode($configs['settings'], true), json_decode($config['settings'], true)));
                $modify = updateClient($Get_Data_Panel['name_panel'], $baseClient['uuid'] ?? ($baseClient['id'] ?? ''), $configs);
                if (!empty($modify['error'])) {
                    return array(
                        'status' => false,
                        'msg' => $modify['error']
                    );
                } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                    return array(
                        'status' => false,
                        'msg' => 'error code : ' . $modify['status']
                    );
                }
                $modify = json_decode($modify['body'], true);
                if (!$modify['success']) {
                    return array(
                        'status' => false,
                        'msg' => 'error :' . $modify['msg']
                    );
                }
                return array(
                    'status' => true,
                    'data' => $modify
                );
            }
            $clients = get_clinets($username, $name_panel);
            if (!empty($clients['error'])) {
                return array(
                    'status' => false,
                    'msg' => $clients['error']
                );
            } elseif (!empty($clients['status']) && $clients['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => json_encode($clients)
                );
            }
            $clients = json_decode($clients['body'], true);
            if (!is_array($clients)) {
                return array(
                    'status' => false,
                    'msg' => 'object invalid'
                );
            }
            if (empty($clients['obj'])) {
                return array(
                    'status' => false,
                    'msg' => "User not found"
                );
            }
            $clients = $clients['obj'];
            $configs = array(
                'id' => intval($clients['inboundId']),
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "id" => $clients['uuid'],
                                "flow" => "",
                                "email" => $clients['email'],
                                "totalGB" => $clients['total'],
                                "expiryTime" => $clients['expiryTime'],
                                "enable" => true,
                                "subId" => $clients['subId'],
                            )
                        ),
                        'decryption' => 'none',
                        'fallbacks' => array(),
                    )
                ),
            );
            $configs['settings'] = json_encode(array_replace_recursive(json_decode($configs['settings'], true), json_decode($config['settings'], true)));
            $modify = updateClient($Get_Data_Panel['name_panel'], $clients['uuid'], $configs);
            if (!empty($modify['error'])) {
                return array(
                    'status' => false,
                    'msg' => $modify['error']
                );
            } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $modify['status']
                );
            }
            $modify = json_decode($modify['body'], true);
            if (!$modify['success']) {
                return array(
                    'status' => false,
                    'msg' => 'error :' . $modify['msg']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $data_user = get_userwg($username, $name_panel);
            if (isset($data_user['status']) && $data_user['status'] === false && !isset($data_user['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($data_user['msg']) ? $data_user['msg'] : ''
                );
            }
            $configs = array(
                "DNS" => $data_user['DNS'],
                "allowed_ip" => $data_user['allowed_ip'],
                "endpoint_allowed_ip" => "0.0.0.0/0",
                "jobs" => $data_user['jobs'],
                "id" => $data_user['id'],
                "keepalive" => $data_user['keepalive'],
                "mtu" => $data_user['mtu'],
                "name" => $data_user['name'],
                "preshared_key" => $data_user['preshared_key'],
                "private_key" => $data_user['private_key']
            );
            $configs = array_merge($configs, $config);
            $modify = updatepear($Get_Data_Panel['name_panel'], $configs);
            if (isset($modify['status']) && $modify['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($modify['msg']) ? $modify['msg'] : ''
                );
            }
            if (!empty($modify['error'])) {
                return array(
                    'status' => false,
                    'msg' => $modify['error']
                );
            } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $modify['status']
                );
            }
            $modify = json_decode($modify['body'], true);
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "guard") {
            if (isset($config['limit_expire'])) {
                $config['limit_expire'] = guardNormalizeExpire($config['limit_expire']);
            }
            $modify = guardUpdateSubscription($Get_Data_Panel['name_panel'], $username, $config);
            if ($modify['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $modify['msg']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $modify = rebeccaUpdateUser($Get_Data_Panel['name_panel'], $username, $config);
            if ($modify['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $modify['msg']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        }
    }
    function Change_status($username, $name_panel)
    {
        $ManagePanel = new ManagePanel();
        $DataUserOut = $ManagePanel->DataUser($name_panel, $username);
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if ($DataUserOut['status'] == "Unsuccessful") {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => $DataUserOut['detail']
            );
            return;
        }
        if (!in_array($DataUserOut['status'], ["active", "disabled", "expired", "limited", "on_hold"])) {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => "status invalid"
            );
            return;
        }
        if ($DataUserOut['status'] === 'active' && !$this->HasLiveConnectionHistory($Get_Data_Panel, $DataUserOut)) {
            return array(
                'status' => 'Unsuccessful',
                'code' => 'not_connected',
                'msg' => 'ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.'
            );
        }
        if ($Get_Data_Panel['type'] == "marzban") {
            if ($DataUserOut['status'] == "active") {
                $status = "disabled";
            } else {
                $status = "active";
            }
            $configs = array("status" => $status);
            $ManagePanel->Modifyuser($username, $name_panel, $configs);
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            if ($DataUserOut['status'] == "active") {
                $status = "disabled";
            } else {
                $status = "active";
            }
            $configs = array("status" => $status);
            $ManagePanel->Modifyuser($username, $name_panel, $configs);
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            if ($DataUserOut['status'] == "active") {
                $status = false;
            } else {
                $status = true;
            }
            $configs = array(
                'settings' => json_encode(array(
                    'clients' => array(
                        array(
                            "enable" => $status,
                        )
                    ),
                )),
            );
            $ManagePanel->Modifyuser($username, $name_panel, $configs);
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $action = $DataUserOut['status'] == "active" ? "disable" : "enable";
            $toggle = guardToggleSubscriptions($Get_Data_Panel['name_panel'], array($username), $action);
            if ($toggle['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $toggle['msg'] ?? 'Toggle failed'
                );
            }
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            $local = remnawave_find_user($username);
            $isActive = is_array($local) ? ((string) ($local['status'] ?? 'active') === 'active') : true;
            $res = remnawave_change_status($Get_Data_Panel['name_panel'], $username, !$isActive);
            if (($res['status'] ?? '') !== 'successful') {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $res['msg'] ?? 'Toggle failed'
                );
            }
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $wantEnable = $DataUserOut['status'] != "active";
            $toggle = rebeccaChangeStatus($Get_Data_Panel['name_panel'], $username, $wantEnable);
            if ($toggle['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $toggle['msg'] ?? 'Toggle failed'
                );
            }
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        }

        return $Output;
    }
    function ResetUserDataUsage($username, $name_panel)
    {
        $panel = $this->loadPanel($name_panel, "name_panel");
        if ($panel == false) {
            return array(
                'status' => false,
                'msg' => 'data not found'
            );
        }
        if ($panel['type'] == "marzban") {
            $reset = ResetUserDataUsage($username, $panel['name_panel']);
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode($reset['body'], true);
            if (!empty($reset['detail'])) {
                return array(
                    'status' => false,
                    'msg' => $reset['detail']
                );
            }
            return array(
                'status' => true,
                'msg' => 'successful'
            );
        } elseif ($panel['type'] == "pasarguard") {
            $reset = pasarguardResetUserDataUsage($username, $panel['name_panel']);
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode($reset['body'], true);
            if (!empty($reset['detail'])) {
                return array(
                    'status' => false,
                    'msg' => $reset['detail']
                );
            }
            return array(
                'status' => true,
                'msg' => 'successful'
            );
        } elseif ($panel['type'] == 'x-ui_single') {
            $reset = ResetUserDataUsagex_uisin($username, $panel['name_panel']);
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode($reset['body'], true);
            if (!$reset['success']) {
                return array(
                    'status' => false,
                    'msg' => 'error :' . $reset['msg']
                );
            }
            return array(
                'status' => true,
                'data' => $reset
            );
        } elseif ($panel['type'] == "WGDashboard") {
            $allowResponse = allowAccessPeers($panel['name_panel'], $username);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = get_userwg($username, $panel['name_panel']);
            if (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($datauser['msg']) ? $datauser['msg'] : ''
                );
            }
            $reset = ResetUserDataUsagewg($datauser['id'], $panel['name_panel']);
            if (isset($reset['status']) && $reset['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($reset['msg']) ? $reset['msg'] : ''
                );
            }
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode($reset['body'], true);
            return array(
                'status' => true,
                'data' => $reset
            );
        } elseif ($panel['type'] == "guard") {
            $reset = guardResetSubscriptions($panel['name_panel'], array($username));
            if ($reset['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $reset['msg']
                );
            }
            return array(
                'status' => true
            );
        } elseif ($panel['type'] == "remnawave") {
            global $pdo;
            $local = remnawave_find_user($username);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $res = $mgr->resetTraffic($local['panel_user_id'], $pdo);
            if (empty($res['ok'])) {
                return array('status' => false, 'msg' => 'remnawave reset traffic failed');
            }
            return array('status' => true);
        } elseif ($panel['type'] == "rebecca") {
            $reset = rebeccaResetUsage($panel['name_panel'], $username);
            if ($reset['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $reset['msg']
                );
            }
            return array(
                'status' => true
            );
        }
    }
    function extend($Method_extend, $new_limit, $time_day, $username, $code_product, $name_panel)
    {
        $panel = $this->loadPanel($name_panel, "code_panel");
        $product = select("product", "*", "code_product", $code_product, "select");
        $invoice = select("invoice", "*", "username", $username, "select");
        if ($code_product == "custom_volume")
            $product = true;
        if ($panel == false || $product == false) {
            return array(
                'status' => false,
                'msg' => 'data not found'
            );
        }
        $data_user = $this->DataUser($panel['name_panel'], $username);
        if ($data_user['status'] == "Unsuccessful") {
            return array(
                'status' => false,
                'msg' => $data_user['msg']
            );
        }
        if ($Method_extend == "رزرو اشتراک") {
            global $pdo;
            $existingQueuedStmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE username = :username AND status = 'pending' LIMIT 1");
            $existingQueuedStmt->execute([':username' => $username]);
            $existingQueued = $existingQueuedStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingQueued !== false) {
                return array(
                    'status' => false,
                    'code' => 'queued_renewal_exists',
                    'msg' => 'a queued renewal is already pending for this service'
                );
            }
            try {
                $stmt = $pdo->prepare("INSERT INTO queued_renewal (id_user, username, name_panel, code_panel, code_product, new_limit_gb, time_day, price, id_invoice, status, bottype, created_at) VALUES (:id_user, :username, :name_panel, :code_panel, :code_product, :new_limit_gb, :time_day, :price, :id_invoice, 'pending', :bottype, :created_at)");
                $stmt->execute(array(
                    ':id_user' => $invoice != false ? $invoice['id_user'] : null,
                    ':username' => $username,
                    ':name_panel' => $panel['name_panel'],
                    ':code_panel' => $panel['code_panel'],
                    ':code_product' => $code_product,
                    ':new_limit_gb' => $new_limit,
                    ':time_day' => $time_day,
                    ':price' => $product !== true ? ($product['price_product'] ?? '0') : '0',
                    ':id_invoice' => $invoice != false ? $invoice['id_invoice'] : null,
                    ':bottype' => $invoice != false ? ($invoice['bottype'] ?? null) : null,
                    ':created_at' => date('Y/m/d H:i:s'),
                ));
            } catch (Throwable $e) {
                error_log('[panels] queued_renewal insert failed: ' . $e->getMessage());
                return array(
                    'status' => false,
                    'code' => 'queued_renewal_insert_failed',
                    'msg' => $e->getMessage()
                );
            }
            return array(
                'status' => true,
                'queued' => true
            );
        }
        if ($panel['type'] == "Manualsale") {
            global $pdo;
            $statement = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :code_panel AND status = 'active' AND codeproduct = :code_product ORDER BY RAND() LIMIT 1");
            $statement->execute(array(
                ':code_panel' => $panel['code_panel'],
                ':code_product' => $code_product,
            ));
            $newConfig = $statement->fetch(PDO::FETCH_ASSOC);
            if ($newConfig == false) {
                $statement2 = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :code_panel AND status = 'active' ORDER BY RAND() LIMIT 1");
                $statement2->execute(array(':code_panel' => $panel['code_panel']));
                $newConfig = $statement2->fetch(PDO::FETCH_ASSOC);
            }
            if ($newConfig == false) {
                return array(
                    'status' => false,
                    'code' => 'manual_stock_empty',
                    'msg' => 'no manual config available in stock for renewal'
                );
            }

            if (!empty($newConfig['group_id'])) {
                $release = $pdo->prepare("UPDATE manualsell SET status = 'delete', username = NULL WHERE username = :username AND (group_id IS NULL OR group_id <> :group_id)");
                $release->execute(array(
                    ':username' => $username,
                    ':group_id' => $newConfig['group_id'],
                ));
                $claimGroup = $pdo->prepare("UPDATE manualsell SET status = 'selled', username = :username WHERE group_id = :group_id AND status = 'active'");
                $claimGroup->execute(array(
                    ':username' => $username,
                    ':group_id' => $newConfig['group_id'],
                ));
            } else {
                $release = $pdo->prepare("UPDATE manualsell SET status = 'delete', username = NULL WHERE username = :username AND id <> :new_id");
                $release->execute(array(
                    ':username' => $username,
                    ':new_id' => $newConfig['id'],
                ));

                update("manualsell", "status", "selled", "id", $newConfig['id']);
                update("manualsell", "username", $username, "id", $newConfig['id']);
            }

            $notifctions = json_encode(array(
                'volume' => false,
                'time' => false,
            ));
            update("invoice", "notifctions", $notifctions, 'id_invoice', $invoice['id_invoice']);
            update("invoice", 'Status', "active", "username", $username);
            return array(
                'status' => true,
                'subscription_url' => $newConfig['contentrecord'],
                'configs' => "",
                'file_ext' => $newConfig['file_ext']
            );
        }
        $notifctions = json_encode(array(
            'volume' => false,
            'time' => false,
        ));
        update("invoice", "notifctions", $notifctions, 'id_invoice', $invoice['id_invoice']);
        $data_limit_old = $data_user['data_limit'];
        $time_old = $data_user['expire'];
        $time_old = time() - $time_old > 0 ? time() : $time_old;
        $data_limit_new = $new_limit == 0 ? 0 : $new_limit * pow(1024, 3);
        $data_limit_new_add = $new_limit == 0 ? 0 : $data_limit_old + ($new_limit * pow(1024, 3));
        $time_new = $time_day == 0 ? 0 : time() + $time_day * 86400;
        $time_old = $time_old == 0 ? time() : $time_old;
        $time_new_add = $time_day == 0 ? 0 : $time_old + ($time_day * 86400);

        $inbound_id = isset($panel['inboundid']) ? $panel['inboundid'] : 1;
        $inbounds = is_string($panel['inbounds']) ? json_decode($panel['inbounds']) : "{}";
        $inbounds = $product['inbounds'] != null ? json_decode($product['inbounds']) : $inbounds;
        if ($panel['type'] != "WGDashboard") {
            update("invoice", 'user_info', null, "username", $username);
        }
        update("invoice", 'uuid', null, "username", $username);
        update("invoice", 'Status', "active", "username", $username);
        if ($Method_extend == "ریست حجم و زمان") {
            $reset = $this->ResetUserDataUsage($username, $panel['name_panel']);
            if ($reset['status'] == false) {
                return array(
                    'status' => false,
                    'msg' => 'error reset : ' . $reset['msg']
                );
            }
        } elseif ($Method_extend == "اضافه شدن زمان و حجم به ماه بعد") {
            $data_limit_new = $data_limit_new_add;
            $time_new = $time_new_add;
        } elseif ($Method_extend == "ریست زمان و اضافه کردن حجم قبلی") {
            $data_limit_new = $data_limit_new_add;
        } elseif ($Method_extend == "ریست شدن حجم و اضافه شدن زمان") {
            $reset = $this->ResetUserDataUsage($username, $panel['name_panel']);
            if ($reset['status'] == false) {
                return array(
                    'status' => false,
                    'msg' => 'error reset : ' . $reset['msg']
                );
            }
            $time_new = $time_new_add;
        } elseif ($Method_extend == "اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده") {
            $reset = $this->ResetUserDataUsage($username, $panel['name_panel']);
            if ($reset['status'] == false) {
                return array(
                    'status' => false,
                    'msg' => 'error reset : ' . $reset['msg']
                );
            }
            $time_new = $time_new_add;
            $data_limit_last = $data_user['data_limit'] - $data_user['used_traffic'];
            $data_limit_last = $data_limit_last < 0 ? 0 : $data_limit_last;
            $data_limit_new = $data_limit_new + $data_limit_last;
        }
        if ($panel['type'] == "remnawave") {
            global $pdo;
            $local = remnawave_find_user($username);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $params = array(
                'id' => (int) $local['panel_user_id'],
                'expireAt' => remnawave_iso_from_timestamp($time_new),
                'trafficLimitBytes' => (int) $data_limit_new,
            );
            $res = $mgr->updateUser($params, null, $pdo);
            if (empty($res['ok'])) {
                return array('status' => false, 'msg' => 'remnawave extend failed');
            }
            return array('status' => true);
        }
        if ($this->isPasarGuardDialect($panel)) {
            $configuredGroups = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingGroups = (!empty($data_user['group_ids']) && is_array($data_user['group_ids'])) ? $data_user['group_ids'] : [];
            $mergedGroups = array_values(array_unique(array_merge($existingGroups, is_array($configuredGroups) ? $configuredGroups : [])));
            $targetGroups = !empty($mergedGroups) ? $mergedGroups : (!empty($configuredGroups) ? $configuredGroups : null);

            $data = array(
                'data_limit' => $data_limit_new,
                'expire' => ($time_new == 0 ? 0 : date('c', $time_new)),
            );
            if ($targetGroups !== null) {
                $data['group_ids'] = $targetGroups;
            }
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxy_settings'] = json_decode($invoice['uuid']);
            }
        } elseif ($panel['type'] == "marzban") {
            $configuredInbounds = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingInbounds = (!empty($data_user['inbounds']) && is_array($data_user['inbounds'])) ? $data_user['inbounds'] : [];
            $mergedInbounds = is_array($configuredInbounds) ? $configuredInbounds : [];
            if (is_array($existingInbounds)) {
                foreach ($existingInbounds as $protocol => $tags) {
                    if (is_array($tags)) {
                        $currentTags = isset($mergedInbounds[$protocol]) && is_array($mergedInbounds[$protocol]) ? $mergedInbounds[$protocol] : [];
                        $mergedInbounds[$protocol] = array_values(array_unique(array_merge($currentTags, $tags)));
                    }
                }
            }
            $targetInbounds = !empty($mergedInbounds) ? $mergedInbounds : $inbounds;
            $data = array(
                'data_limit' => $data_limit_new,
                'expire' => $time_new,
                'inbounds' => $targetInbounds,
            );
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxies'] = json_decode($invoice['uuid'], true);
            }
        } elseif ($panel['type'] == "x-ui_single") {
            $data = array(
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "totalGB" => $data_limit_new,
                                "expiryTime" => $time_new * 1000,
                                "enable" => true,
                            )
                        ),
                        'decryption' => 'none',
                        'fallbacks' => array(),
                    )
                ),
            );
        } elseif ($panel['type'] == "WGDashboard") {
            if ($data_user['status'] == "limited" || $data_user['status'] == "expired") {
                $reset = $this->ResetUserDataUsage($username, $panel['name_panel']);
                if ($reset['status'] == false) {
                    return array(
                        'status' => false,
                        'msg' => 'error reset : ' . $reset['msg']
                    );
                }
            }
            $allowResponse = allowAccessPeers($panel['name_panel'], $username);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = get_userwg($username, $panel['name_panel']);
            if (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($datauser['msg']) ? $datauser['msg'] : ''
                );
            }
            $count = 0;
            foreach ($datauser['jobs'] as $jobsvolume) {
                if ($jobsvolume['Field'] == "date") {
                    break;
                }
                $count += 1;
            }
            $datam = array(
                "Job" => $datauser['jobs'][$count],
            );
            $deleteJob = deletejob($panel['name_panel'], $datam);
            if (isset($deleteJob['status']) && $deleteJob['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($deleteJob['msg']) ? $deleteJob['msg'] : ''
                );
            }
            $count = 0;
            foreach ($datauser['jobs'] as $jobsvolume) {
                if ($jobsvolume['Field'] == "total_data") {
                    break;
                }
                $count += 1;
            }
            $datam = array(
                "Job" => $datauser['jobs'][$count],
            );
            $deleteJob = deletejob($panel['name_panel'], $datam);
            if (isset($deleteJob['status']) && $deleteJob['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($deleteJob['msg']) ? $deleteJob['msg'] : ''
                );
            }
            $time_new = date("Y-m-d H:i:s", $time_new);
            if ($time_day != 0) {
                $setJob = setjob($panel['name_panel'], "date", $time_new, $datauser['id']);
                if (isset($setJob['status']) && $setJob['status'] === false) {
                    return array(
                        'status' => false,
                        'msg' => isset($setJob['msg']) ? $setJob['msg'] : ''
                    );
                }
            }
            if ($new_limit != 0) {
                $setJob = setjob($panel['name_panel'], "total_data", $data_limit_new / pow(1024, 3), $datauser['id']);
                if (isset($setJob['status']) && $setJob['status'] === false) {
                    return array(
                        'status' => false,
                        'msg' => isset($setJob['msg']) ? $setJob['msg'] : ''
                    );
                }
            }
            return array(
                'status' => true
            );
        } elseif ($panel['type'] == "guard") {
            $limitExpire = guardNormalizeExpire($time_new);
            $serviceIdsSource = isset($data_user['service_ids']) ? $data_user['service_ids'] : ($panel['guard_service_ids'] ?? null);
            $serviceResult = guardResolveServiceIds($panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $serviceResult['msg']
                );
            }
            $data = array(
                "limit_usage" => $data_limit_new,
                "limit_expire" => $limitExpire,
                "service_ids" => $serviceResult['service_ids']
            );
        } elseif ($panel['type'] == "rebecca") {
            $data = array(
                'data_limit' => $data_limit_new,
                'expire' => $time_new,
            );
        }
        $extend = $this->Modifyuser($username, $panel['name_panel'], $data);
        if ($extend['status'] == false) {
            return array(
                'status' => false,
                'msg' => $extend['msg']
            );
        }
        return $extend;
    }
    function extra_volume($username_account, $code_panel, $limit_volume_new)
    {
        $panel = $this->loadPanel($code_panel, "code_panel");
        $invoice = select("invoice", "*", "username", $username_account, "select");
        if ($panel == false) {
            return array(
                'status' => false,
                'msg' => 'data not found'
            );
        }
        $notif_value = json_decode($invoice['notifctions'], true);
        $notifctions = json_encode(array(
            'volume' => false,
            'time' => $notif_value['time'],
        ));
        update("invoice", "notifctions", $notifctions, 'id_invoice', $invoice['id_invoice']);
        $user_info = $this->DataUser($panel['name_panel'], $username_account);
        if ($user_info['status'] == "Unsuccessful") {
            return array(
                'status' => false,
                'msg' => $user_info['msg']
            );
        }
        $old_limit_volume = $user_info['data_limit'];
        $new_limit = $limit_volume_new == 0 ? 0 : ($limit_volume_new * pow(1024, 3)) + $old_limit_volume;
        $inbound_id = isset($panel['inboundid']) ? $panel['inboundid'] : 1;
        $inbounds = is_string($panel['inbounds']) ? json_decode($panel['inbounds']) : "{}";
        if ($panel['type'] != "WGDashboard") {
            update("invoice", 'user_info', null, "username", $username_account);
        }
        update("invoice", 'uuid', null, "username", $username_account);
        update("invoice", 'Status', "active", "username", $username_account);
        if ($panel['type'] == "remnawave") {
            global $pdo;
            $local = remnawave_find_user($username_account);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $res = $mgr->addVolume($local['panel_user_id'], (int) $new_limit, $pdo);
            return array('status' => !empty($res['ok']));
        }
        if ($this->isPasarGuardDialect($panel)) {
            $configuredGroups = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingGroups = (!empty($user_info['group_ids']) && is_array($user_info['group_ids'])) ? $user_info['group_ids'] : [];
            $mergedGroups = array_values(array_unique(array_merge($existingGroups, is_array($configuredGroups) ? $configuredGroups : [])));
            $targetGroups = !empty($mergedGroups) ? $mergedGroups : (!empty($configuredGroups) ? $configuredGroups : null);

            $data = array(
                'data_limit' => $new_limit,
            );
            if ($targetGroups !== null) {
                $data['group_ids'] = $targetGroups;
            }
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxy_settings'] = json_decode($invoice['uuid']);
            }
        } elseif ($panel['type'] == "marzban") {
            $configuredInbounds = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingInbounds = (!empty($user_info['inbounds']) && is_array($user_info['inbounds'])) ? $user_info['inbounds'] : [];
            $mergedInbounds = is_array($configuredInbounds) ? $configuredInbounds : [];
            if (is_array($existingInbounds)) {
                foreach ($existingInbounds as $protocol => $tags) {
                    if (is_array($tags)) {
                        $currentTags = isset($mergedInbounds[$protocol]) && is_array($mergedInbounds[$protocol]) ? $mergedInbounds[$protocol] : [];
                        $mergedInbounds[$protocol] = array_values(array_unique(array_merge($currentTags, $tags)));
                    }
                }
            }
            $targetInbounds = !empty($mergedInbounds) ? $mergedInbounds : $inbounds;
            $data = array(
                'data_limit' => $new_limit,
                'inbounds' => $targetInbounds,
            );
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxies'] = json_decode($invoice['uuid'], true);
            }
        } elseif ($panel['type'] == "x-ui_single") {
            $data = array(
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "totalGB" => $new_limit,
                            )
                        ),
                    )
                ),
            );
        } elseif ($panel['type'] == "WGDashboard") {
            $allowResponse = allowAccessPeers($panel['name_panel'], $username_account);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = get_userwg($username_account, $panel['name_panel']);
            if (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($datauser['msg']) ? $datauser['msg'] : ''
                );
            }
            $count = 0;
            foreach ($datauser['jobs'] as $jobsvolume) {
                if ($jobsvolume['Field'] == "total_data") {
                    break;
                }
                $count += 1;
            }
            if (isset($datauser['jobs'][$count])) {
                $datam = array(
                    "Job" => $datauser['jobs'][$count],
                );
                $deleteJob = deletejob($panel['name_panel'], $datam);
                if (isset($deleteJob['status']) && $deleteJob['status'] === false) {
                    return array(
                        'status' => false,
                        'msg' => isset($deleteJob['msg']) ? $deleteJob['msg'] : ''
                    );
                }
            } else {
                $resetResult = $this->ResetUserDataUsage($username_account, $panel['name_panel']);
                if (isset($resetResult['status']) && $resetResult['status'] === false) {
                    return array(
                        'status' => false,
                        'msg' => isset($resetResult['msg']) ? $resetResult['msg'] : ''
                    );
                }
            }
            $log = setjob($panel['name_panel'], "total_data", $new_limit / pow(1024, 3), $datauser['id']);
            if (isset($log['status']) && $log['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($log['msg']) ? $log['msg'] : ''
                );
            }
            return array(
                'status' => true,
                'data' => $log
            );
        } elseif ($panel['type'] == "guard") {
            $serviceIdsSource = isset($user_info['service_ids']) ? $user_info['service_ids'] : ($panel['guard_service_ids'] ?? null);
            $serviceResult = guardResolveServiceIds($panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $serviceResult['msg']
                );
            }
            $data = array(
                "limit_usage" => $new_limit,
                "service_ids" => $serviceResult['service_ids']
            );
        } elseif ($panel['type'] == "rebecca") {
            $data = array(
                'data_limit' => $new_limit,
            );
        }
        $extra_volume = $this->Modifyuser($username_account, $panel['name_panel'], $data);
        if ($extra_volume['status'] == false) {
            return array(
                'status' => false,
                'msg' => $extra_volume['msg']
            );
        }
        return $extra_volume;
    }
    function extra_time($username_account, $code_panel, $limit_time_new)
    {
        $panel = $this->loadPanel($code_panel, "code_panel");
        $invoice = select("invoice", "*", "username", $username_account, "select");
        if ($panel == false) {
            return array(
                'status' => false,
                'msg' => 'data not found'
            );
        }
        $notif_value = json_decode($invoice['notifctions'], true);
        $notifctions = json_encode(array(
            'volume' => $notif_value['volume'],
            'time' => false,
        ));
        update("invoice", "notifctions", $notifctions, 'id_invoice', $invoice['id_invoice']);
        $user_info = $this->DataUser($panel['name_panel'], $username_account);
        if ($user_info['status'] == "Unsuccessful") {
            return array(
                'status' => false,
                'msg' => $user_info['msg']
            );
        }
        $old_limit_time = $user_info['expire'];
        $old_limit_time = time() - $old_limit_time > 0 ? time() : $old_limit_time;
        $new_limit = $limit_time_new == 0 ? 0 : $limit_time_new * 86400 + $old_limit_time;
        $inbound_id = isset($panel['inboundid']) ? $panel['inboundid'] : 1;
        $inbounds = is_string($panel['inbounds']) ? json_decode($panel['inbounds']) : "{}";
        if ($panel['type'] != "WGDashboard") {
            update("invoice", 'user_info', null, "username", $username_account);
        }
        update("invoice", 'uuid', null, "username", $username_account);
        update("invoice", 'Status', "active", "username", $username_account);
        if ($panel['type'] == "remnawave") {
            global $pdo;
            $local = remnawave_find_user($username_account);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $res = $mgr->extendUser($local['panel_user_id'], remnawave_iso_from_timestamp($new_limit), $pdo);
            return array('status' => !empty($res['ok']));
        }
        if ($this->isPasarGuardDialect($panel)) {
            $configuredGroups = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingGroups = (!empty($user_info['group_ids']) && is_array($user_info['group_ids'])) ? $user_info['group_ids'] : [];
            $mergedGroups = array_values(array_unique(array_merge($existingGroups, is_array($configuredGroups) ? $configuredGroups : [])));
            $targetGroups = !empty($mergedGroups) ? $mergedGroups : (!empty($configuredGroups) ? $configuredGroups : null);

            $data = array(
                'expire' => ($new_limit == 0 ? 0 : date('c', $new_limit)),
            );
            if ($targetGroups !== null) {
                $data['group_ids'] = $targetGroups;
            }
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxy_settings'] = json_decode($invoice['uuid']);
            }
        } elseif ($panel['type'] == "marzban") {
            $configuredInbounds = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingInbounds = (!empty($user_info['inbounds']) && is_array($user_info['inbounds'])) ? $user_info['inbounds'] : [];
            $mergedInbounds = is_array($configuredInbounds) ? $configuredInbounds : [];
            if (is_array($existingInbounds)) {
                foreach ($existingInbounds as $protocol => $tags) {
                    if (is_array($tags)) {
                        $currentTags = isset($mergedInbounds[$protocol]) && is_array($mergedInbounds[$protocol]) ? $mergedInbounds[$protocol] : [];
                        $mergedInbounds[$protocol] = array_values(array_unique(array_merge($currentTags, $tags)));
                    }
                }
            }
            $targetInbounds = !empty($mergedInbounds) ? $mergedInbounds : $inbounds;
            $data = array(
                'expire' => $new_limit,
                'inbounds' => $targetInbounds,
            );
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxies'] = json_decode($invoice['uuid'], true);
            }
        } elseif ($panel['type'] == "x-ui_single") {
            $new_limit = $new_limit * 1000;
            $data = array(
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "expiryTime" => $new_limit,
                            )
                        ),
                    )
                ),
            );
        } elseif ($panel['type'] == "WGDashboard") {
            $allowResponse = allowAccessPeers($panel['name_panel'], $username_account);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = get_userwg($username_account, $panel['name_panel']);
            if (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($datauser['msg']) ? $datauser['msg'] : ''
                );
            }
            $count = 0;
            foreach ($datauser['jobs'] as $jobsvolume) {
                if ($jobsvolume['Field'] == "date") {
                    break;
                }
                $count += 1;
            }
            if (isset($datauser['jobs'][$count])) {
                $datam = array(
                    "Job" => $datauser['jobs'][$count],
                );
                $deleteJob = deletejob($panel['name_panel'], $datam);
                if (isset($deleteJob['status']) && $deleteJob['status'] === false) {
                    return array(
                        'status' => false,
                        'msg' => isset($deleteJob['msg']) ? $deleteJob['msg'] : ''
                    );
                }
            }
            $log = setjob($panel['name_panel'], "date", date('Y-m-d H:i:s', $new_limit), $datauser['id']);
            if (isset($log['status']) && $log['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($log['msg']) ? $log['msg'] : ''
                );
            }
            return array(
                'status' => true,
                'data' => $log
            );
        } elseif ($panel['type'] == "guard") {
            $serviceIdsSource = isset($user_info['service_ids']) ? $user_info['service_ids'] : ($panel['guard_service_ids'] ?? null);
            $serviceResult = guardResolveServiceIds($panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $serviceResult['msg']
                );
            }
            $data = array(
                "limit_expire" => guardNormalizeExpire($new_limit),
                "service_ids" => $serviceResult['service_ids']
            );
        } elseif ($panel['type'] == "rebecca") {
            $data = array(
                'expire' => $new_limit,
            );
        }
        $extra_time = $this->Modifyuser($username_account, $panel['name_panel'], $data);
        if ($extra_time['status'] == false) {
            return array(
                'status' => false,
                'msg' => $extra_time['msg']
            );
        }
        return $extra_time;
    }
}


