<?php

namespace Faoxima\Services\Panels;

final class PanelFeatures
{
    public static function panel_feature_registry()
    {
        return [
            'extravolume' => ['shopSetting', 'statusextra', 'onextra'],
            'directbuy' => ['shopSetting', 'statusdirectpabuy', 'ondirectbuy'],
            'timeextra' => ['shopSetting', 'statustimeextra', 'ontimeextraa'],
            'disorder' => ['shopSetting', 'statusdisorder', 'ondisorder'],
            'changeservice' => ['shopSetting', 'statuschangeservice', 'onstatus'],
            'showprice' => ['shopSetting', 'statusshowprice', 'onshowprice'],
            'configbtn' => ['shopSetting', 'configshow', 'onconfig'],
            'refund' => ['shopSetting', 'backserviecstatus', 'on'],
            'categorygeneral' => ['setting', 'statuscategorygenral', 'oncategorys'],
            'categorytime' => ['setting', 'statuscategory', 'oncategory'],
        ];
    }

    public static function panel_feature_keys()
    {
        return array_keys(self::panel_feature_registry());
    }

    public static function panel_features_all_off_json()
    {
        $out = [];
        foreach (self::panel_feature_keys() as $featureKey) {
            $out[$featureKey] = "0";
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    public static function panel_features_new_panel_defaults_json()
    {
        $out = [];
        foreach (self::panel_feature_keys() as $featureKey) {
            $out[$featureKey] = "0";
        }
        foreach (['categorygeneral', 'configbtn', 'directbuy'] as $featureKey) {
            $out[$featureKey] = "1";
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    public static function panel_feature_global_value($table, $globalKey)
    {
        if ($table === "setting") {
            $row = \Faoxima\Core\Database\Db::select("setting", "*");
            return is_array($row) && isset($row[$globalKey]) ? (string) $row[$globalKey] : '';
        }
        $row = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", $globalKey, "select");
        return is_array($row) && isset($row['value']) ? (string) $row['value'] : '';
    }

    public static function panel_features_snapshot_json()
    {
        $out = [];
        foreach (self::panel_feature_registry() as $featureKey => $definition) {
            list($table, $globalKey, $onValue) = $definition;
            $out[$featureKey] = (self::panel_feature_global_value($table, $globalKey) === $onValue) ? "1" : "0";
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    public static function panel_feature_resolve_row($panel)
    {
        if (is_array($panel)) {
            return $panel;
        }
        $identifier = trim((string) $panel);
        if ($identifier === '') {
            return null;
        }
        $row = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $identifier, "select");
        if (!is_array($row) || empty($row)) {
            $row = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $identifier, "select");
        }
        return is_array($row) ? $row : null;
    }

    public static function panel_feature_enabled($panel, $key)
    {
        $registry = self::panel_feature_registry();
        if (!isset($registry[$key])) {
            return false;
        }
        $row = self::panel_feature_resolve_row($panel);
        if (is_array($row) && isset($row['shop_features'])) {
            $features = json_decode((string) $row['shop_features'], true);
            if (is_array($features) && array_key_exists($key, $features)) {
                $result = ((string) $features[$key]) === "1";
                return $result;
            }
        }
        list($table, $globalKey, $onValue) = $registry[$key];
        $globalValue = self::panel_feature_global_value($table, $globalKey);
        return $globalValue === $onValue;
    }
}
