<?php

namespace Faoxima\Admin\Web\Pages;

final class TextbotRequestHelpers
{
    public static function faoxima_text_categories(): array {
    $faoximaCategories = [
        'custom_pricing' => ['title' => 'متن قیمت‌گذاری هر گیگ', 'icon' => 'coins', 'keys' => ['text_custom_price_summary']],
        'sys'  => ['title' => 'سیستم و شروع',        'icon' => 'gear', 'keys' => ['text_start', 'text_roll', 'miniapp_suggest_1', 'text_miniapp_button', 'text_account_info']],
        'srv'  => ['title' => 'سرویس‌ها',              'icon' => 'server', 'keys' => ['text_Purchased_services', 'text_usertest', 'crontest', 'textafterpay', 'dyn_purchase_subscription_link_line', 'textaftertext', 'textmanual', 'textselectlocation', 'text_extend', 'text_wgdashboard', 'text_service_detail', 'dyn_renewconfirm_queued_success', 'dyn_renewal_success_same_link']],
        'help' => ['title' => 'راهنما و پشتیبانی',     'icon' => 'message', 'keys' => ['text_fq', 'text_dec_fq', 'text_help', 'text_support', 'text_channel']],
        'fin'  => ['title' => 'مالی و خرید',           'icon' => 'coins', 'keys' => ['text_Add_Balance', 'text_sell', 'text_Tariff_list', 'text_dec_Tariff_list', 'accountwallet', 'text_pishinvoice', 'text_cart', 'text_cart_auto', 'text_Discount', 'text_wheel_luck', 'carttocart', 'textnowpayment', 'textsnowpayment', 'textnowpaymenttron', 'text_star_telegram', 'iranpay3', 'iranpay2', 'iranpay1', 'tonpay', 'rialit', 'cubepay', 'blupal', 'variza', 'abangateway', 'atlaspay', 'zarinpal', 'textpaymentnotverify', 'dyn_public_log_btn_label', 'dyn_public_broadcast_new_sub_tpl', 'dyn_public_broadcast_renewal_tpl', 'dyn_public_broadcast_volume_topup_tpl', 'dyn_public_broadcast_time_extra_tpl', 'dyn_public_broadcast_wallet_deposit_tpl']],
        'ref'  => ['title' => 'زیرمجموعه و نمایندگی', 'icon' => 'users', 'keys' => ['text_affiliates', 'textrequestagent', 'textpanelagent', 'text_request_agent_dec']],
        'err_general' => ['title' => 'پیام‌های عمومی خطا', 'icon' => 'circle-exclamation', 'keys' => ['dyn_errors_verification_failed', 'dyn_errors_restart_process', 'dyn_errors_concurrency_insufficient_stock', 'dyn_errors_panel_connection_error', 'dyn_errors_button_disabled', 'dyn_errors_feature_unavailable', 'dyn_errors_feature_unavailable_short', 'dyn_errors_feature_currently_unavailable_nodot', 'dyn_errors_service_found_count', 'dyn_errors_service_not_found', 'dyn_errors_view_account_unavailable', 'dyn_errors_qr_unavailable', 'dyn_errors_service_deleted', 'dyn_errors_config_read_error', 'dyn_errors_panel_unavailable_dot', 'dyn_errors_config_read_from_panel_error', 'dyn_errors_usage_stats_failed', 'dyn_errors_server_list_failed', 'dyn_errors_not_connected_change_status']],
        'err_purchase' => ['title' => 'خطاهای خرید و تمدید', 'icon' => 'cart-shopping', 'keys' => ['dyn_errors_renewal_failed_restart', 'dyn_errors_renewal_support_error', 'dyn_errors_purchase_info_incomplete', 'dyn_errors_panel_not_found', 'dyn_errors_panel_unavailable_choose_other', 'dyn_errors_extra_volume_purchase_error', 'dyn_errors_location_change_limit_reached', 'dyn_errors_min_max_deposit_amount', 'dyn_errors_stock_depleted_no_charge', 'dyn_errors_stock_extend_success', 'dyn_errors_invalid_subscription_link', 'dyn_errors_extend_unavailable_panel', 'dyn_errors_not_connected_extend', 'dyn_errors_extend_current_plan_unavailable', 'dyn_errors_extend_restart_process', 'dyn_errors_exclusive_discount_conflict', 'dyn_errors_discount_expired', 'dyn_errors_discount_applied', 'dyn_errors_link_change_service_disabled', 'dyn_errors_extra_volume_unavailable_panel', 'dyn_errors_extra_volume_restart_process', 'dyn_errors_purchase_failed_restart', 'dyn_errors_location_change_unavailable', 'dyn_errors_generic_restart_process', 'dyn_errors_restart_buy_process_short', 'dyn_errors_transfer_panel_unavailable', 'dyn_errors_transfer_unused_config_only', 'dyn_errors_removal_request_thanks', 'dyn_errors_extra_time_unavailable_panel', 'dyn_errors_extra_time_restart_process', 'dyn_errors_service_removed', 'dyn_errors_service_removal_unavailable', 'dyn_errors_removal_reason_prompt', 'dyn_errors_test_service_unavailable', 'dyn_errors_min_bulk_purchase_balance', 'dyn_errors_section_disabled', 'dyn_errors_discount_code_not_applicable', 'dyn_errors_stock_low_purchase_blocked', 'dyn_errors_queued_renewal_exists']],
        'tickets' => ['title' => 'تیکت پشتیبانی', 'icon' => 'ticket', 'keys' => ['dyn_tickets_select_category', 'dyn_tickets_select_department', 'dyn_tickets_ask_subject', 'dyn_tickets_operation_canceled', 'dyn_tickets_invalid_subject_text', 'dyn_tickets_ask_media_new', 'dyn_tickets_ask_media_reply', 'dyn_tickets_media_yes', 'dyn_tickets_media_no', 'dyn_tickets_cancel', 'dyn_tickets_ask_media_count', 'dyn_tickets_ask_media_upload', 'dyn_tickets_invalid_media_choice', 'dyn_tickets_invalid_media_type', 'dyn_tickets_media_received', 'dyn_tickets_ask_body_text', 'dyn_tickets_ask_body_text_short', 'dyn_tickets_not_available', 'dyn_tickets_reply_recorded', 'dyn_tickets_invalid_body_text', 'dyn_tickets_created', 'dyn_tickets_closed_locked', 'dyn_tickets_no_media', 'dyn_tickets_closed_confirm']],
        'wallet_ext' => ['title' => 'کیف پول و پرداخت', 'icon' => 'wallet', 'keys' => ['dyn_wallet_select_amount', 'dyn_wallet_recheck_canceled', 'dyn_wallet_amount_not_numeric', 'dyn_wallet_rate_unavailable', 'dyn_wallet_calculated_amount_invalid', 'dyn_wallet_hash_not_found', 'dyn_wallet_ask_receipt_photo', 'dyn_wallet_incomplete_info', 'dyn_wallet_hash_already_used', 'dyn_wallet_internal_error_retry', 'dyn_wallet_invalid_charge_amount', 'dyn_wallet_discount_not_applicable_zero', 'dyn_wallet_no_active_card', 'dyn_wallet_select_verified_card', 'dyn_wallet_card_auth_active_notice', 'dyn_wallet_queue_too_busy', 'dyn_wallet_hashchecker_not_loaded', 'dyn_wallet_no_wallet_address_configured', 'dyn_wallet_charge_amount_undetectable', 'dyn_wallet_invoice_not_found', 'dyn_wallet_invoice_not_pending', 'dyn_wallet_prompt_amount_prompt', 'dyn_wallet_prompt_amount_out_of_range', 'dyn_wallet_prompt_debt_must_pay_first', 'dyn_wallet_prompt_has_discount_yes', 'dyn_wallet_prompt_has_discount_no', 'dyn_wallet_prompt_ask_has_discount', 'dyn_wallet_prompt_auth_success']],
        'atlaspay_bot' => ['title' => 'ارسال رسید اطلس‌پی در ربات', 'icon' => 'wallet', 'keys' => ['dyn_atlaspay_bot_receipt_button', 'dyn_atlaspay_bot_receipt_instructions']],
        'tonpay' => ['title' => 'قالب پیام‌های پرداخت تون‌پی (TonPay Payment Message Templates)', 'icon' => 'wallet', 'keys' => array_values(array_filter(\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateKeys(), static function ($key) { return strpos($key, 'dyn_tonpay_') === 0; }))],
        'ref_ext' => ['title' => 'رفرال، پاداش و گردونه', 'icon' => 'gift', 'keys' => ['dyn_referral_welcome_alert', 'dyn_referral_inviter_reward_alert', 'dyn_referral_commission_credited', 'dyn_rewards_points_earned', 'dyn_rewards_cashback_gift', 'dyn_affiliates_extra_section_disabled', 'dyn_affiliates_extra_not_affiliate_of_anyone', 'dyn_affiliates_extra_gift_already_received', 'dyn_affiliates_extra_gift_credited_inviter', 'dyn_affiliates_extra_gift_activated', 'dyn_wheel_button_disabled_you', 'dyn_wheel_no_purchase_users_only', 'dyn_wheel_price_unavailable']],
        'misc' => ['title' => 'سایر (گروه/ادمین)', 'icon' => 'user-tag', 'keys' => ['dyn_group_setup_topic_not_active', 'dyn_group_setup_insufficient_access', 'dyn_group_setup_chat_id_received']],
        'cron' => ['title' => 'اعلانات کرون', 'icon' => 'hourglass', 'keys' => ['dyn_cron_volume_warning_tpl', 'dyn_cron_volume_warning_report_tpl', 'dyn_cron_time_warning_tpl', 'dyn_cron_time_warning_report_tpl', 'dyn_cron_removal_notice_tpl', 'dyn_cron_removal_report_tpl', 'dyn_cron_removal_volume_notice_tpl', 'dyn_cron_removal_volume_report_tpl', 'dyn_cron_extend_service_btn', 'dyn_gift_all_done', 'dyn_gift_error_report_tpl', 'dyn_gift_failed_users_suffix_tpl', 'dyn_uptime_api_access_error_tpl', 'dyn_uptime_api_access_restored_tpl', 'dyn_uptime_xray_stopped_tpl', 'dyn_uptime_xray_restored_tpl', 'dyn_uptime_panel_not_connected_tpl', 'dyn_queued_renewal_activated_tpl']],
        'jsontext_users' => ['title' => 'متن‌های کاربر (text.json)', 'icon' => 'comment-dots', 'keys' => []],
        'jsontext_admin' => ['title' => 'متن‌های ادمین (text.json)', 'icon' => 'user-shield', 'keys' => []],
    ];
    return $faoximaCategories + \Faoxima\Telegram\Support\MessageTemplates::categories();
}

