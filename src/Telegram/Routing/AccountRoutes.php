<?php

namespace Faoxima\Telegram\Routing;

final class AccountRoutes
{
    public static function account($user, $text, $datain, $textbotlang, $datatextbot, $from_id, $admin_ids, &$dataget): ?string
    {
        return match (true) {
            (bool) ($text == "version") => 'version',
            (bool) ($text == $textbotlang['users']['backbtn'] || $datain == "backuser" || $datain == "admin_back") => 'back',
            (bool) ($user['step'] == 'get_number' && in_array($from_id, $admin_ids)) => 'admin_phone',
            (bool) ($user['step'] == 'get_number' && !in_array($from_id, $admin_ids)) => 'phone',
            (bool) (($text !== '' && $text == $datatextbot['text_Purchased_services']) || $datain == "backorder" || $datain == "myservicesbtn" || $datain == "next_page" || $datain == "previous_page" || $text == "/services") => 'services',
            (bool) (str_starts_with((string) $datain, 'ms:')) => 'services_page',
            (bool) (preg_match('/^(myservicescat|next_page|previous_page)_([a-f0-9]{32})(?:_([0-9]{1,10}))?$/', (string) $datain, $dataget)) => 'services_page',
            (bool) ($datain == "notusernameme") => 'lookup_prompt',
            (bool) ($user['step'] == "getusernameinfo") => 'lookup_name',
            (bool) (preg_match('/locationnotuser_(.*)/', $datain, $dataget)) => 'lookup_panel',
            default => null,
        };
    }

    public static function service($user, $text, $datain, &$dataget, &$rwm): ?string
    {
        return match (true) {
            (bool) (preg_match('/^quickview_(\w+)/', $datain, $dataget) || preg_match('/^product_(\w+)/', $datain, $dataget) || preg_match('/updateproduct_(\w+)/', $datain, $dataget) || $user['step'] == "getuseragnetservice" || $datain == "productcheckdata") => 'service_detail',
            (bool) (preg_match('/^infocard_qr_(\w+)$/', $datain, $dataget)) => 'info_qr',
            (bool) (preg_match('/subscriptionurl_(\w+)/', $datain, $dataget) || (is_string($text) && strpos($text, "/sub ") !== false)) => 'subscription',
            (bool) (preg_match('/removeauto-(\w+)/', $datain, $dataget)) => 'remove_auto',
            (bool) (preg_match('/^configpage_(.*)_(\d+)$/', $datain, $dataget)) => 'config_page',
            (bool) (preg_match('/config_(\w+)/', $datain, $dataget) || (is_string($text) && strpos($text, "/link ") !== false)) => 'config_send',
            (bool) (preg_match('/configget_(.*)_(.*)/', $datain, $dataget)) => 'config_item',
            (bool) (preg_match('/^remnausage_(.+)$/', $datain, $rwm)) => 'remna_usage',
            (bool) (preg_match('/^remnasetsquad_(.+)_([0-9a-fA-F-]{36})$/', $datain, $rwm)) => 'remna_squad',
            (bool) (preg_match('/^remnapicker_(.+)$/', $datain, $rwm)) => 'remna_picker',
            (bool) (preg_match('/changestatus_(\w+)/', $datain, $dataget)) => 'service_status',
            (bool) (preg_match('/confirmaccountdisable_(\w+)/', $datain, $dataget)) => 'disable_service',
            default => null,
        };
    }

    public static function renewal($user, $datain, &$dataget): ?string
    {
        return match (true) {
            (bool) (preg_match('/extend_(\w+)/', $datain, $dataget)) => 'renewal_start',
            (bool) ($user['step'] == "gettimecustomvolomforextend") => 'renewal_duration',
            (bool) (preg_match('/productextendmonths_(\w+)/', $datain, $dataget)) => 'renewal_month',
            (bool) (preg_match('/^serviceextendselect_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuserforextend" || $datain == "exntedagei") => 'renewal_product',
            (bool) (in_array($datain, ['rsvt', 'rsvp', 'rsvu', 'rsvd'], true) || strpos((string) $datain, 'rsvc:') === 0) => 'renewal_reservation',
            (bool) ($user['step'] == 'rsv_get_date') => 'renewal_date',
            (bool) ($datain == "discountextend") => 'renewal_discount_prompt',
            (bool) ($user['step'] == "getcodesellDiscountextend") => 'renewal_discount',
            (bool) ($datain == "confirmserivce" || $datain == "confirmserdiscount") => 'renewal_confirm',
            default => null,
        };
    }

    public static function serviceChanges($user, $datain, $text, $datatextbot, &$dataget): ?string
    {
        return match (true) {
            (bool) (preg_match('/changelink_(\w+)/', $datain, $dataget)) => 'rotate_link',
            (bool) (preg_match('/confirmchange_(\w+)/', $datain, $dataget)) => 'rotate_confirm',
            (bool) (preg_match('/Extra_volume_(\w+)/', $datain, $dataget)) => 'traffic_start',
            (bool) ($user['step'] == "getvolumeextra") => 'traffic_amount',
            (bool) (preg_match('/discountvolume-(\w+)/', $datain, $dataget)) => 'traffic_discount_prompt',
            (bool) ($user['step'] == "getcodesellDiscountvolume") => 'traffic_discount',
            (bool) (preg_match('/confirmaextra(discount)?-(\w+)/', $datain, $dataget)) => 'traffic_confirm',
            (bool) (preg_match('/changeloc_(\w+)/', $datain, $dataget)) => 'location_start',
            (bool) (preg_match('/changelocselectlo-(\w+)/', $datain, $dataget)) => 'location_select',
            (bool) (preg_match('/confirmchangeloccha_(\w+)/', $datain, $dataget)) => 'location_confirm',
            (bool) (preg_match('/disorder-(\w+)/', $datain, $dataget)) => 'dispute_start',
            (bool) ($user['step'] == "getdesdisorder") => 'dispute_description',
            (bool) (preg_match('/confirmdisorders-(\w+)/', $datain, $dataget)) => 'dispute_confirm',
            (bool) (preg_match('/Extra_time_(\w+)/', $datain, $dataget)) => 'time_start',
            (bool) ($user['step'] == "gettimeextra") => 'time_amount',
            (bool) (preg_match('/discounttime-(\w+)/', $datain, $dataget)) => 'time_discount_prompt',
            (bool) ($user['step'] == "getcodesellDiscounttime") => 'time_discount',
            (bool) (preg_match('/confirmaextratime(discount)?-(\w+)/', $datain, $dataget)) => 'time_confirm',
            (bool) (preg_match('/deletelist-(\w+)/', $datain, $dataget)) => 'delete_prompt',
            (bool) (preg_match('/removeserviceuser_(\w+)/', $datain, $dataget)) => 'delete_start',
            (bool) ($user['step'] == "getdisdeleteconfig") => 'delete_description',
            (bool) (preg_match('/confirmremoveservices-(\w+)/', $datain, $dataget)) => 'delete_confirm',
            (bool) (preg_match('/transfer_(\w+)/', $datain, $dataget)) => 'transfer_start',
            (bool) ($user['step'] == "getidfortransfer") => 'transfer_recipient',
            (bool) (preg_match('/confrimtransfers_(\w+)/', $datain, $dataget)) => 'transfer_confirm',
            (bool) (($text !== '' && $text == $datatextbot['text_usertest']) || $datain == "usertestbtn" || $text == "usertest") => 'trial_menu',
            default => null,
        };
    }
}
