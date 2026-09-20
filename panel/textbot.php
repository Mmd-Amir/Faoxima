<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/../re/rx/function/database_helpers_1.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}

fx_csrf_guard();


function faoxima_text_categories(): array {
    return [
        'sys'  => ['title' => 'سیستم و شروع',        'icon' => 'gear', 'keys' => ['text_start', 'text_roll', 'miniapp_suggest_1', 'text_account_info']],
        'srv'  => ['title' => 'سرویس‌ها',              'icon' => 'server', 'keys' => ['text_Purchased_services', 'text_usertest', 'crontest', 'textafterpay', 'dyn_purchase_subscription_link_line', 'textaftertext', 'textmanual', 'textselectlocation', 'text_extend', 'text_wgdashboard', 'text_service_detail', 'dyn_renewconfirm_queued_success']],
        'help' => ['title' => 'راهنما و پشتیبانی',     'icon' => 'message', 'keys' => ['text_fq', 'text_dec_fq', 'text_help', 'text_support', 'text_channel']],
        'fin'  => ['title' => 'مالی و خرید',           'icon' => 'coins', 'keys' => ['text_Add_Balance', 'text_sell', 'text_Tariff_list', 'text_dec_Tariff_list', 'accountwallet', 'text_pishinvoice', 'text_cart', 'text_cart_auto', 'text_Discount', 'text_wheel_luck', 'carttocart', 'textnowpayment', 'textsnowpayment', 'textnowpaymenttron', 'text_star_telegram', 'iranpay3', 'iranpay2', 'iranpay1', 'tonpay', 'cubepay', 'blupal', 'variza', 'atlaspay', 'tetrapay', 'zarinpal', 'textpaymentnotverify', 'dyn_public_log_btn_label', 'dyn_public_broadcast_new_sub_tpl', 'dyn_public_broadcast_renewal_tpl', 'dyn_public_broadcast_volume_topup_tpl', 'dyn_public_broadcast_time_extra_tpl', 'dyn_public_broadcast_wallet_deposit_tpl']],
        'ref'  => ['title' => 'زیرمجموعه و نمایندگی', 'icon' => 'users', 'keys' => ['text_affiliates', 'textrequestagent', 'textpanelagent', 'text_request_agent_dec']],
        'err_general' => ['title' => 'پیام‌های عمومی خطا', 'icon' => 'circle-exclamation', 'keys' => ['dyn_errors_verification_failed', 'dyn_errors_restart_process', 'dyn_errors_concurrency_insufficient_stock', 'dyn_errors_panel_connection_error', 'dyn_errors_button_disabled', 'dyn_errors_feature_unavailable', 'dyn_errors_feature_unavailable_short', 'dyn_errors_feature_currently_unavailable_nodot', 'dyn_errors_service_found_count', 'dyn_errors_service_not_found', 'dyn_errors_view_account_unavailable', 'dyn_errors_qr_unavailable', 'dyn_errors_service_deleted', 'dyn_errors_config_read_error', 'dyn_errors_panel_unavailable_dot', 'dyn_errors_config_read_from_panel_error', 'dyn_errors_usage_stats_failed', 'dyn_errors_server_list_failed', 'dyn_errors_not_connected_change_status']],
        'err_purchase' => ['title' => 'خطاهای خرید و تمدید', 'icon' => 'cart-shopping', 'keys' => ['dyn_errors_renewal_failed_restart', 'dyn_errors_renewal_support_error', 'dyn_errors_purchase_info_incomplete', 'dyn_errors_panel_not_found', 'dyn_errors_panel_unavailable_choose_other', 'dyn_errors_extra_volume_purchase_error', 'dyn_errors_location_change_limit_reached', 'dyn_errors_min_max_deposit_amount', 'dyn_errors_stock_depleted_no_charge', 'dyn_errors_stock_extend_success', 'dyn_errors_invalid_subscription_link', 'dyn_errors_extend_unavailable_panel', 'dyn_errors_not_connected_extend', 'dyn_errors_extend_current_plan_unavailable', 'dyn_errors_extend_restart_process', 'dyn_errors_exclusive_discount_conflict', 'dyn_errors_discount_expired', 'dyn_errors_discount_applied', 'dyn_errors_link_change_service_disabled', 'dyn_errors_extra_volume_unavailable_panel', 'dyn_errors_extra_volume_restart_process', 'dyn_errors_purchase_failed_restart', 'dyn_errors_location_change_unavailable', 'dyn_errors_generic_restart_process', 'dyn_errors_restart_buy_process_short', 'dyn_errors_transfer_panel_unavailable', 'dyn_errors_transfer_unused_config_only', 'dyn_errors_removal_request_thanks', 'dyn_errors_extra_time_unavailable_panel', 'dyn_errors_extra_time_restart_process', 'dyn_errors_service_removed', 'dyn_errors_service_removal_unavailable', 'dyn_errors_removal_reason_prompt', 'dyn_errors_test_service_unavailable', 'dyn_errors_min_bulk_purchase_balance', 'dyn_errors_section_disabled', 'dyn_errors_discount_code_not_applicable', 'dyn_errors_stock_low_purchase_blocked', 'dyn_errors_queued_renewal_exists']],
        'tickets' => ['title' => 'تیکت پشتیبانی', 'icon' => 'ticket', 'keys' => ['dyn_tickets_select_category', 'dyn_tickets_select_department', 'dyn_tickets_ask_subject', 'dyn_tickets_operation_canceled', 'dyn_tickets_invalid_subject_text', 'dyn_tickets_ask_media_new', 'dyn_tickets_ask_media_reply', 'dyn_tickets_media_yes', 'dyn_tickets_media_no', 'dyn_tickets_cancel', 'dyn_tickets_ask_media_count', 'dyn_tickets_ask_media_upload', 'dyn_tickets_invalid_media_choice', 'dyn_tickets_invalid_media_type', 'dyn_tickets_media_received', 'dyn_tickets_ask_body_text', 'dyn_tickets_ask_body_text_short', 'dyn_tickets_not_available', 'dyn_tickets_reply_recorded', 'dyn_tickets_invalid_body_text', 'dyn_tickets_created', 'dyn_tickets_closed_locked', 'dyn_tickets_no_media', 'dyn_tickets_closed_confirm']],
        'wallet_ext' => ['title' => 'کیف پول و پرداخت', 'icon' => 'wallet', 'keys' => ['dyn_wallet_select_amount', 'dyn_wallet_recheck_canceled', 'dyn_wallet_amount_not_numeric', 'dyn_wallet_rate_unavailable', 'dyn_wallet_calculated_amount_invalid', 'dyn_wallet_hash_not_found', 'dyn_wallet_ask_receipt_photo', 'dyn_wallet_incomplete_info', 'dyn_wallet_hash_already_used', 'dyn_wallet_internal_error_retry', 'dyn_wallet_invalid_charge_amount', 'dyn_wallet_discount_not_applicable_zero', 'dyn_wallet_no_active_card', 'dyn_wallet_select_verified_card', 'dyn_wallet_card_auth_active_notice', 'dyn_wallet_queue_too_busy', 'dyn_wallet_hashchecker_not_loaded', 'dyn_wallet_no_wallet_address_configured', 'dyn_wallet_charge_amount_undetectable', 'dyn_wallet_invoice_not_found', 'dyn_wallet_invoice_not_pending', 'dyn_wallet_prompt_amount_prompt', 'dyn_wallet_prompt_amount_out_of_range', 'dyn_wallet_prompt_debt_must_pay_first', 'dyn_wallet_prompt_has_discount_yes', 'dyn_wallet_prompt_has_discount_no', 'dyn_wallet_prompt_ask_has_discount', 'dyn_wallet_prompt_auth_success']],
        'ref_ext' => ['title' => 'رفرال، پاداش و گردونه', 'icon' => 'gift', 'keys' => ['dyn_referral_welcome_alert', 'dyn_referral_inviter_reward_alert', 'dyn_referral_commission_credited', 'dyn_rewards_points_earned', 'dyn_rewards_cashback_gift', 'dyn_affiliates_extra_section_disabled', 'dyn_affiliates_extra_not_affiliate_of_anyone', 'dyn_affiliates_extra_gift_already_received', 'dyn_affiliates_extra_gift_credited_inviter', 'dyn_affiliates_extra_gift_activated', 'dyn_wheel_button_disabled_you', 'dyn_wheel_no_purchase_users_only', 'dyn_wheel_price_unavailable']],
        'misc' => ['title' => 'سایر (گروه/ادمین)', 'icon' => 'user-tag', 'keys' => ['dyn_group_setup_topic_not_active', 'dyn_group_setup_insufficient_access', 'dyn_group_setup_chat_id_received']],
        'cron' => ['title' => 'اعلانات کرون', 'icon' => 'hourglass', 'keys' => ['dyn_cron_volume_warning_tpl', 'dyn_cron_volume_warning_report_tpl', 'dyn_cron_time_warning_tpl', 'dyn_cron_time_warning_report_tpl', 'dyn_cron_removal_notice_tpl', 'dyn_cron_removal_report_tpl', 'dyn_cron_removal_volume_notice_tpl', 'dyn_cron_removal_volume_report_tpl', 'dyn_cron_extend_service_btn', 'dyn_gift_all_done', 'dyn_gift_error_report_tpl', 'dyn_gift_failed_users_suffix_tpl', 'dyn_uptime_api_access_error_tpl', 'dyn_uptime_api_access_restored_tpl', 'dyn_uptime_xray_stopped_tpl', 'dyn_uptime_xray_restored_tpl', 'dyn_uptime_panel_not_connected_tpl', 'dyn_queued_renewal_activated_tpl']],
        'jsontext_users' => ['title' => 'متن‌های کاربر (text.json)', 'icon' => 'comment-dots', 'keys' => []],
        'jsontext_admin' => ['title' => 'متن‌های ادمین (text.json)', 'icon' => 'user-shield', 'keys' => []],
    ];
}

