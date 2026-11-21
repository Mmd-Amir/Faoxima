<?php

namespace Faoxima\Telegram\Routing;

final class SectionRoutes
{
    public static function tables(): array
    {
        return [
            'exact' => [
                'start' => 'main_menu', 'backuser' => 'main_menu', 'admin_back' => 'main_menu',
                'confirmchannel' => 'main_menu', 'rules_accept' => 'main_menu', 'acceptrule' => 'main_menu',
                'buy' => 'purchase', 'buyback' => 'purchase', 'buybacktow' => 'purchase', 'backproduct' => 'purchase', 'customsellvolume' => 'purchase',
                'extendbtn' => 'extend',
                'myservicesbtn' => 'services', 'backorder' => 'services',
                'accountbtn' => 'wallet',
                'usertestbtn' => 'test',
                'Tariff_list' => 'tariff',
                'affiliatesbtn' => 'referral',
                'wheel_luck' => 'wheel',
                'helpmenubtn' => 'help', 'helpbtns' => 'help',
                'supportbtns' => 'support', 'supporttickets' => 'support', 'ticketnew' => 'support',
                'agentpanel' => 'agent', 'requestagent' => 'agent',
            ],
            'prefix' => [
                'locationbuy_' => 'purchase', 'categorynames_' => 'purchase', 'prodcutservice' => 'purchase',
                'producttime_' => 'purchase', 'productvolume_' => 'purchase', 'paneluserbuy_' => 'purchase',
                'extends_' => 'extend', 'serviceextend' => 'extend',
                'quickview_' => 'services', 'updateproduct_' => 'services', 'subscriptionurl_' => 'services',
                'removeserviceuser_' => 'services', 'transfer_' => 'services', 'infocard_qr_' => 'services',
                'Extra_time_' => 'services', 'Extra_volume_' => 'services', 'Extra_volumes_' => 'services',
                'extravolunme' => 'services', 'nmstock' => 'services',
                'ticket' => 'support', 'tk_' => 'support', 'departman_' => 'support',
                'helpsection_' => 'help', 'helpos_' => 'help',
            ],
            'text' => [
                'text_sell' => 'purchase', 'text_extend' => 'extend', 'text_Purchased_services' => 'services',
                'accountwallet' => 'wallet', 'text_usertest' => 'test', 'text_Tariff_list' => 'tariff',
                'text_affiliates' => 'referral', 'text_wheel_luck' => 'wheel', 'text_help' => 'help',
                'text_support' => 'support', 'textpanelagent' => 'agent', 'textrequestagent' => 'agent',
            ],
        ];
    }

    public static function callback($callback, callable $readTables): ?string
    {
        $callback = (string) $callback;
        if ($callback === '') {
            return null;
        }
        $tables = $readTables();
        if (isset($tables['exact'][$callback])) {
            return $tables['exact'][$callback];
        }
        foreach ($tables['prefix'] as $prefix => $section) {
            if (strpos($callback, $prefix) === 0) {
                return $section;
            }
        }
        return null;
    }

    public static function resolve($datain, $text, $backLabel, $datatextbot, callable $routeCallback, callable $readTables): ?string
    {
        $section = $routeCallback($datain);
        if ($section !== null) {
            return $section;
        }
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if ($text === '/start' || $text === 'start') {
            return 'main_menu';
        }
        if ($text === '/services') {
            return 'services';
        }
        if ($backLabel !== '' && $text === $backLabel) {
            return 'main_menu';
        }
        foreach ($readTables()['text'] as $key => $section) {
            if (isset($datatextbot[$key]) && is_string($datatextbot[$key]) && $datatextbot[$key] !== '' && $text === $datatextbot[$key]) {
                return $section;
            }
        }
        return null;
    }
}
