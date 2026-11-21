<?php

namespace Faoxima\Api\Management;

use Exception;
use Faoxima\Api\Runtime\EndpointSupport;

final class SettingsManagement
{
    public static function dispatch(string $method, array $data): void
    {
        switch ($data['actions'] ?? '') {
            case 'keyboard_set':
                self::keyboardSet($method, $data);
                return;
            case 'setting_info':
                self::settingInfo($method);
                return;
            case 'save_setting_shop':
                self::saveSettingShop($method, $data);
                return;
            default:
                EndpointSupport::sendJsonResponse(false, "Action Invalid");
                return;
        }
    }

    private static function keyboardSet(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);

        try {
            $keyboard = $data['keyboard'];
            if ($data['keyboard_reset']) {
                $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
                \Faoxima\Core\Database\Db::update("setting", "keyboardmain", $keyboardmain, null, null);
                EndpointSupport::sendJsonResponse(true, "Successful", [], 200);
            }
            if (!is_array($keyboard)) {
                EndpointSupport::sendJsonResponse(false, 'keyboard invalid', [], 200);
            }
            $keyboardmain = ['keyboard' => []];
            $keyboardmain['keyboard'] = $keyboard;
            \Faoxima\Core\Database\Db::update("setting", "keyboardmain", json_encode($keyboardmain), null, null);
            EndpointSupport::sendJsonResponse(true, "Successful", [], 200);
        } catch (Exception $e) {
            error_log("Database error in keyboard: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 200);
        }
    }

    private static function settingInfo(string $method): void
    {
        EndpointSupport::validateMethod('GET', $method);
        try {
            $shopsetting = \Faoxima\Core\Database\Db::select("shopSetting", "*", null, null, "fetchAll");
            $shop_setting = [];
            foreach ($shopsetting as $setting) {
                $shop_setting[$setting['Namevalue']] = $setting['value'];
            }
            $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select");
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'setting_shop' => $shop_setting,
                'setting_General' => $setting
            ]);

        } catch (Exception $e) {
            error_log("Database error in setting: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error ", [], 500);
        }
    }

    private static function saveSettingShop(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        try {
            $shopsetting = \Faoxima\Core\Database\Db::select("shopSetting", "*", null, null, "fetchAll");
            if (empty($data['data']))
                EndpointSupport::sendJsonResponse(false, "data empty ", [], 200);
            foreach ($data['data'] as $setting) {
                if (!empty(($setting['json'])) && $setting['json'])
                    $setting['value'] = json_encode($setting['value']);
                if ($setting['type'] == "shop") {
                    \Faoxima\Core\Database\Db::update("shopSetting", "value", $setting['value'], "Namevalue", $setting['name_value']);
                } else {
                    \Faoxima\Core\Database\Db::update("setting", $setting['name_value'], $setting['value'], null, null);
                }
            }
            $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select");
            EndpointSupport::sendJsonResponse(true, "setting updated successfully", [], 200);
        } catch (Exception $e) {
            error_log("Database error in setting: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error ", [], 500);
        }
    }
}