    public static function faoxima_jsontext_prefix_map(): array {
    return [
        'users.balance.'          => 'fin',
        'users.discount.'         => 'fin',
        'users.sell.'             => 'fin',
        'users.customsellvolume.' => 'fin',
        'users.customvolume.'     => 'fin',
        'users.pricearze.'        => 'fin',
        'users.wheel_luck.'       => 'fin',
        'users.stateus.'          => 'srv',
        'users.extend.'           => 'srv',
        'users.extra_volume.'     => 'srv',
        'users.extra_time.'       => 'srv',
        'users.usertest.'         => 'srv',
        'users.help.'             => 'help',
        'users.support.'          => 'help',
        'users.affiliates.'       => 'ref',
        'users.agenttext.'        => 'ref',
        'admin.'                  => 'jsontext_admin',
        'users.'                  => 'jsontext_users',
    ];
}

    public static function faoxima_jsontext_group(string $key): ?string {
    if (strpos($key, 'jsontext.') !== 0) {
        return null;
    }
    $path = strtolower(substr($key, strlen('jsontext.')));
    foreach (self::faoxima_jsontext_prefix_map() as $prefix => $cat) {
        if (strpos($path, $prefix) === 0) {
            return $cat;
        }
    }
    return 'jsontext_users';
}

    public static function faoxima_text_has_placeholder(string $text): bool {
    return (bool) preg_match('/%(\d+\$)?[sd]|\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $text);
}

    public static function faoxima_text_group(string $key): string {
    if ($key === 'text_custom_price_summary') return 'custom_pricing';
    $k = strtolower($key);
    foreach (self::faoxima_text_categories() as $cat => $info) {
        foreach ($info['keys'] as $known) {
            if ($k === strtolower($known)) return $cat;
        }
    }
    $jsontextCat = self::faoxima_jsontext_group($k);
    if ($jsontextCat !== null) return $jsontextCat;
    if (strpos($k, 'zarinp') !== false || strpos($k, 'pay') !== false || strpos($k, 'cart') !== false || strpos($k, 'star') !== false)
        return 'fin';
    if (strpos($k, 'agent') !== false)
        return 'ref';
    return 'other';
}

    public static function faoxima_tab_title(string $tab, array $categories): string {
    return $categories[$tab]['title'] ?? 'سایر';
}

    public static function faoxima_tab_icon(string $tab, array $categories): string {
    return $categories[$tab]['icon'] ?? 'more-horizontal';
}
}
