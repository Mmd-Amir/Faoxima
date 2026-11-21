<?php

namespace Faoxima\Services\Panels;

use PDO;

/**
 * Account creation on the provider, including rename-on-duplicate handling and retry.
 * Part of ManagePanel; the methods keep their original names, signatures and bodies.
 */
trait PanelProvisioning
{
    private function isDuplicateUsernameFailure($output): bool
    {
        if (!is_array($output) || !empty($output['username'])) {
            return false;
        }
        if ((int) ($output['http_code'] ?? 0) === 409) {
            return true;
        }
        $msg = $output['msg'] ?? '';
        if (is_scalar($msg) && preg_match('/^\s*409\s*$/', (string) $msg)) {
            return true;
        }
        $text = is_scalar($msg) ? (string) $msg : (string) json_encode($msg, JSON_UNESCAPED_UNICODE);
        if (isset($output['detail'])) {
            $text .= ' ' . (is_scalar($output['detail']) ? (string) $output['detail'] : (string) json_encode($output['detail'], JSON_UNESCAPED_UNICODE));
        }
        return (bool) preg_match('/already\s+exist|duplicate|\bexists\b|تکراری/iu', $text);
    }



    private function supportsUsernameRename($name_panel): bool
    {
        $panel = $this->loadPanel((string) $name_panel, 'name_panel');
        return is_array($panel) && in_array((string) ($panel['type'] ?? ''), ['marzban', 'pasarguard', 'x-ui_single', 'guard', 'remnawave', 'rebecca'], true);
    }



    private function panelResponseDetail($response)
    {
        if (!is_array($response) || !isset($response['body']) || !is_string($response['body']) || $response['body'] === '') {
            return null;
        }
        $decoded = json_decode($response['body'], true);
        if (is_array($decoded)) {
            return $decoded['detail'] ?? ($decoded['message'] ?? ($decoded['msg'] ?? null));
        }
        return function_exists('mb_substr') ? mb_substr(strip_tags($response['body']), 0, 300, 'UTF-8') : substr(strip_tags($response['body']), 0, 300);
    }



