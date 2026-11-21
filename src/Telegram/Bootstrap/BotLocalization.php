<?php

namespace Faoxima\Telegram\Bootstrap;

use PDO;

final class BotLocalization
{
    public static function textCatalog(array $rows): array
    {
        $datatextbot = array(
            'text_usertest' => '',
            'text_Purchased_services' => '',
            'text_support' => '',
            'text_help' => '',
            'text_start' => '',
            'text_bot_off' => '',
            'text_dec_info' => '',
            'text_roll' => '',
            'text_fq' => '',
            'text_dec_fq' => '',
            'text_sell' => '',
            'text_Add_Balance' => '',
            'text_channel' => '',
            'text_Tariff_list' => '',
            'text_dec_Tariff_list' => '',
            'text_affiliates' => '',
            'text_pishinvoice' => '',
            'text_custom_price_summary' => \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text_default(),
            'text_account_info' => '',
            'text_service_detail' => '',
            'dyn_errors_verification_failed' => '',
            'dyn_errors_restart_process' => '',
            'dyn_errors_concurrency_insufficient_stock' => '',
            'dyn_errors_panel_connection_error' => '',
            'dyn_errors_button_disabled' => '',
            'dyn_errors_feature_unavailable' => '',
            'dyn_errors_feature_unavailable_short' => '',
            'dyn_errors_feature_currently_unavailable_nodot' => '',
            'dyn_errors_renewal_failed_restart' => '',
            'dyn_errors_renewal_support_error' => '',
            'dyn_errors_purchase_info_incomplete' => '',
            'dyn_errors_panel_not_found' => '',
            'dyn_errors_panel_unavailable_choose_other' => '',
            'dyn_errors_extra_volume_purchase_error' => '',
            'dyn_errors_location_change_limit_reached' => '',
            'dyn_errors_min_max_deposit_amount' => '',
            'dyn_errors_stock_depleted_no_charge' => '',
            'dyn_errors_stock_extend_success' => '',
            'dyn_errors_invalid_subscription_link' => '',
            'dyn_errors_service_found_count' => '',
            'dyn_errors_service_not_found' => '',
            'dyn_errors_view_account_unavailable' => '',
            'dyn_errors_qr_unavailable' => '',
            'dyn_errors_service_deleted' => '',
            'dyn_errors_config_read_error' => '',
            'dyn_errors_panel_unavailable_dot' => '',
            'dyn_errors_config_read_from_panel_error' => '',
            'dyn_errors_usage_stats_failed' => '',
            'dyn_errors_server_list_failed' => '',
            'dyn_errors_not_connected_change_status' => '',
            'dyn_errors_extend_unavailable_panel' => '',
            'dyn_errors_not_connected_extend' => '',
            'dyn_errors_extend_current_plan_unavailable' => '',
            'dyn_errors_extend_restart_process' => '',
            'dyn_errors_exclusive_discount_conflict' => '',
            'dyn_errors_discount_expired' => '',
            'dyn_errors_discount_applied' => '',
            'dyn_errors_link_change_service_disabled' => '',
            'dyn_errors_extra_volume_unavailable_panel' => '',
            'dyn_errors_extra_volume_restart_process' => '',
            'dyn_errors_purchase_failed_restart' => '',
            'dyn_errors_location_change_unavailable' => '',
            'dyn_errors_generic_restart_process' => '',
            'dyn_errors_restart_buy_process_short' => '',
            'dyn_errors_transfer_panel_unavailable' => '',
            'dyn_errors_transfer_unused_config_only' => '',
            'dyn_errors_removal_request_thanks' => '',
            'dyn_errors_extra_time_unavailable_panel' => '',
            'dyn_errors_extra_time_restart_process' => '',
            'dyn_errors_service_removed' => '',
            'dyn_errors_service_removal_unavailable' => '',
            'dyn_errors_removal_reason_prompt' => '',
            'dyn_errors_test_service_unavailable' => '',
            'dyn_errors_min_bulk_purchase_balance' => '',
            'dyn_errors_section_disabled' => '',
            'dyn_errors_discount_code_not_applicable' => '',
            'dyn_errors_stock_low_purchase_blocked' => '',
            'dyn_referral_welcome_alert' => '',
            'dyn_referral_inviter_reward_alert' => '',
            'dyn_referral_commission_credited' => '',
            'dyn_rewards_points_earned' => '',
            'dyn_rewards_cashback_gift' => '',
            'dyn_tickets_select_category' => '',
            'dyn_tickets_select_department' => '',
            'dyn_tickets_ask_subject' => '',
            'dyn_tickets_operation_canceled' => '',
            'dyn_tickets_invalid_subject_text' => '',
            'dyn_tickets_ask_media_new' => '',
            'dyn_tickets_ask_media_reply' => '',
            'dyn_tickets_media_yes' => '',
            'dyn_tickets_media_no' => '',
            'dyn_tickets_cancel' => '',
            'dyn_tickets_ask_media_count' => '',
            'dyn_tickets_ask_media_upload' => '',
            'dyn_tickets_invalid_media_choice' => '',
            'dyn_tickets_invalid_media_type' => '',
            'dyn_tickets_media_received' => '',
            'dyn_tickets_ask_body_text' => '',
            'dyn_tickets_ask_body_text_short' => '',
            'dyn_tickets_not_available' => '',
            'dyn_tickets_reply_recorded' => '',
            'dyn_tickets_invalid_body_text' => '',
            'dyn_tickets_created' => '',
            'dyn_tickets_closed_locked' => '',
            'dyn_tickets_no_media' => '',
            'dyn_tickets_closed_confirm' => '',
            'dyn_wallet_select_amount' => '',
            'dyn_wallet_recheck_canceled' => '',
            'dyn_wallet_amount_not_numeric' => '',
            'dyn_wallet_rate_unavailable' => '',
            'dyn_wallet_calculated_amount_invalid' => '',
            'dyn_wallet_hash_not_found' => '',
            'dyn_wallet_ask_receipt_photo' => '',
            'dyn_wallet_incomplete_info' => '',
            'dyn_wallet_hash_already_used' => '',
            'dyn_wallet_internal_error_retry' => '',
            'dyn_wallet_invalid_charge_amount' => '',
            'dyn_wallet_discount_not_applicable_zero' => '',
            'dyn_wallet_no_active_card' => '',
            'dyn_wallet_select_verified_card' => '',
            'dyn_wallet_card_auth_active_notice' => '',
            'dyn_wallet_queue_too_busy' => '',
            'dyn_wallet_hashchecker_not_loaded' => '',
            'dyn_wallet_no_wallet_address_configured' => '',
            'dyn_wallet_charge_amount_undetectable' => '',
            'dyn_wallet_invoice_not_found' => '',
            'dyn_wallet_invoice_not_pending' => '',
            'dyn_affiliates_extra_section_disabled' => '',
            'dyn_affiliates_extra_not_affiliate_of_anyone' => '',
            'dyn_affiliates_extra_gift_already_received' => '',
            'dyn_affiliates_extra_gift_credited_inviter' => '',
            'dyn_affiliates_extra_gift_activated' => '',
            'dyn_wheel_button_disabled_you' => '',
            'dyn_wheel_no_purchase_users_only' => '',
            'dyn_wheel_price_unavailable' => '',
            'dyn_wallet_prompt_amount_prompt' => '',
            'dyn_wallet_prompt_amount_out_of_range' => '',
            'dyn_wallet_prompt_debt_must_pay_first' => '',
            'dyn_wallet_prompt_has_discount_yes' => '',
            'dyn_wallet_prompt_has_discount_no' => '',
            'dyn_wallet_prompt_ask_has_discount' => '',
            'dyn_wallet_prompt_auth_success' => '',
            'dyn_group_setup_topic_not_active' => '',
            'dyn_group_setup_insufficient_access' => '',
            'dyn_group_setup_chat_id_received' => '',
            'accountwallet' => '',
            'textafterpay' => '',
            'textaftertext' => '',
            'textmanual' => '',
            'textselectlocation' => '',
            'crontest' => '',
            'textrequestagent' => '',
            'textpanelagent' => '',
            'text_wheel_luck' => '',
            'text_cart' => '',
            'text_request_agent_dec' => '',
            'carttocart' => '',
            'textnowpayment' => '',
            'textnowpaymenttron' => '',
            'iranpay1' => '',
            'iranpay2' => '',
            'iranpay3' => '',
            'tonpay' => '',
            'rialit' => '',
            'cubepay' => '',
            'blupal' => '',
            'variza' => '',
            'abangateway' => '',
            'atlaspay' => '',
            'zarinpal' => '',
            'textsnowpayment' => '',
            'textpaymentnotverify' => "",
            'text_star_telegram' => '',
            'text_extend' => '',
            'text_wgdashboard' => '',
            'text_Discount' => '',
            'miniapp_suggest_1' => '',
        );
        foreach ($rows as $item) {
            if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
                $datatextbot[$item['id_text']] = $item['text'];
            }
        }
        foreach ($datatextbot as $rxTbKey => $rxTbVal) {
            if (strpos((string) $rxTbKey, 'dyn_') === 0 && trim((string) $rxTbVal) === '') {
                unset($datatextbot[$rxTbKey]);
            }
        }
        return $datatextbot;
    }

    public static function renderStart(
        PDO $pdo, array &$datatextbot, array $user, $from_id, $username,
        $first_name, $last_name, $version,
    ): ?array {
        $time_Start = jdate('Y/m/d');
        $date_start = jdate('H:i:s', time());
        $time_string = "📆 $date_start → ⏰ $time_Start";
        $varable_start = [
            '{username}' => $username,
            '{first_name}' => $first_name,
            '{name}' => $first_name,
            '{last_name}' => $last_name,
            '{time}' => $time_string,
            '{version}' => $version
        ];
        $startPhone = (string) ($user['number'] ?? 'none');
        if ($startPhone === 'none' || $startPhone === '') {
            $startPhone = '🔴 ارسال نشده است 🔴';
        } elseif ($startPhone === 'confrim number by admin') {
            $startPhone = '✅ تایید شده توسط ادمین';
        }
        $startRegister = $user['register'] ?? null;
        $varable_start += [
            '{shanase}' => (string) $from_id,
            '{referral_code}' => htmlspecialchars((string) ($user['codeInvitation'] ?? ''), ENT_QUOTES, 'UTF-8'),
            '{phone}' => htmlspecialchars($startPhone, ENT_QUOTES, 'UTF-8'),
            '{register_date}' => is_numeric($startRegister) && (int) $startRegister > 0
                ? jdate('Y/m/d H:i:s', (int) $startRegister) : '',
            '{balance}' => number_format((float) ($user['Balance'] ?? 0), 0),
            '{affiliate_count}' => (int) ($user['affiliatescount'] ?? 0),
            '{group}' => ['f' => 'عادی', 'n' => 'نماینده', 'n2' => 'نمایندگی پیشرفته'][$user['agent'] ?? 'f'] ?? 'عادی',
            '{date_now}' => $time_Start,
            '{time_now}' => $date_start,
        ];
        // Only count orders/payments when the start template actually uses them.
        $rxDiagStartBeforeRaw = $datatextbot['text_start'];
        if (strpos($datatextbot['text_start'], '{order_count}') !== false) {
            $startOrderStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = :id_user AND name_product != 'سرویس تست' AND status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold')");
            $startOrderStmt->execute([':id_user' => $from_id]);
            $varable_start['{order_count}'] = (int) $startOrderStmt->fetchColumn();
        }
        if (strpos($datatextbot['text_start'], '{payment_count}') !== false) {
            $startPaymentStmt = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE id_user = :from_id AND payment_Status = 'paid'");
            $startPaymentStmt->execute([':from_id' => $from_id]);
            $varable_start['{payment_count}'] = (int) $startPaymentStmt->fetchColumn();
        }
        $datatextbot['text_start'] = strtr($datatextbot['text_start'], $varable_start);
        if (isset($rxDiagStartBeforeRaw) && method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_text') && method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_structure')) {
            $rxDiagStartRich = isset($datatextbot['richmsg.text_start']) ? (string) $datatextbot['richmsg.text_start'] : '';
            return [
                'text_raw' => $datatextbot['text_start'],
                'text_before_raw' => $rxDiagStartBeforeRaw,
                'structure_raw' => $rxDiagStartRich,
                'selection' => [
                    'template_key' => 'text_start',
                    'template_source' => 'textbot_row',
                    'rich_structure_key' => 'richmsg.text_start',
                    'rich_structure_present' => trim($rxDiagStartRich) !== '',
                    'render_input' => 'textbot_text_html',
                    'parse_mode' => 'html',
                    'send_call' => 'rx_send_banner_message',
                ],
            ];
        }
        return null;
    }
}
