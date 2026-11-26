<?php

namespace Faoxima\Services\Panels;


/**
 * Service lifecycle on the provider: subscription revoke, removal, modification, status change and usage reset.
 * Part of ManagePanel; the methods keep their original names, signatures and bodies.
 */
trait PanelLifecycle
{
    function Revoke_sub($name_panel, $username)
    {
        $Output = array();
        $ManagePanel = new ManagePanel();
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if ($Get_Data_Panel['type'] == "marzban") {
            $revoke_sub = \Faoxima\Services\Panels\Drivers\Marzban::revoke_sub($username, $name_panel);
            if (!empty($revoke_sub['error'])) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['error']
                );
            } elseif (isset($revoke_sub['detail']) && $revoke_sub['detail']) {
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
            $revoke_sub = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardRevokeSub($username, $name_panel);
            $revokeHttp = (int) ($revoke_sub['status'] ?? 0);
            $revokeBody = (!empty($revoke_sub['body']) && is_string($revoke_sub['body'])) ? json_decode($revoke_sub['body'], true) : null;
            if (isset($revoke_sub['detail']) && $revoke_sub['detail']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['detail']
                );
            } elseif (!empty($revoke_sub['error'])) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['error']
                );
            } elseif ($revokeHttp < 200 || $revokeHttp >= 300 || (!empty($revoke_sub['body']) && !is_array($revokeBody))) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => (is_array($revokeBody) && isset($revokeBody['detail']) && is_string($revokeBody['detail'])) ? $revokeBody['detail'] : ('HTTP ' . $revokeHttp)
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
            $revoke_sub = \Faoxima\Services\Panels\Drivers\Guard::guardRevokeSubscriptions($name_panel, array($username));
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
                                "id" => \Faoxima\Core\Support\Formatting::generateUUID(),
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
                    'configs' => [\Faoxima\Services\Delivery\ServiceDelivery::outputlunk($Get_Data_Panel['linksubx'] . "/{$subId}")],
                    'subscription_url' => $Get_Data_Panel['linksubx'] . "/{$subId}",
                );
            }
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            $revoke = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_revoke($name_panel, $username);
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
            $revoke = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaRevokeSub($name_panel, $username);
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
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            $rotate = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelRotate($Get_Data_Panel, $username);
            if (($rotate['status'] ?? '') !== 'successful') {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $rotate['msg'] ?? 'revoke failed'
                );
            } else {
                $Data_User = $ManagePanel->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? ($rotate['subscription_url'] ?? null)
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
            $UsernameData = \Faoxima\Services\Panels\Drivers\Marzban::removeuser($Get_Data_Panel['name_panel'], $username);
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
            $UsernameData = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardRemoveUser($Get_Data_Panel['name_panel'], $username);
            if (!empty($UsernameData['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            }
            $httpStatus = isset($UsernameData['status']) ? (int)$UsernameData['status'] : 0;
            if (in_array($httpStatus, [200, 201, 202], true) && !empty($UsernameData['body']) && is_string($UsernameData['body']) && !is_array(json_decode($UsernameData['body'], true)) && trim($UsernameData['body']) !== 'null') {
                $httpStatus = 0;
            }
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
            $UsernameData = \Faoxima\Services\Panels\Drivers\XuiSingle::removeClient($Get_Data_Panel['name_panel'], $username);
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
            \Faoxima\Core\Database\Db::update("manualsell", "status", "delete", "username", $username);
            $Output = array(
                'status' => 'successful',
                'username' => $username,
            );
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $UsernameData = \Faoxima\Services\Panels\Drivers\WgDashboard::remove_userwg($Get_Data_Panel['name_panel'], $username);
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
            $UsernameData = \Faoxima\Services\Panels\Drivers\Guard::guardDeleteSubscriptions($Get_Data_Panel['name_panel'], array($username));
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
            $res = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_remove_user($Get_Data_Panel['name_panel'], $username);
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
            $removeResponse = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaRemoveUser($Get_Data_Panel['name_panel'], $username);
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
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            $removeResponse = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelRemoveUser($Get_Data_Panel, $username);
            if (($removeResponse['status'] ?? '') !== 'successful') {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $removeResponse['msg'] ?? 'remove failed'
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
                $result = \Faoxima\Services\Panels\Drivers\Marzban::getuser($username, $name_panel);
                if (!empty($result['body'])) {
                    $result = json_decode($result['body'], true);
                    if (is_array($result) && isset($result['proxy_settings'])) {
                        $config['proxy_settings'] = $result['proxy_settings'];
                    }
                }
            }
            $modify = \Faoxima\Services\Panels\Drivers\Marzban::Modifyuser($name_panel, $username, $config);
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
            $modify = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardModifyUser($name_panel, $username, $config);
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
            if (\Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($Get_Data_Panel)) {
                $fullClient = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_get_full_client($Get_Data_Panel, $username);
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
                $modify = \Faoxima\Services\Panels\Drivers\XuiSingle::updateClient($Get_Data_Panel['name_panel'], $baseClient['uuid'] ?? ($baseClient['id'] ?? ''), $configs);
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
            $clients = \Faoxima\Services\Panels\Drivers\XuiSingle::get_clinets($username, $name_panel);
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
            $modify = \Faoxima\Services\Panels\Drivers\XuiSingle::updateClient($Get_Data_Panel['name_panel'], $clients['uuid'], $configs);
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
            $data_user = \Faoxima\Services\Panels\Drivers\WgDashboard::get_userwg($username, $name_panel);
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
            $modify = \Faoxima\Services\Panels\Drivers\WgDashboard::updatepear($Get_Data_Panel['name_panel'], $configs);
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
                $config['limit_expire'] = \Faoxima\Services\Panels\Drivers\Guard::guardNormalizeExpire($config['limit_expire']);
            }
            $modify = \Faoxima\Services\Panels\Drivers\Guard::guardUpdateSubscription($Get_Data_Panel['name_panel'], $username, $config);
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
            $modify = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaUpdateUser($Get_Data_Panel['name_panel'], $username, $config);
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
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            return \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelModifyUser($Get_Data_Panel, $username, is_array($config) ? $config : array());
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
            $toggle = \Faoxima\Services\Panels\Drivers\Guard::guardToggleSubscriptions($Get_Data_Panel['name_panel'], array($username), $action);
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
            $local = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_find_user($username);
            $isActive = is_array($local) ? ((string) ($local['status'] ?? 'active') === 'active') : true;
            $res = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_change_status($Get_Data_Panel['name_panel'], $username, !$isActive);
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
            $toggle = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaChangeStatus($Get_Data_Panel['name_panel'], $username, $wantEnable);
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
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            return \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelSetStatus($Get_Data_Panel, $username, $DataUserOut);
        }

        return $Output;
    }


    private static function rxResetResponseUncertain($reset)
    {
        if (!is_array($reset)) {
            return true;
        }
        if (!empty($reset['uncertain'])) {
            return true;
        }
        $status = (isset($reset['status']) && is_numeric($reset['status'])) ? (int) $reset['status'] : null;
        return $status === 0 || $status === 202 || $status === 408
            || $status === 409 || $status === 425 || $status === 429
            || ($status >= 500 && $status <= 599)
            || ($status === null && !is_bool($reset['status'] ?? null));
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
            $reset = \Faoxima\Services\Panels\Drivers\Marzban::ResetUserDataUsage($username, $panel['name_panel']);
            if (self::rxResetResponseUncertain($reset)) {
                return array(
                    'status' => false,
                    'uncertain' => true,
                    'msg' => 'error  : ' . (is_scalar($reset['error'] ?? null) ? (string) $reset['error'] : 'no response after reset request')
                );
            }
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'rejected' => in_array((int) $reset['status'], [400, 401, 403, 404, 405, 422], true),
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode((string) ($reset['body'] ?? ''), true);
            if (!is_array($reset) || $reset === []) {
                return ['status' => false, 'uncertain' => true, 'msg' => 'invalid panel response'];
            }
            if (!empty($reset['detail'])) {
                return array(
                    'status' => false,
                    'rejected' => true,
                    'msg' => $reset['detail']
                );
            }
            return array(
                'status' => true,
                'msg' => 'successful'
            );
        } elseif ($panel['type'] == "pasarguard") {
            $reset = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardResetUserDataUsage($username, $panel['name_panel']);
            if (self::rxResetResponseUncertain($reset)) {
                return array(
                    'status' => false,
                    'uncertain' => true,
                    'msg' => 'error  : ' . (is_scalar($reset['error'] ?? null) ? (string) $reset['error'] : 'no response after reset request')
                );
            }
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'rejected' => in_array((int) $reset['status'], [400, 401, 403, 404, 405, 422], true),
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $resetRaw = (string) ($reset['body'] ?? '');
            $reset = json_decode($resetRaw, true);
            if (!is_array($reset) || $reset === []) {
                return array(
                    'status' => false,
                    'uncertain' => true,
                    'msg' => 'invalid panel response'
                );
            }
            if (!empty($reset['detail'])) {
                return array(
                    'status' => false,
                    'rejected' => true,
                    'msg' => $reset['detail']
                );
            }
            return array(
                'status' => true,
                'msg' => 'successful'
            );
        } elseif ($panel['type'] == 'x-ui_single') {
            $reset = \Faoxima\Services\Panels\Drivers\XuiSingle::ResetUserDataUsagex_uisin($username, $panel['name_panel']);
            if (self::rxResetResponseUncertain($reset)) {
                return array(
                    'status' => false,
                    'uncertain' => true,
                    'msg' => 'error  : ' . (is_scalar($reset['error'] ?? null) ? (string) $reset['error'] : 'no response after reset request')
                );
            }
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'rejected' => in_array((int) $reset['status'], [400, 401, 403, 404, 405, 422], true),
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode((string) ($reset['body'] ?? ''), true);
            if (!is_array($reset) || !is_bool($reset['success'] ?? null)) {
                return ['status' => false, 'uncertain' => true, 'msg' => 'invalid panel response'];
            }
            if (!$reset['success']) {
                return array(
                    'status' => false,
                    'rejected' => true,
                    'msg' => 'error :' . ($reset['msg'] ?? '')
                );
            }
            return array(
                'status' => true,
                'data' => $reset
            );
        } elseif ($panel['type'] == "WGDashboard") {
            $allowResponse = \Faoxima\Services\Panels\Drivers\WgDashboard::allowAccessPeers($panel['name_panel'], $username);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = \Faoxima\Services\Panels\Drivers\WgDashboard::get_userwg($username, $panel['name_panel']);
            if (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($datauser['msg']) ? $datauser['msg'] : ''
                );
            }
            $reset = \Faoxima\Services\Panels\Drivers\WgDashboard::ResetUserDataUsagewg($datauser['id'], $panel['name_panel']);
            if (self::rxResetResponseUncertain($reset)) {
                return array(
                    'status' => false,
                    'uncertain' => true,
                    'msg' => 'error  : ' . (is_scalar($reset['error'] ?? null) ? (string) $reset['error'] : 'no response after reset request')
                );
            }
            if (isset($reset['status']) && $reset['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($reset['msg']) ? $reset['msg'] : ''
                );
            }
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'rejected' => in_array((int) $reset['status'], [400, 401, 403, 404, 405, 422], true),
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
            $reset = \Faoxima\Services\Panels\Drivers\Guard::guardResetSubscriptions($panel['name_panel'], array($username));
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
            $local = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_find_user($username);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $res = $mgr->resetTraffic($local['panel_user_id'], $pdo);
            if (empty($res['ok'])) {
                return array('status' => false, 'msg' => 'remnawave reset traffic failed');
            }
            return array('status' => true);
        } elseif ($panel['type'] == "rebecca") {
            $reset = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaResetUsage($panel['name_panel'], $username);
            if ($reset['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $reset['msg']
                );
            }
            return array(
                'status' => true
            );
        } elseif ($panel['type'] == "jovadpanel") {
            $reset = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelResetUsage($panel, $username);
            if (($reset['status'] ?? false) !== true) {
                return array(
                    'status' => false,
                    'msg' => $reset['msg'] ?? 'reset failed'
                );
            }
            return array(
                'status' => true,
                'data' => $reset['data'] ?? null
            );
        }
    }
}
