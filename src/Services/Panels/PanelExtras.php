<?php

namespace Faoxima\Services\Panels;

/**
 * Additional traffic and additional time purchases applied to an existing service.
 * Part of ManagePanel; the methods keep their original names, signatures and bodies.
 */
trait PanelExtras
{
    function extra_volume($username_account, $code_panel, $limit_volume_new, $rxTarget = null)
    {
        $panel = $this->loadPanel($code_panel, "code_panel");
        $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $username_account, "select");
        if ($panel == false) {
            return array(
                'status' => false,
                'no_mutation' => true,
                'msg' => 'data not found'
            );
        }
        $user_info = $this->DataUser($panel['name_panel'], $username_account);
        if ($user_info['status'] == "Unsuccessful") {
            return array(
                'status' => false,
                'no_mutation' => true,
                'msg' => $user_info['msg']
            );
        }
        $old_limit_volume = $user_info['data_limit'];
        $new_limit = $limit_volume_new == 0 ? 0 : ($limit_volume_new * pow(1024, 3)) + $old_limit_volume;
        if (is_array($rxTarget) && array_key_exists('data_limit', $rxTarget)) {
            $new_limit = (int) $rxTarget['data_limit'];
        }
        $inbound_id = isset($panel['inboundid']) ? $panel['inboundid'] : 1;
        $inbounds = is_string($panel['inbounds']) ? json_decode($panel['inbounds']) : "{}";
        if ($panel['type'] != "WGDashboard") {
            \Faoxima\Core\Database\Db::update("invoice", 'user_info', null, "username", $username_account);
        }
        \Faoxima\Core\Database\Db::update("invoice", 'uuid', null, "username", $username_account);
        \Faoxima\Core\Database\Db::update("invoice", 'Status', "active", "username", $username_account);
        if ($panel['type'] == "remnawave") {
            global $pdo;
            $local = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_find_user($username_account);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $res = $mgr->addVolume($local['panel_user_id'], (int) $new_limit, $pdo);
            $rxSuccess = !empty($res['ok']);
            if ($rxSuccess) {
                \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('volume_cycle'), array('volume', 'volume_report'));
            }
            return array('status' => $rxSuccess);
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
            $allowResponse = \Faoxima\Services\Panels\Drivers\WgDashboard::allowAccessPeers($panel['name_panel'], $username_account);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = \Faoxima\Services\Panels\Drivers\WgDashboard::get_userwg($username_account, $panel['name_panel']);
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
                $deleteJob = \Faoxima\Services\Panels\Drivers\WgDashboard::deletejob($panel['name_panel'], $datam);
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
            $log = \Faoxima\Services\Panels\Drivers\WgDashboard::setjob($panel['name_panel'], "total_data", $new_limit / pow(1024, 3), $datauser['id']);
            if (isset($log['status']) && $log['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($log['msg']) ? $log['msg'] : ''
                );
            }
            \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('volume_cycle'), array('volume', 'volume_report'));
            return array(
                'status' => true,
                'data' => $log
            );
        } elseif ($panel['type'] == "guard") {
            $serviceIdsSource = isset($user_info['service_ids']) ? $user_info['service_ids'] : ($panel['guard_service_ids'] ?? null);
            $serviceResult = \Faoxima\Services\Panels\Drivers\Guard::guardResolveServiceIds($panel['name_panel'], $serviceIdsSource);
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
        } elseif ($panel['type'] == "jovadpanel") {
            $extra_volume = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelAddVolume($panel, (string) $username_account, (int) $new_limit);
            $rxSuccess = !empty($extra_volume['status']);
            if ($rxSuccess) {
                \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('volume_cycle'), array('volume', 'volume_report'));
            }
            return array(
                'status' => $rxSuccess,
                'data' => $extra_volume['data'] ?? null,
                'msg' => $extra_volume['msg'] ?? null
            );
        }
        $extra_volume = $this->Modifyuser($username_account, $panel['name_panel'], $data);
        if (empty($extra_volume['status'])) {
            return array(
                'status' => false,
                'msg' => $extra_volume['msg'] ?? null
            );
        }
        \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('volume_cycle'), array('volume', 'volume_report'));
        return $extra_volume;
    }


    function extra_time($username_account, $code_panel, $limit_time_new, $rxTarget = null)
    {
        $panel = $this->loadPanel($code_panel, "code_panel");
        $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $username_account, "select");
        if ($panel == false) {
            return array(
                'status' => false,
                'no_mutation' => true,
                'msg' => 'data not found'
            );
        }
        $user_info = $this->DataUser($panel['name_panel'], $username_account);
        if ($user_info['status'] == "Unsuccessful") {
            return array(
                'status' => false,
                'no_mutation' => true,
                'msg' => $user_info['msg']
            );
        }
        $old_limit_time = $user_info['expire'];
        $old_limit_time = time() - $old_limit_time > 0 ? time() : $old_limit_time;
        $new_limit = $limit_time_new == 0 ? 0 : $limit_time_new * 86400 + $old_limit_time;
        if (is_array($rxTarget) && array_key_exists('expire', $rxTarget)) {
            $new_limit = (int) $rxTarget['expire'];
        }
        $inbound_id = isset($panel['inboundid']) ? $panel['inboundid'] : 1;
        $inbounds = is_string($panel['inbounds']) ? json_decode($panel['inbounds']) : "{}";
        if ($panel['type'] != "WGDashboard") {
            \Faoxima\Core\Database\Db::update("invoice", 'user_info', null, "username", $username_account);
        }
        \Faoxima\Core\Database\Db::update("invoice", 'uuid', null, "username", $username_account);
        \Faoxima\Core\Database\Db::update("invoice", 'Status', "active", "username", $username_account);
        if ($panel['type'] == "remnawave") {
            global $pdo;
            $local = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_find_user($username_account);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $res = $mgr->extendUser($local['panel_user_id'], \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_iso_from_timestamp($new_limit), $pdo);
            $rxSuccess = !empty($res['ok']);
            if ($rxSuccess) {
                \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('time_cycle'), array('time', 'time_report'));
            }
            return array('status' => $rxSuccess);
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
            $allowResponse = \Faoxima\Services\Panels\Drivers\WgDashboard::allowAccessPeers($panel['name_panel'], $username_account);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = \Faoxima\Services\Panels\Drivers\WgDashboard::get_userwg($username_account, $panel['name_panel']);
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
                $deleteJob = \Faoxima\Services\Panels\Drivers\WgDashboard::deletejob($panel['name_panel'], $datam);
                if (isset($deleteJob['status']) && $deleteJob['status'] === false) {
                    return array(
                        'status' => false,
                        'msg' => isset($deleteJob['msg']) ? $deleteJob['msg'] : ''
                    );
                }
            }
            $log = \Faoxima\Services\Panels\Drivers\WgDashboard::setjob($panel['name_panel'], "date", date('Y-m-d H:i:s', $new_limit), $datauser['id']);
            if (isset($log['status']) && $log['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($log['msg']) ? $log['msg'] : ''
                );
            }
            \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('time_cycle'), array('time', 'time_report'));
            return array(
                'status' => true,
                'data' => $log
            );
        } elseif ($panel['type'] == "guard") {
            $serviceIdsSource = isset($user_info['service_ids']) ? $user_info['service_ids'] : ($panel['guard_service_ids'] ?? null);
            $serviceResult = \Faoxima\Services\Panels\Drivers\Guard::guardResolveServiceIds($panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $serviceResult['msg']
                );
            }
            $data = array(
                "limit_expire" => \Faoxima\Services\Panels\Drivers\Guard::guardNormalizeExpire($new_limit),
                "service_ids" => $serviceResult['service_ids']
            );
        } elseif ($panel['type'] == "rebecca") {
            $data = array(
                'expire' => $new_limit,
            );
        } elseif ($panel['type'] == "jovadpanel") {
            $extra_time = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelAddTime($panel, (string) $username_account, (int) $new_limit);
            $rxSuccess = !empty($extra_time['status']);
            if ($rxSuccess) {
                \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('time_cycle'), array('time', 'time_report'));
            }
            return array(
                'status' => $rxSuccess,
                'data' => $extra_time['data'] ?? null,
                'msg' => $extra_time['msg'] ?? null
            );
        }
        $extra_time = $this->Modifyuser($username_account, $panel['name_panel'], $data);
        if (empty($extra_time['status'])) {
            return array(
                'status' => false,
                'msg' => $extra_time['msg'] ?? null
            );
        }
        \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('time_cycle'), array('time', 'time_report'));
        return $extra_time;
    }
}
