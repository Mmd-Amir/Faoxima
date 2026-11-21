<?php

namespace Faoxima\Telegram\Routing;

final class FollowupRoutes
{
    public static function payment($user, $datain, $text_inline, &$dataget): ?string
    {
        return match (true) {
            (bool) (preg_match('/^sendresidcart-(.*)/', $datain, $dataget)) => 'card_start',
            (bool) ($user['step'] == 'card_select_step') => 'card_select',
            (bool) ($user['step'] == 'card_photo_step') => 'card_photo',
            (bool) ($user['step'] == 'card_last4_step') => 'card_last_four',
            (bool) (preg_match('/^sendresidarze-(.*)/', $datain, $dataget) and $text_inline != null) => 'crypto_start',
            (bool) ($user['step'] == "digitaltron_hash_input") => 'crypto_hash',
            (bool) ($user['step'] == "digitaltron_receipt_photo") => 'crypto_photo',
            (bool) ($user['step'] == "getresidcurrency") => 'crypto_receipt',
            (bool) ($user['step'] == "cart_to_cart_user") => 'legacy_card',
            (bool) ($datain == "Discount") => 'discount_prompt',
            (bool) ($user['step'] == "get_code_user") => 'discount_code',
            default => null,
        };
    }

    public static function navigation($user, $datain, $text, $datatextbot, &$dataget): ?string
    {
        return match (true) {
            (bool) (($text !== '' && $text == $datatextbot['text_Tariff_list']) || $datain == "Tariff_list") => 'tariffs',
            (bool) ($datain == "colselist") => 'close_list',
            (bool) (($text !== '' && $text == $datatextbot['text_affiliates']) || $datain == "affiliatesbtn") => 'referral',
            (bool) ($datain == "get_gift_start") => 'referral_gift',
            (bool) (preg_match('/Extra_volumes_(\w+)_(.*)/', $datain, $dataget)) => 'legacy_traffic_start',
            (bool) ($user['step'] == "getvolumeextras") => 'legacy_traffic_amount',
            (bool) (preg_match('/confirmaextras_(\w+)/', $datain, $dataget)) => 'legacy_traffic_confirm',
            default => null,
        };
    }

    public static function conversation($user, $datain, $text, $textbotlang, $datatextbot): ?string
    {
        return match (true) {
            (bool) ($datain == "searchservice") => 'service_search',
            (bool) ($datain == "Responseuser") => 'user_response',
            (bool) ($user['step'] == "getmessageAsuser") => 'user_message',
            (bool) ((($text !== '' && $text == $datatextbot['textpanelagent']) || $datain == "agentpanel") && $user['agent'] != "f") => 'agent_panel',
            (bool) ($text == $textbotlang['users']['agenttext']['customnameusername'] || $datain == "selectname") => 'agent_name_prompt',
            (bool) ($user['step'] == "selectusernamecustom") => 'agent_name',
            (bool) (($text !== '' && $text == $datatextbot['textrequestagent']) || $datain == "requestagent") => 'agent_start',
            (bool) ($user['step'] == "getagentrequest" && $text) => 'agent_request',
            (bool) ($text == "/privacy") => 'privacy',
            (bool) (($text !== '' && $text == $datatextbot['text_wheel_luck']) || $datain == "wheel_luck" || $text == "/gift") => 'prize_wheel',
            (bool) ($text == "/tron") => 'tron_price',
            (bool) ($text == "/usd") => 'usd_price',
            default => null,
        };
    }

    public static function service($user, $datain, $text, $datatextbot, &$dataget): ?string
    {
        return match (true) {
            (bool) (($text !== '' && $text == $datatextbot['text_extend']) || $datain == "extendbtn") => 'renewal_list',
            (bool) ($datain == 'next_page_extends') => 'renewal_next',
            (bool) ($datain == 'previous_page_extends') => 'renewal_previous',
            (bool) (preg_match('/changenote_(\w+)/', $datain, $dataget)) => 'note_start',
            (bool) ($user['step'] == "getnotedit") => 'note_save',
            default => null,
        };
    }
}