function faoxima_jsontext_prefix_map(): array {
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

function faoxima_jsontext_group(string $key): ?string {
    if (strpos($key, 'jsontext.') !== 0) {
        return null;
    }
    $path = strtolower(substr($key, strlen('jsontext.')));
    foreach (faoxima_jsontext_prefix_map() as $prefix => $cat) {
        if (strpos($path, $prefix) === 0) {
            return $cat;
        }
    }
    return 'jsontext_users';
}

function faoxima_text_has_placeholder(string $text): bool {
    return (bool) preg_match('/%(\d+\$)?[sd]|\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $text);
}

function faoxima_text_group(string $key): string {
    $k = strtolower($key);
    foreach (faoxima_text_categories() as $cat => $info) {
        foreach ($info['keys'] as $known) {
            if ($k === strtolower($known)) return $cat;
        }
    }
    $jsontextCat = faoxima_jsontext_group($k);
    if ($jsontextCat !== null) return $jsontextCat;
    if (strpos($k, 'zarinp') !== false || strpos($k, 'pay') !== false || strpos($k, 'cart') !== false || strpos($k, 'star') !== false)
        return 'fin';
    if (strpos($k, 'agent') !== false)
        return 'ref';
    return 'other';
}

$flash = ['ok' => '', 'err' => ''];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'save') {
        $newValues = $_POST['t'] ?? [];
        if (!is_array($newValues)) $newValues = [];


        $cur = [];
        $r = $pdo->query("SELECT id_text, text FROM textbot");
        if ($r) {
            foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cur[(string)$row['id_text']] = (string)$row['text'];
            }
        }

        $updated = 0;
        foreach ($newValues as $key => $val) {
            $key = (string)$key;
            $val = (string)$val;
            if (isset($cur[$key]) && $cur[$key] === $val) continue;
            try {
                $upd = $pdo->prepare("INSERT INTO textbot (id_text, text) VALUES (:k, :v) ON DUPLICATE KEY UPDATE text = :v2");
                $upd->execute([':k' => $key, ':v' => $val, ':v2' => $val]);
                $updated++;
            } catch (\Throwable $e) {
                $flash['err'] = $e->getMessage();
                error_log('[panel/textbot] update failed for ' . $key . ': ' . $e->getMessage());
            }
        }
        if ($updated > 0) {
            faoxima_bust_bot_selectcache('textbot');
            if (function_exists('clearSelectCache')) {
                clearSelectCache('textbot');
            }
        }
        header('Location: textbot.php?saved=' . $updated);
        exit;
    }
    elseif ($action === 'add') {
        $key = trim((string)($_POST['new_key'] ?? ''));
        $val = (string)($_POST['new_val'] ?? '');


        $errors = [];
        if ($key === '') {
            $errors[] = 'کلید نمی‌تواند خالی باشد.';
        } elseif (!preg_match('/^[A-Za-z0-9_ـ]{2,100}$/u', $key)) {
            $errors[] = 'کلید فقط می‌تواند شامل حروف، عدد و آندرلاین باشد (۲ تا ۱۰۰ کاراکتر).';
        } elseif (mb_strlen($val) > 4000) {
            $errors[] = 'متن بیش از حد طولانی است (حداکثر ۴۰۰۰ کاراکتر).';
        }

        if (!empty($errors)) {
            $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
        } else {
            try {
                $ins = $pdo->prepare("INSERT INTO textbot (id_text, text) VALUES (:k, :v)
                                      ON DUPLICATE KEY UPDATE text = VALUES(text)");
                $ins->execute([':k' => $key, ':v' => $val]);
                faoxima_bust_bot_selectcache('textbot');
                if (function_exists('clearSelectCache')) {
                    clearSelectCache('textbot');
                }
                header('Location: textbot.php?added=' . urlencode($key));
                exit;
            } catch (\Throwable $e) {
                $flash['err'] = 'افزودن ناموفق: ' . $e->getMessage();
            }
        }
    }
    elseif ($action === 'delete') {
        $key = (string)($_POST['key'] ?? '');
        if ($key !== '') {
            try {
                $d = $pdo->prepare("DELETE FROM textbot WHERE id_text = :k");
                $d->execute([':k' => $key]);
                faoxima_bust_bot_selectcache('textbot');
                if (function_exists('clearSelectCache')) {
                    clearSelectCache('textbot');
                }
                header('Location: textbot.php?deleted=' . urlencode($key));
                exit;
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
    }
}


$rows = [];
try {
    $r = $pdo->query("SELECT id_text, text FROM textbot ORDER BY id_text ASC");
    if ($r) $rows = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $flash['err'] = 'بارگذاری متن‌ها ناموفق: ' . $e->getMessage();
}


$grouped = [];
foreach ($rows as $row) {
    $g = faoxima_text_group((string)$row['id_text']);
    if (!isset($grouped[$g])) $grouped[$g] = [];
    $grouped[$g][] = $row;
}

$categories = faoxima_text_categories();
$tabOrder = ['sys', 'srv', 'help', 'fin', 'ref', 'err_general', 'err_purchase', 'tickets', 'wallet_ext', 'ref_ext', 'misc', 'cron', 'jsontext_users', 'jsontext_admin', 'other'];
$orderedGrouped = [];
foreach ($tabOrder as $g) {
    if (isset($grouped[$g])) { $orderedGrouped[$g] = $grouped[$g]; unset($grouped[$g]); }
}
foreach ($grouped as $g => $items) $orderedGrouped[$g] = $items;

function faoxima_tab_title(string $tab, array $categories): string {
    return $categories[$tab]['title'] ?? 'سایر';
}

function faoxima_tab_icon(string $tab, array $categories): string {
    return $categories[$tab]['icon'] ?? 'more-horizontal';
}

$savedNum = isset($_GET['saved']) ? (int)$_GET['saved'] : -1;
$addedKey = isset($_GET['added']) ? (string)$_GET['added'] : '';
$delKey   = isset($_GET['deleted']) ? (string)$_GET['deleted'] : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>متن‌های ربات | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat47">
    <script src="js/theme.js?v=flat5" defer>

</script>
    <style>
        .text-grp { margin-bottom: 22px; }
        .text-grp__title {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 700;
            margin: 0 4px 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .text-grp__title::before {
            content: "";
            width: 4px; height: 16px;
            background: var(--accent);
            border-radius: 4px;
        }
        .text-row {
            background: var(--surface-2);
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
            display: grid;
            grid-template-columns: 220px 1fr auto;
            gap: 14px;
            align-items: start;
            transition: border-color .15s ease;
        }
        .text-row:hover { border-color: var(--border-mid); }
        .text-key {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--accent);
            direction: ltr;
            text-align: left;
            padding-top: 8px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .text-key__id {
            word-break: break-all;
        }
        .text-key__badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--color-warning-soft);
            border: 1px solid var(--color-warning);
            color: var(--color-warning);
            font-size: 10px;
            font-family: 'Vazirmatn', sans-serif;
            white-space: nowrap;
        }
        .text-row textarea {
            width: 100%;
            background: var(--surface-3);
            border: 1px solid var(--border-mid);
            border-radius: 8px;
            color: var(--text-main);
            padding: 9px 12px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 13.5px;
            min-height: 44px;
            resize: vertical;
            line-height: 1.7;
        }
        .text-row textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        .text-row textarea[readonly] {
            cursor: pointer;
            resize: none;
        }
        .text-row textarea[readonly]:hover {
            border-color: var(--accent);
        }
        .text-row textarea.changed {
            border-color: var(--color-warning);
            background: var(--color-warning-soft);
        }
        @media (max-width: 768px) {
            .text-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .text-key { padding-top: 0; font-size: 11px; }
        }
        .save-bar {
            position: sticky;
            bottom: 0;
            margin-top: 24px;
            padding: 14px 0;
            background: linear-gradient(180deg, transparent, var(--bg-grad-bot) 30%);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            z-index: 5;
        }
        .filter-bar {
            display: flex; gap: 10px; flex-wrap: wrap;
            align-items: center;
            margin-bottom: 16px;
        }
        .filter-bar__search {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 420px;
            min-width: 0;
        }
        .filter-bar input {
            flex: 1 1 240px;
            min-width: 0;
            padding: 10px 14px;
            background: var(--surface-2);
            border: 1px solid var(--border-mid);
            border-radius: 10px;
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 13.5px;
        }
        .filter-bar input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        .search-scope {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px;
            flex: 0 1 360px;
            padding: 4px;
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            background: var(--surface-2);
        }
        .search-scope__btn {
            min-height: 36px;
            padding: 7px 12px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--text-muted);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 12.5px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            transition: .16s ease;
        }
        .search-scope__btn:hover { color: var(--text-main); background: var(--surface-3); }
        .search-scope__btn.active {
            color: var(--accent);
            background: var(--accent-soft);
            box-shadow: inset 0 0 0 1px var(--accent-mid);
        }
        .text-row.hidden { display: none; }
        .cat-select {
            position: relative;
            max-width: 320px;
            margin-bottom: 20px;
        }
        .cat-select__box {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 14px;
            border-radius: 13px;
            border: 1px solid var(--border-soft);
            background: var(--surface-2);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.18s;
        }
        .cat-select__box:hover,
        .cat-select.open .cat-select__box {
            border-color: var(--accent-mid);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .cat-select__box .svg-icon:first-child { width: 18px; height: 18px; color: var(--text-dim); flex-shrink: 0; }
        .cat-select__label { flex: 1 1 auto; }
        .cat-select__chevron { width: 16px; height: 16px; color: var(--text-dim); transition: transform .16s ease; flex-shrink: 0; }
        .cat-select.open .cat-select__chevron { transform: rotate(180deg); }
        .cat-select__dd {
            position: absolute;
            top: calc(100% + 8px);
            right: 0; left: 0;
            background: var(--surface-2);
            border: 1px solid var(--border-soft);
            border-radius: 14px;
            box-shadow: var(--shadow-2);
            padding: 8px;
            max-height: min(340px, calc(100vh - 180px));
            overflow-y: auto;
            opacity: 0; transform: translateY(-6px);
            pointer-events: none;
            transition: 0.16s ease;
            z-index: 1100;
        }
        .cat-select.open .cat-select__dd { opacity: 1; transform: translateY(0); pointer-events: auto; }
        .cat-select__item {
            display: flex; align-items: center; gap: 10px;
            width: 100%;
            padding: 9px 11px;
            border: none;
            background: transparent;
            border-radius: 10px;
            color: var(--text-main); font-size: 13px;
            font-family: 'Vazirmatn', sans-serif;
            cursor: pointer;
            text-align: right;
        }
        .cat-select__item:hover { background: var(--accent-soft); color: var(--accent); }
        .cat-select__item.active { background: var(--accent-soft); color: var(--accent); }
        .cat-select__item .svg-icon { width: 16px; height: 16px; flex-shrink: 0; }
        .cat-select__item span:first-of-type { flex: 1 1 auto; }
        .text-tabpanel { display: none; }
        .text-tabpanel.active { display: block; }
        .text-tabpanel.search-mode { display: block; }
        .text-tabpanel.search-mode.search-empty { display: none; }
        .cat-select.search-mode { opacity: .5; pointer-events: none; }
        .text-tabpanel__searchhead {
            display: none;
            align-items: center;
            gap: 8px;
            font-family: 'Vazirmatn', system-ui, monospace;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            padding: 10px 4px;
            border-bottom: 1px solid var(--border-soft);
            margin-bottom: 10px;
        }
        .text-tabpanel.search-mode .text-tabpanel__searchhead { display: flex; }
        @media (max-width: 768px) {
            .filter-bar__search,
            .search-scope,
            .cat-select { width: 100%; max-width: none; flex-basis: 100%; }
            .search-scope__btn { white-space: normal; }
        }
        @media (max-width: 420px) {
            .filter-bar__search { flex-wrap: wrap; }
            .filter-bar__search .chip { margin-inline-start: auto; }
            .search-scope { grid-template-columns: 1fr; }
        }
        .modal-box--wide { max-width: 640px; }
        #editTextArea {
            width: 100%;
            min-height: 260px;
            line-height: 1.8;
            resize: vertical;
        }
        .text-format-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin: 4px 0 10px;
        }
        .fmt-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            background: var(--surface-2);
            border: 1px solid var(--border-mid);
            border-radius: 8px;
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.15s ease;
            white-space: nowrap;
        }
        .fmt-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-soft);
        }
        .fmt-btn:active { transform: translateY(1px); }
        .fmt-btn__icon {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        .fmt-btn__icon--badge {
            width: 11px;
            height: 11px;
            margin-inline-start: -4px;
        }
        @media (max-width: 560px) {
            .text-format-bar {
                gap: 6px;
            }
            .fmt-btn {
                flex: 1 1 calc(50% - 6px);
                justify-content: center;
                padding: 9px 8px;
                font-size: 12px;
            }
            .fmt-btn__label {
                white-space: normal;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('text', 'svg-icon svg-lg'); ?>
                        ویرایش متن‌های ربات
                    </div>
                    <div class="page-head__sub">تمام برچسب‌های نمایشی و پیام‌های ربات در یک‌جا</div>
                </div>
            </div>

            <?php if ($savedNum > 0): ?>
                <div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); padding:12px 16px; border-radius:10px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span><?php echo $savedNum; ?> متن به‌روزرسانی شد.</span>
                </div>
            <?php elseif ($savedNum === 0): ?>
                <div class="alert" style="background:var(--surface-3); border:1px solid var(--border-mid); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    تغییری انجام نشد.
                </div>
            <?php endif; ?>
            <?php if ($addedKey !== ''): ?>
                <div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    کلید جدید <code><?php echo htmlspecialchars($addedKey, ENT_QUOTES); ?></code> افزوده شد.
                </div>
            <?php endif; ?>
            <?php if ($delKey !== ''): ?>
                <div class="alert" style="background:var(--color-warning-soft); border:1px solid var(--color-warning); color:var(--color-warning); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    کلید <code><?php echo htmlspecialchars($delKey, ENT_QUOTES); ?></code> حذف شد.
                </div>
            <?php endif; ?>
            <?php if ($flash['err']): ?>
                <div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    <?php echo htmlspecialchars($flash['err'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="filter-bar">
                <div class="filter-bar__search">
                    <input type="search" id="searchBox" placeholder="جستجو در کلید، متن یا دسته‌بندی…">
                    <span class="chip" id="searchResultCount"><?php echo count($rows); ?> کلید</span>
                </div>
                <div class="search-scope" role="group" aria-label="محدوده جستجو">
                    <button type="button" class="search-scope__btn active" data-search-scope="global" aria-pressed="true">جست‌وجوی سراسری</button>
                    <button type="button" class="search-scope__btn" data-search-scope="category" aria-pressed="false">جست‌وجوی دسته‌بندی‌شده</button>
                </div>
            </div>

            <form method="POST" action="textbot.php" id="textForm" autocomplete="off">
                <?php echo fx_csrf_field(); ?>
                <input type="hidden" name="_action" value="save">

                <div class="cat-select" id="catSelect">
                    <button type="button" class="cat-select__box" id="catSelectBox">
                        <?php echo icon('list', 'svg-icon'); ?>
                        <span class="cat-select__label" id="catSelectLabel"></span>
                        <?php echo icon('chevron-down', 'svg-icon cat-select__chevron'); ?>
                    </button>
                    <div class="cat-select__dd" id="catSelectDd" role="listbox">
                        <?php $rxFirstTab = true; foreach ($orderedGrouped as $tabKey => $items): ?>
                            <button type="button" class="cat-select__item<?php echo $rxFirstTab ? ' active' : ''; ?>" data-tab="tab-<?php echo htmlspecialchars($tabKey, ENT_QUOTES); ?>" role="option">
                                <?php echo icon(faoxima_tab_icon($tabKey, $categories), 'svg-icon'); ?>
                                <span><?php echo htmlspecialchars(faoxima_tab_title($tabKey, $categories), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="chip"><?php echo count($items); ?></span>
                            </button>
                        <?php $rxFirstTab = false; endforeach; ?>
                    </div>
                </div>

                <?php $rxFirstPanel = true; foreach ($orderedGrouped as $tabKey => $items): ?>
                    <div class="text-tabpanel<?php echo $rxFirstPanel ? ' active' : ''; ?>" id="tab-<?php echo htmlspecialchars($tabKey, ENT_QUOTES); ?>">
                        <div class="text-tabpanel__searchhead">
                            <?php echo icon(faoxima_tab_icon($tabKey, $categories), 'svg-icon'); ?>
                            <?php echo htmlspecialchars(faoxima_tab_title($tabKey, $categories), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php foreach ($items as $row):
                            $k = (string)$row['id_text'];
                            $v = (string)$row['text'];
                        ?>
                            <div class="text-row"
                                 data-search-key="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
                                 data-search-text="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>"
                                 data-search-category="<?php echo htmlspecialchars(faoxima_tab_title($tabKey, $categories), ENT_QUOTES, 'UTF-8'); ?>">
                                <code class="text-key">
                                    <span class="text-key__id"><?php echo htmlspecialchars($k, ENT_QUOTES); ?></span>
                                    <?php if (faoxima_text_has_placeholder($v)): ?>
                                        <span class="text-key__badge" title="این متن دارای متغیر جایگزین‌شونده است؛ در ویرایش حذف نکنید">
                                            <?php echo icon('circle-exclamation', 'svg-icon'); ?> متغیر
                                        </span>
                                    <?php endif; ?>
                                </code>
                                <textarea name="t[<?php echo htmlspecialchars($k, ENT_QUOTES); ?>]"
                                        id="ta-<?php echo htmlspecialchars($k, ENT_QUOTES); ?>"
                                        class="text-edit-source"
                                        data-key="<?php echo htmlspecialchars($k, ENT_QUOTES); ?>"
                                        data-orig="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>"
                                        rows="1" readonly autocomplete="off" onfocus="openTextEditor(this.dataset.key)" onclick="openTextEditor(this.dataset.key)"><?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div style="display:flex; flex-direction:column; gap:6px;">
                                    <button type="button" class="btn btn-sm btn-soft-danger"
                                            onclick='deleteKey(<?php echo json_encode($k, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_QUOT); ?>)'
                                            title="حذف این کلید">
                                        <?php echo icon('trash', 'svg-icon'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php $rxFirstPanel = false; endforeach; ?>

                <div class="save-bar">
                    <span id="changeCount" style="align-self:center; color:var(--text-muted); font-size:13px; margin-inline-end:auto;">۰ تغییر در انتظار ذخیره</span>
                    <button type="submit" class="btn btn-primary">
                        <?php echo icon('check', 'svg-icon svg-sm'); ?>
                        <span>ذخیره تغییرات</span>
                    </button>
                </div>
            </form>

        </div>
    </section>
</section>


<div id="modal-add-text" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">افزودن کلید متن جدید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-text')">&times;</button>
        </div>
        <form method="POST" action="textbot.php">
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="_action" value="add">
            <div class="form-group">
                <label class="form-label">کلید (انگلیسی، بدون فاصله)</label>
                <input type="text" name="new_key" class="form-control" style="direction:ltr; font-family:'JetBrains Mono', monospace;" placeholder="text_custom_button" required pattern="[A-Za-z0-9_ـ]+">
            </div>
            <div class="form-group">
                <label class="form-label">متن نمایش (فارسی)</label>
                <textarea name="new_val" class="form-control" rows="3" placeholder="مثلاً: 🔥 پیشنهاد ویژه"></textarea>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-text')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">افزودن</button>
            </div>
        </form>
    </div>
</div>


<div id="modal-edit-text" class="modal-overlay">
    <div class="modal-box modal-box--wide">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش متن — <code id="editTextKeyLabel"></code></span>
            <button type="button" class="modal-close" onclick="closeTextEditor()">&times;</button>
        </div>
        <div class="text-format-bar" role="toolbar" aria-label="قالب‌بندی متن">
            <button type="button" class="fmt-btn" data-tag="blockquote" title="بخش انتخاب‌شده را به‌صورت نقل‌قول نمایش بده">
                <svg class="fmt-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="4" x2="4" y2="20"/><line x1="9" y1="7" x2="20" y2="7"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="17" x2="16" y2="17"/></svg>
                <span class="fmt-btn__label">نقل‌قول</span>
            </button>
            <button type="button" class="fmt-btn" data-tag="blockquote expandable" title="نقل‌قول جمع‌شونده برای متن طولانی">
                <svg class="fmt-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="4" x2="4" y2="20"/><line x1="9" y1="7" x2="20" y2="7"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="17" x2="16" y2="17"/></svg>
                <svg class="fmt-btn__icon fmt-btn__icon--badge" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                <span class="fmt-btn__label">نقل‌قول جمع‌شونده</span>
            </button>
            <button type="button" class="fmt-btn" data-tag="tg-spoiler" title="بخش انتخاب‌شده را به‌صورت اسپویلر مخفی کن">
                <svg class="fmt-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                <span class="fmt-btn__label">اسپویلر</span>
            </button>
            <button type="button" class="fmt-btn" data-tag="code" title="بخش انتخاب‌شده را به‌صورت کد قابل کپی نمایش بده">
                <svg class="fmt-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                <span class="fmt-btn__label">کد</span>
            </button>
        </div>
        <div class="form-group">
            <textarea id="editTextArea" class="form-control" rows="10"></textarea>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeTextEditor()">لغو</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="confirmTextEditor()">تایید</button>
        </div>
    </div>
</div>


<form method="POST" action="textbot.php" id="deleteForm" style="display:none;">
    <?php echo fx_csrf_field(); ?>
    <input type="hidden" name="_action" value="delete">
    <input type="hidden" name="key" id="deleteKey">
</form>

<script>
function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); }
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

function deleteKey(k) {
    if (!confirm('کلید «' + k + '» و متن آن حذف شود؟')) return;
    document.getElementById('deleteKey').value = k;
    document.getElementById('deleteForm').submit();
}

var textEditorCurrentKey = null;
function openTextEditor(key) {
    var modal = document.getElementById('modal-edit-text');
    if (textEditorCurrentKey === key && modal && modal.classList.contains('active')) return;
    var src = document.getElementById('ta-' + key);
    if (!src) return;
    textEditorCurrentKey = key;
    document.getElementById('editTextKeyLabel').textContent = key;
    document.getElementById('editTextArea').value = src.value;
    openModal('modal-edit-text');
    document.getElementById('editTextArea').focus();
}
function closeTextEditor() {
    closeModal('modal-edit-text');
    textEditorCurrentKey = null;
}

var FMT_PLACEHOLDERS = {
    'blockquote': 'متن نقل‌قول',
    'blockquote expandable': 'متن نقل‌قول',
    'tg-spoiler': 'متن مخفی',
    'code': 'متن کد'
};
function wrapTextSelection(tagWithAttrs) {
    var ta = document.getElementById('editTextArea');
    if (!ta) return;
    var tagName = tagWithAttrs.split(' ')[0];
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    var value = ta.value;
    var hasSelection = end > start;
    var selected = hasSelection ? value.slice(start, end) : (FMT_PLACEHOLDERS[tagWithAttrs] || '');
    var openTag = '<' + tagWithAttrs + '>';
    var closeTag = '</' + tagName + '>';
    ta.value = value.slice(0, start) + openTag + selected + closeTag + value.slice(end);
    var caretStart = start + openTag.length;
    var caretEnd = caretStart + selected.length;
    ta.focus();
    ta.setSelectionRange(caretStart, caretEnd);
    ta.dispatchEvent(new Event('input', { bubbles: true }));
}
document.querySelectorAll('.fmt-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        wrapTextSelection(btn.dataset.tag);
    });
});
function confirmTextEditor() {
    if (!textEditorCurrentKey) return;
    var src = document.getElementById('ta-' + textEditorCurrentKey);
    if (src) {
        src.value = document.getElementById('editTextArea').value;
        src.dispatchEvent(new Event('input', { bubbles: true }));
    }
    closeTextEditor();
}


(function () {
    var counter = document.getElementById('changeCount');
    var areas = document.querySelectorAll('#textForm textarea');
    function persianNum(n){ return String(n).replace(/\d/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'[+d]; }); }
    function updateCount() {
        var n = 0;
        areas.forEach(function(t){
            if (t.value !== t.dataset.orig) { t.classList.add('changed'); n++; }
            else t.classList.remove('changed');
        });
        counter.textContent = persianNum(n) + ' تغییر در انتظار ذخیره';
    }
    function autogrow(t){
        t.style.height = 'auto';
        t.style.height = Math.max(44, t.scrollHeight) + 'px';
    }
    areas.forEach(function (t) {
        autogrow(t);
        t.addEventListener('input', function () { autogrow(t); updateCount(); });
    });

    var textForm = document.getElementById('textForm');
    if (textForm) {
        textForm.addEventListener('submit', function () {
            areas.forEach(function (t) {
                if (t.value === t.dataset.orig) {
                    t.disabled = true;
                }
            });
        });
    }
    window.addEventListener('pageshow', function () {
        areas.forEach(function (t) {
            t.disabled = false;
        });
    });

    var catSelect = document.getElementById('catSelect');
    var catSelectBox = document.getElementById('catSelectBox');
    var catSelectLabel = document.getElementById('catSelectLabel');
    var items = document.querySelectorAll('.cat-select__item');
    var panels = document.querySelectorAll('.text-tabpanel');

    function selectCategory(item) {
        items.forEach(function (i) { i.classList.remove('active'); });
        panels.forEach(function (p) { p.classList.remove('active'); });
        item.classList.add('active');
        var panel = document.getElementById(item.dataset.tab);
        if (panel) panel.classList.add('active');
        catSelectLabel.textContent = item.querySelector('span:first-of-type').textContent;
        if (search) applySearch();
    }

    if (items.length) selectCategory(items[0]);

    catSelectBox.addEventListener('click', function (ev) {
        ev.stopPropagation();
        catSelect.classList.toggle('open');
    });
    items.forEach(function (item) {
        item.addEventListener('click', function () {
            selectCategory(item);
            catSelect.classList.remove('open');
        });
    });
    document.addEventListener('click', function (ev) {
        if (!catSelect.contains(ev.target)) catSelect.classList.remove('open');
    });

    var search = document.getElementById('searchBox');
    var searchResultCount = document.getElementById('searchResultCount');
    var searchScopeButtons = document.querySelectorAll('[data-search-scope]');
    var rows = document.querySelectorAll('.text-row');
    var searchScope = 'global';
    function normalizeSearchText(s) {
        return String(s || '')
            .toLowerCase()
            .replace(/[يى]/g, 'ی')
            .replace(/ك/g, 'ک')
            .replace(/[أإآ]/g, 'ا')
            .replace(/ة/g, 'ه')
            .replace(/[\s‌_-]+/g, '');
    }
    rows.forEach(function (r) {
        r._searchFields = [
            normalizeSearchText(r.dataset.searchKey),
            normalizeSearchText(r.dataset.searchText)
        ];
    });
    function applySearch() {
        var q = search.value.trim();
        var normalizedQuery = normalizeSearchText(q);
        var globalSearch = !!q && searchScope === 'global';
        var activePanel = document.querySelector('.text-tabpanel.active');
        catSelect.classList.toggle('search-mode', globalSearch);
        panels.forEach(function (p) { p.classList.toggle('search-mode', globalSearch); });
        var matchCounts = {};
        var visibleCount = 0;
        rows.forEach(function (r) {
            var rowPanel = r.closest('.text-tabpanel');
            var inScope = searchScope === 'global' || rowPanel === activePanel;
            var match = !q || inScope && r._searchFields.some(function (field) {
                return field.indexOf(normalizedQuery) !== -1;
            });
            r.classList.toggle('hidden', !match);
            if (match && q) {
                visibleCount++;
                var panelId = rowPanel.id;
                matchCounts[panelId] = (matchCounts[panelId] || 0) + 1;
            }
        });
        panels.forEach(function (p) {
            p.classList.toggle('search-empty', globalSearch && !matchCounts[p.id]);
        });
        searchResultCount.textContent = (q ? visibleCount : rows.length) + ' کلید';
        search.placeholder = searchScope === 'global'
            ? 'جستجو در کلید، متن یا دسته‌بندی…'
            : 'جستجو در دسته‌بندی جاری…';
    }
    search.addEventListener('input', applySearch);
    searchScopeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            searchScope = button.dataset.searchScope;
            searchScopeButtons.forEach(function (item) {
                var active = item === button;
                item.classList.toggle('active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            applySearch();
        });
    });
})();
</script>
</body>
</html>
