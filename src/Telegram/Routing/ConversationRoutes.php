<?php

namespace Faoxima\Telegram\Routing;

final class ConversationRoutes
{
    public static function trial($user, $datain, $text, $datatextbot, &$dataget): ?string
    {
        return match (true) {
            (bool) ($user['step'] == "createusertest" || preg_match('/locationtest_(.*)/', $datain, $dataget) || (($text !== '' && $text == $datatextbot['text_usertest']) || $datain == "usertestbtn" || $text == "usertest")) => 'trial',
            default => null,
        };
    }

    public static function paymentFollowup($user, $datain, $update, &$rialitActionMatch, &$rialitViewMatch, &$apcMatch, &$tpcMatch): ?string
    {
        return match (true) {
            (bool) ($datain === 'tpc_list') => 'tonpay_list',
            (bool) (preg_match('/^rl_(cancel|abandon|retry)_([A-Za-z0-9_\-]{1,64})$/', (string) $datain, $rialitActionMatch)) => 'rialit_action',
            (bool) (preg_match('/^rl_view_([A-Za-z0-9_\-]{1,64})$/', (string) $datain, $rialitViewMatch)) => 'rialit_view',
            (bool) (preg_match('/^apc_(chk|can|cny|view)_(\w+)$/', (string) $datain, $apcMatch)) => 'atlaspay_action',
            (bool) (preg_match('/^tpc_(chk|chg|rcp|can|cny|view|rfd)_(\w+)$/', (string) $datain, $tpcMatch)) => 'tonpay_action',
            (bool) ($user['step'] == "tpc_receipt" && $datain === '' && isset($update['message'])) => 'tonpay_receipt',
            (bool) ($user['step'] == "tpc_refund" && $datain === '' && isset($update['message'])) => 'tonpay_refund',
            (bool) (preg_match('/^ckw_(pick|ok|menu|cancel)_/', (string) $datain)) => 'checkout_wallet',
            default => null,
        };
    }

    public static function help($datain, $text, $datatextbot, &$dataget): ?string
    {
        return match (true) {
            (bool) (($text !== '' && $text == $datatextbot['text_help']) || $datain == "helpbtn" || $datain == "helpbtns" || $datain == "helpmenubtn" || $text == "/help" || $text == "help") => 'help_menu',
            (bool) (preg_match('/^helpctgoryـ(.*)/', $datain, $dataget)) => 'help_category',
            (bool) (preg_match('/^helpos_(.*)/', $datain, $dataget)) => 'help_topic',
            default => null,
        };
    }

    public static function support($user, $datain, $text, $datatextbot, $photo, $video, &$dataget): ?string
    {
        return match (true) {
            (bool) (($text !== '' && $text == $datatextbot['text_support']) || $datain == "supportbtns" || $text == "/support") => 'support_menu',
            (bool) ($datain == "support" || $datain == "supporttickets") => 'ticket_list',
            (bool) ($datain == "ticketnew") => 'ticket_new',
            (bool) (preg_match('/^departman_(.*)/', $datain, $dataget)) => 'ticket_department',
            (bool) ($datain == "tk_cancel") => 'ticket_cancel',
            (bool) ($user['step'] == "getticketsubj" && (string)$text !== '') => 'ticket_subject',
            (bool) ($user['step'] == "getticketsubj") => 'ticket_subject_invalid',
            (bool) ($datain == "tk_media_no" && $user['step'] == "tk_media_choice") => 'ticket_text_only',
            (bool) ($datain == "tk_media_yes" && $user['step'] == "tk_media_choice") => 'ticket_media_choice',
            (bool) (preg_match('/^tk_cnt_([1-5])$/', $datain, $dataget) && $user['step'] == "tk_media_count") => 'ticket_media_count',
            (bool) (($user['step'] == "tk_media_choice" || $user['step'] == "tk_media_count") && empty($datain)) => 'ticket_media_invalid',
            (bool) ($user['step'] == "tk_collect") => 'ticket_collect',
            (bool) ($user['step'] == "tk_desc" && ((string)$text !== '' || $photo || $video)) => 'ticket_submit',
            (bool) ($user['step'] == "tk_desc") => 'ticket_body_invalid',
            (bool) (preg_match('/^ticketopen_(\w+)$/', $datain, $dataget)) => 'ticket_open',
            (bool) (preg_match('/^(?:ticketmsg|ticketreply)_(\w+)$/', $datain, $dataget)) => 'ticket_reply',
            (bool) (preg_match('/^tk_album_(\w+)$/', $datain, $dataget)) => 'ticket_album',
            (bool) (preg_match('/^ticketclose_(\w+)$/', $datain, $dataget)) => 'ticket_close',
            default => null,
        };
    }

