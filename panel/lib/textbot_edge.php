<?php


if (!function_exists('fx_textbot_edge_routing_keys')) {

    function fx_textbot_edge_routing_keys(): array
    {
        return [
            'text_sell',
            'text_usertest',
            'text_help',
            'text_support',
            'accountwallet',
            'text_Tariff_list',
            'text_affiliates',
            'textpanelagent',
            'textrequestagent',
            'text_wheel_luck',
            'text_extend',
            'text_Purchased_services',
            'jsontext.users.backbtn',
            'jsontext.users.agenttext.customnameusername',
            'jsontext.Admin.backadmin',
            'jsontext.Admin.backmenu',
            'jsontext.Admin.Status.btn',
            'jsontext.Admin.btnkeyboardadmin.addpanel',
            'jsontext.Admin.btnkeyboardadmin.managementpanel',
            'jsontext.Admin.btnkeyboardadmin.managruser',
            'jsontext.Admin.channel.removechannelbtn',
            'jsontext.Admin.channel.title',
            'jsontext.Admin.getlimitusertest.setlimitbtn',
        ];
    }
}

if (!function_exists('fx_textbot_is_routing_key')) {

    function fx_textbot_is_routing_key(string $key): bool
    {
        $k = strtolower($key);
        foreach (fx_textbot_edge_routing_keys() as $known) {
            if ($k === strtolower($known)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('fx_textbot_edge_class')) {

    function fx_textbot_edge_class(): string
    {
        return '(?![\x{E0020}-\x{E007F}])[\p{Z}\p{Cc}\p{Cf}\x{034F}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{2800}\x{3164}\x{FFA0}]';
    }
}

if (!function_exists('fx_textbot_edge_issue')) {

    function fx_textbot_edge_issue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $class = fx_textbot_edge_class();
        $lead = preg_match('/\A' . $class . '/u', $value);
        $trail = preg_match('/' . $class . '\z/u', $value);
        if ($lead === false || $trail === false) {
            $lead = $value !== ltrim($value) ? 1 : 0;
            $trail = $value !== rtrim($value) ? 1 : 0;
        }
        if ($lead && $trail) {
            return 'both';
        }
        if ($lead) {
            return 'leading';
        }
        if ($trail) {
            return 'trailing';
        }
        return '';
    }
}

if (!function_exists('fx_textbot_edge_violation')) {

    function fx_textbot_edge_violation(string $key, string $value): string
    {
        if (!fx_textbot_is_routing_key($key)) {
            return '';
        }
        return fx_textbot_edge_issue($value);
    }
}

if (!function_exists('fx_textbot_edge_error_message')) {

    function fx_textbot_edge_error_message(): string
    {
        return 'ابتدا یا انتهای متن نباید شامل فاصله یا کاراکتر نامرئی باشد.';
    }
}

if (!function_exists('fx_textbot_edge_js_config')) {

    function fx_textbot_edge_js_config(): string
    {
        return json_encode([
            'keys'    => array_map('strtolower', fx_textbot_edge_routing_keys()),
            'cls'     => str_replace('\x{', '\u{', fx_textbot_edge_class()),
            'message' => fx_textbot_edge_error_message(),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
