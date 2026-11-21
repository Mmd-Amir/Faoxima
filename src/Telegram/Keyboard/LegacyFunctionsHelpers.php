<?php

namespace Faoxima\Telegram\Keyboard;

final class LegacyFunctionsHelpers
{
    public static function KeyboardProduct($location,$query,$pricediscount,$datakeyboard,$statuscustom = false,$backuser = "backuser", $valuetow = null,$customvolume = "customsellvolume", $agentFilter = null, $params = [], $excludeHiddenPanels = false)
{
    global $pdo, $textbotlang, $from_id;
    return \Faoxima\Telegram\Keyboard\CatalogKeyboard::products($pdo, $textbotlang, $from_id, $location, $query, $pricediscount, $datakeyboard, $statuscustom, $backuser, $valuetow, $customvolume, $agentFilter, $params, $excludeHiddenPanels);
}

    public static function KeyboardCategory($location,$agent,$backuser = "backuser")
{
    global $pdo, $textbotlang;
    return \Faoxima\Telegram\Keyboard\CatalogKeyboard::categories($pdo, $textbotlang, $location, $agent, $backuser);
}

    public static function keyboardTimeCategory($name_panel,$agent,$callback_data = "producttime_",$callback_data_back = "backuser",$statuscustomvolume = false,$statusbtnextend = false)
{
    global $pdo, $textbotlang;
    return \Faoxima\Telegram\Keyboard\CatalogKeyboard::timeCategories($pdo, $textbotlang, $name_panel, $agent, $callback_data, $callback_data_back, $statuscustomvolume, $statusbtnextend);
}

    public static function rebeccaKeyboardProtocolLabel($config)
{
    return \Faoxima\Telegram\Keyboard\ConfigurationKeyboard::protocolLabel($config);
}

    public static function keyboard_config($config_split,$id_invoice,$back_active = true,$page = 0,&$rowCount = null)
{
    global $textbotlang, $setting;
    return \Faoxima\Telegram\Keyboard\ConfigurationKeyboard::build($textbotlang, $setting, $config_split, $id_invoice, $back_active, $page, $rowCount);
}

    public static function keyboard_config_unavailable_text($dataUserOut, $outcomeOverride = null)
{
    global $datatextbot;
    return \Faoxima\Telegram\Keyboard\ConfigurationKeyboard::unavailableText($datatextbot, $dataUserOut, $outcomeOverride);
}
}