    public static function wallet($user, $datain, $text, $datatextbot, &$txRangeMatch): ?string
    {
        return match (true) {
            (bool) ($datain == "my_miniapp_pending") => 'miniapp_pending',
            (bool) ($datain == "fqQuestions") => 'faq',
            (bool) (($text !== '' && $text == $datatextbot['accountwallet']) || $datain == "account" || $datain == "accountbtn" || $text == "/wallet") => 'wallet',
            (bool) ($datain == "MyTransactions") => 'transactions',
            (bool) (preg_match('/^tx_(24h|3d|7d|30d)(?:_(\d+))?$/', (string)$datain, $txRangeMatch)) => 'transaction_range',
            (bool) ($datain == "TransferBalance") => 'transfer_start',
            (bool) ($user['step'] == "getTransferRecipient") => 'transfer_recipient',
            (bool) ($user['step'] == "getTransferAmount") => 'transfer_amount',
            (bool) ($datain == "transferbal_cancel") => 'transfer_cancel',
            (bool) (($datain == "transferbal_confirm" || str_starts_with((string)$datain, 'transferbal_confirm_')) && $user['step'] == "getTransferConfirm") => 'transfer_confirm',
            default => null,
        };
    }

    public static function purchase($user, $datain, $text, $datatextbot, $statusnote, &$dataget): ?string
    {
        return match (true) {
            (bool) ((($text !== '' && $text == $datatextbot['text_sell']) || $datain == "buy" || $datain == "buyback" || $text == "/buy" || $text == "buy") && $statusnote) => 'purchase_note',
            (bool) (($text !== '' && $text == $datatextbot['text_sell']) || $datain == "buy" || $datain == "buybacktow" || $datain == "buyback" || $text == "/buy" || $text == "buy" || $user['step'] == "statusnamecustom") => 'purchase_start',
            (bool) (preg_match('/^location_(.*)/', $datain, $dataget) || $datain == "backproduct") => 'purchase_panel',
            (bool) (preg_match('/^categorynames_(.*)/', $datain, $dataget)) => 'purchase_category',
            (bool) (preg_match('/^productmonth_(\w+)/', $datain, $dataget)) => 'purchase_month',
            (bool) ($datain == "customsellvolume") => 'purchase_custom',
            (bool) ($user['step'] == "gettimecustomvol") => 'purchase_duration',
            (bool) ($user['step'] == "getvolumecustomusername" || preg_match('/^prodcutservices_(.*)/', $datain, $dataget)) => 'purchase_username',
            (bool) ($user['step'] == "endstepuser" || $user['step'] == "endstepusers" || preg_match('/prodcutservice_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuser") => 'purchase_product',
            (bool) ($user['step'] == "payment" && in_array($datain, ["confirmandgetservice", "confirmandgetserviceDiscount"], true)) => 'purchase_confirm',
            (bool) ($datain == "aptdc") => 'purchase_discount_prompt',
            (bool) ($user['step'] == "getcodesellDiscount") => 'purchase_discount',
            default => null,
        };
    }

    public static function bulkPurchase($user, $datain, $text, &$dataget): ?string
    {
        return match (true) {
            (bool) ($text == "🗂 خرید انبوه" || $datain == "kharidanbuh") => 'bulk_start',
            (bool) ($user['step'] == "getcountconfig") => 'bulk_count',
            (bool) (preg_match('/^locationom_(.*)/', $datain, $dataget)) => 'bulk_panel',
            (bool) ($datain == "customsellvolumeom") => 'bulk_custom',
            (bool) ($user['step'] == "gettimecustomvolom") => 'bulk_duration',
            (bool) ($user['step'] == "getvolumecustomusernameom" || preg_match('/^prodcutservicesom_(.*)/', $datain, $dataget)) => 'bulk_username',
            (bool) ($user['step'] == "endstepuserom" || $user['step'] == "endstepusersom" || preg_match('/prodcutserviceom_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuserom") => 'bulk_product',
            (bool) ($user['step'] == "payments" && $datain == "confirmandgetservice") => 'bulk_confirm',
            default => null,
        };
    }

    public static function recharge($user, $datain, $photoid, &$rwPickMatch, &$rccPick): ?string
    {
        return match (true) {
            (bool) ($datain == "Add_Balance") => 'recharge_start',
            (bool) (preg_match('/^rw_pick_(\d+)$/', (string)$datain, $rwPickMatch)) => 'recharge_preset',
            (bool) ($datain == "rw_custom") => 'recharge_custom',
            (bool) ($datain == "rcc_cancel") => 'crypto_cancel',
            (bool) ($datain == "recheckcrypto") => 'crypto_recheck',
            (bool) (strpos((string) $datain, 'rcc_view_') === 0) => 'crypto_view',
            (bool) (strpos((string) $datain, 'rcc_resubmit_') === 0) => 'crypto_resubmit',
            (bool) (preg_match('/^rcc_pick_([A-Za-z0-9_]{2,20})$/', (string) $datain, $rccPick)
                && in_array($rccPick[1], array_column(method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallets') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallets() : [], 'currency'), true)) => 'crypto_pick',
            (bool) ($user['step'] == "rcc_amount" && empty($datain)) => 'crypto_amount',
            (bool) ($user['step'] == "rcc_hash" && empty($datain)) => 'crypto_hash',
            (bool) ($user['step'] == "rcc_photo" && ($datain == "rcc_skip_photo" || !empty($photoid))) => 'crypto_photo',
            (bool) ($user['step'] == "getprice") => 'recharge_amount',
            (bool) ($datain == "chargenodiscount") => 'recharge_no_discount',
            (bool) ($datain == "chargehasdiscount") => 'recharge_discount_prompt',
            (bool) ($user['step'] == "getcodesellDiscountcharge") => 'recharge_discount',
            (bool) ($user['step'] == "get_step_payment") => 'recharge_payment',
            default => null,
        };
    }
}