    function createUser($name_panel, $code_product, $usernameC, array $Data_Config, $allowRename = false)
    {
        $requested = (string) $usernameC;
        $Output = $this->createUserOnce($name_panel, $code_product, $requested, $Data_Config, $requested);
        if (!$allowRename || !$this->isDuplicateUsernameFailure($Output) || !$this->supportsUsernameRename($name_panel)) {
            return $Output;
        }
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $candidate = \Faoxima\Services\Panels\PanelMessages::rx_panel_username_variant($requested);
            $Output = $this->createUserOnce($name_panel, $code_product, $candidate, $Data_Config, $requested);
            if (!$this->isDuplicateUsernameFailure($Output)) {
                break;
            }
        }
        if (is_array($Output) && !empty($Output['username'])) {
            $Output['renamed_from'] = $requested;
        }
        return $Output;
    }



    private function createUserOnce($name_panel, $code_product, $usernameC, array $Data_Config, $invoiceUsername)
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
            $inoice = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $invoiceUsername, "select");
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

            $ConnectToPanel = \Faoxima\Services\Panels\Drivers\Marzban::adduser($Get_Data_Panel['name_panel'], $data_limit, $usernameC, $expire, $note, $Get_Data_Product['data_limit_reset'], $Get_Data_Product['name_product']);
            if (!empty($ConnectToPanel['status']) && (int)$ConnectToPanel['status'] !== 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['status'],
                    'detail' => $this->panelResponseDetail($ConnectToPanel)
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
                    $out_put_link = \Faoxima\Services\Delivery\ServiceDelivery::outputlunk($data_Output['subscription_url']);
                    if (\Faoxima\Core\Support\Formatting::isBase64($out_put_link)) {
                        $data_Output['links'] = base64_decode(\Faoxima\Services\Delivery\ServiceDelivery::outputlunk($data_Output['subscription_url']));
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
            $ConnectToPanel = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardAddUser($Get_Data_Panel['name_panel'], $data_limit, $usernameC, $expire, $note, $Get_Data_Product['data_limit_reset'], $Get_Data_Product['name_product']);
            if (!empty($ConnectToPanel['status']) && (int)$ConnectToPanel['status'] !== 201) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['status'],
                    'detail' => $this->panelResponseDetail($ConnectToPanel)
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
            $data_Output = \Faoxima\Services\Panels\Drivers\XuiSingle::addClient($Get_Data_Panel['name_panel'], $usernameC, $expire, $data_limit, \Faoxima\Core\Support\Formatting::generateUUID(), "", $subId, $inbounds, $Get_Data_Product['name_product'], $note, $productIpLimit, $productHwidLimit);
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
                    if (!(isset($Get_Data_Product['inbounds']) and $Get_Data_Product['inbounds'] != null)) {
                        \Faoxima\Services\Panels\XuiInboundSync::xsync_record_creation($Get_Data_Panel['name_panel'], $usernameC, \Faoxima\Services\Panels\Drivers\XuiSingle::xui_parse_inbound_ids($inbounds));
                    }
                    $subscriptionUrl = rtrim($Get_Data_Panel['linksubx'], '/') . "/{$subId}";
                    $singleConfig = \Faoxima\Services\Panels\Drivers\XuiSingle::get_single_link_after_create(
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
                    $subscriptionLinks = \Faoxima\Services\Panels\Drivers\XuiSingle::get_subscription_links_with_retry($subscriptionUrl);
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
            $serviceResult = \Faoxima\Services\Panels\Drivers\Guard::guardResolveServiceIds($Get_Data_Panel['name_panel'], $serviceIdsSource);
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
                $guardSubscriptionEntry['limit_expire'] = \Faoxima\Services\Panels\Drivers\Guard::guardNormalizeExpire($expire);
            }
            $payload = array($guardSubscriptionEntry);
            $createResponse = \Faoxima\Services\Panels\Drivers\Guard::guardCreateSubscription($Get_Data_Panel['name_panel'], $payload);
            if ($createResponse['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $createResponse['msg'],
                    'http_code' => $createResponse['http_code'] ?? null
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
            $configman = null;
            $manualClaimedRows = array();
            $pickStock = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = ? AND status = 'active' AND codeproduct = ? ORDER BY RAND() LIMIT 1");
            for ($claimAttempt = 0; $claimAttempt < 5 && $configman === null; $claimAttempt++) {
                $pickStock->execute(array((string) $Get_Data_Panel['code_panel'], (string) $code_product));
                $candidate = $pickStock->fetch(PDO::FETCH_ASSOC);
                $pickStock->closeCursor();
                if (!is_array($candidate) || empty($candidate['id'])) {
                    break;
                }
                if (!empty($candidate['group_id'])) {
                    $claimGroup = $pdo->prepare("UPDATE manualsell SET status = 'selled', username = ? WHERE group_id = ? AND status = 'active'");
                    $claimGroup->execute(array($usernameC, $candidate['group_id']));
                    if ($claimGroup->rowCount() < 1) {
                        continue;
                    }
                    $groupFetch = $pdo->prepare("SELECT * FROM manualsell WHERE group_id = ? AND username = ? AND status = 'selled' ORDER BY id ASC");
                    $groupFetch->execute(array($candidate['group_id'], $usernameC));
                    $groupRows = $groupFetch->fetchAll(PDO::FETCH_ASSOC);
                    if (empty($groupRows)) {
                        continue;
                    }
                    $manualClaimedRows = $groupRows;
                    $configman = $groupRows[0];
                    foreach ($groupRows as $groupRow) {
                        if ((string) $groupRow['id'] === (string) $candidate['id']) {
                            $configman = $groupRow;
                            break;
                        }
                    }
                } else {
                    $claimOne = $pdo->prepare("UPDATE manualsell SET status = 'selled', username = ? WHERE id = ? AND status = 'active'");
                    $claimOne->execute(array($usernameC, $candidate['id']));
                    if ($claimOne->rowCount() !== 1) {
                        continue;
                    }
                    $candidate['status'] = 'selled';
                    $candidate['username'] = $usernameC;
                    $configman = $candidate;
                    $manualClaimedRows = array($candidate);
                }
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('manualsell');
            }
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
                    'name'     => (string)($mRow['namerecord'] ?? ''),
                );
            }
            $Output['manual_items'] = $manualItemsOut;
            $Output['manual_sub_link'] = $manualUnifiedSubOut;
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $data_limit = round($data_limit / (1024 * 1024 * 1024), 2);
            $data_Output = \Faoxima\Services\Panels\Drivers\WgDashboard::addpear($Get_Data_Panel['name_panel'], $usernameC);
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
                $jobResponse = \Faoxima\Services\Panels\Drivers\WgDashboard::setjob($Get_Data_Panel['name_panel'], "total_data", $data_limit, $data_Output['public_key']);
                if (isset($jobResponse['status']) && $jobResponse['status'] === false) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => isset($jobResponse['msg']) ? $jobResponse['msg'] : ''
                    );
                }
            }
            if ($expire != 0) {
                $jobResponse = \Faoxima\Services\Panels\Drivers\WgDashboard::setjob($Get_Data_Panel['name_panel'], "date", date('Y-m-d H:i:s', $expire), $data_Output['public_key']);
                if (isset($jobResponse['status']) && $jobResponse['status'] === false) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => isset($jobResponse['msg']) ? $jobResponse['msg'] : ''
                    );
                }
            }
            \Faoxima\Core\Database\Db::update("invoice", "user_info", json_encode($data_Output), "username", $usernameC);
            if (!$response['status']) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['msg'];
            } else {
                $download_config = \Faoxima\Services\Panels\Drivers\WgDashboard::downloadconfig($Get_Data_Panel['name_panel'], $data_Output['public_key']);
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
            $data_Output = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_adduser($Get_Data_Panel['name_panel'], $data_limit, $usernameC, $expire, $squad, $invoiceId, $remnaHwidLimit);
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
            $createResponse = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaCreateUser($Get_Data_Panel['name_panel'], $usernameC, $data_limit, $expire, $rebeccaServiceValue, $note, $rebeccaIpLimit, $rebeccaOnHoldDuration);
            if ($createResponse['status'] === false) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $createResponse['msg'];
                $Output['http_code'] = $createResponse['http_code'] ?? null;
            } else {
                $createdData = $createResponse['data'];
                $subscriptionUrl = is_array($createdData) ? ($createdData['subscription_url'] ?? '') : '';
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $subscriptionUrl)) {
                    $subscriptionUrl = rtrim((string) ($Get_Data_Panel['url_panel'] ?? ''), '/') . "/" . ltrim($subscriptionUrl, "/");
                }
                $configs = method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaBuildDisplayLinks') && is_array($createdData)
                    ? \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaBuildDisplayLinks($Get_Data_Panel['name_panel'], $createdData, $usernameC)
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
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            $jovadOutput = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelCreateUser(
                $Get_Data_Panel,
                $code_product,
                $usernameC,
                $Data_Config,
                is_array($Get_Data_Product) ? $Get_Data_Product : array(),
                $note
            );
            if (($jovadOutput['status'] ?? '') === 'successful' && !empty($jovadOutput['username'])) {
                $Output['status'] = 'successful';
                $Output['username'] = $jovadOutput['username'];
                $Output['subscription_url'] = $jovadOutput['subscription_url'];
                $Output['configs'] = $jovadOutput['configs'];
            } else {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $jovadOutput['msg'] ?? 'JovadPanel create failed';
                if (!empty($jovadOutput['code'])) {
                    $Output['code'] = $jovadOutput['code'];
                }
            }
        } else {
            $Output['status'] = 'Unsuccessful';
            $Output['msg'] = 'Panel Not Found';
        }
        if (method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'normalizeServiceConfigs')) {
            $rxNormalizeSubUrl = (($Get_Data_Panel['type'] ?? '') === 'jovadpanel') ? null : ($Output['subscription_url'] ?? null);
            if (isset($Output['status']) && $Output['status'] === 'successful') {
                $Output['configs'] = \Faoxima\Services\Delivery\ServiceDelivery::normalizeServiceConfigs($Output['configs'] ?? null, $rxNormalizeSubUrl);
            } else {
                $Output['configs'] = \Faoxima\Services\Delivery\ServiceDelivery::normalizeServiceConfigs($Output['configs'] ?? null);
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
}
