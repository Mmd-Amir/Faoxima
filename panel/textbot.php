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
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/textbot_edge.php';
require_once __DIR__ . '/../re/rx/function/tonpay_templates.php';
require_once __DIR__ . '/../re/rx/function/custom_duration_pricing.php';
require_once __DIR__ . '/../re/rx/function/database_helpers_1.php';
$textDefaults = ['text_custom_price_summary' => fx_custom_price_text_default()];

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
        'custom_pricing' => ['title' => 'متن قیمت‌گذاری هر گیگ', 'icon' => 'coins', 'keys' => ['text_custom_price_summary']],
        'sys'  => ['title' => 'سیستم و شروع',        'icon' => 'gear', 'keys' => ['text_start', 'text_roll', 'miniapp_suggest_1', 'text_miniapp_button', 'text_account_info']],
        'srv'  => ['title' => 'سرویس‌ها',              'icon' => 'server', 'keys' => ['text_Purchased_services', 'text_usertest', 'crontest', 'textafterpay', 'dyn_purchase_subscription_link_line', 'textaftertext', 'textmanual', 'textselectlocation', 'text_extend', 'text_wgdashboard', 'text_service_detail', 'dyn_renewconfirm_queued_success', 'dyn_renewal_success_same_link']],
        'help' => ['title' => 'راهنما و پشتیبانی',     'icon' => 'message', 'keys' => ['text_fq', 'text_dec_fq', 'text_help', 'text_support', 'text_channel']],
        'fin'  => ['title' => 'مالی و خرید',           'icon' => 'coins', 'keys' => ['text_Add_Balance', 'text_sell', 'text_Tariff_list', 'text_dec_Tariff_list', 'accountwallet', 'text_pishinvoice', 'text_cart', 'text_cart_auto', 'text_Discount', 'text_wheel_luck', 'carttocart', 'textnowpayment', 'textsnowpayment', 'textnowpaymenttron', 'text_star_telegram', 'iranpay3', 'iranpay2', 'iranpay1', 'tonpay', 'cubepay', 'blupal', 'variza', 'abangateway', 'atlaspay', 'zarinpal', 'textpaymentnotverify', 'dyn_public_log_btn_label', 'dyn_public_broadcast_new_sub_tpl', 'dyn_public_broadcast_renewal_tpl', 'dyn_public_broadcast_volume_topup_tpl', 'dyn_public_broadcast_time_extra_tpl', 'dyn_public_broadcast_wallet_deposit_tpl']],
        'ref'  => ['title' => 'زیرمجموعه و نمایندگی', 'icon' => 'users', 'keys' => ['text_affiliates', 'textrequestagent', 'textpanelagent', 'text_request_agent_dec']],
        'err_general' => ['title' => 'پیام‌های عمومی خطا', 'icon' => 'circle-exclamation', 'keys' => ['dyn_errors_verification_failed', 'dyn_errors_restart_process', 'dyn_errors_concurrency_insufficient_stock', 'dyn_errors_panel_connection_error', 'dyn_errors_button_disabled', 'dyn_errors_feature_unavailable', 'dyn_errors_feature_unavailable_short', 'dyn_errors_feature_currently_unavailable_nodot', 'dyn_errors_service_found_count', 'dyn_errors_service_not_found', 'dyn_errors_view_account_unavailable', 'dyn_errors_qr_unavailable', 'dyn_errors_service_deleted', 'dyn_errors_config_read_error', 'dyn_errors_panel_unavailable_dot', 'dyn_errors_config_read_from_panel_error', 'dyn_errors_usage_stats_failed', 'dyn_errors_server_list_failed', 'dyn_errors_not_connected_change_status']],
        'err_purchase' => ['title' => 'خطاهای خرید و تمدید', 'icon' => 'cart-shopping', 'keys' => ['dyn_errors_renewal_failed_restart', 'dyn_errors_renewal_support_error', 'dyn_errors_purchase_info_incomplete', 'dyn_errors_panel_not_found', 'dyn_errors_panel_unavailable_choose_other', 'dyn_errors_extra_volume_purchase_error', 'dyn_errors_location_change_limit_reached', 'dyn_errors_min_max_deposit_amount', 'dyn_errors_stock_depleted_no_charge', 'dyn_errors_stock_extend_success', 'dyn_errors_invalid_subscription_link', 'dyn_errors_extend_unavailable_panel', 'dyn_errors_not_connected_extend', 'dyn_errors_extend_current_plan_unavailable', 'dyn_errors_extend_restart_process', 'dyn_errors_exclusive_discount_conflict', 'dyn_errors_discount_expired', 'dyn_errors_discount_applied', 'dyn_errors_link_change_service_disabled', 'dyn_errors_extra_volume_unavailable_panel', 'dyn_errors_extra_volume_restart_process', 'dyn_errors_purchase_failed_restart', 'dyn_errors_location_change_unavailable', 'dyn_errors_generic_restart_process', 'dyn_errors_restart_buy_process_short', 'dyn_errors_transfer_panel_unavailable', 'dyn_errors_transfer_unused_config_only', 'dyn_errors_removal_request_thanks', 'dyn_errors_extra_time_unavailable_panel', 'dyn_errors_extra_time_restart_process', 'dyn_errors_service_removed', 'dyn_errors_service_removal_unavailable', 'dyn_errors_removal_reason_prompt', 'dyn_errors_test_service_unavailable', 'dyn_errors_min_bulk_purchase_balance', 'dyn_errors_section_disabled', 'dyn_errors_discount_code_not_applicable', 'dyn_errors_stock_low_purchase_blocked', 'dyn_errors_queued_renewal_exists']],
        'tickets' => ['title' => 'تیکت پشتیبانی', 'icon' => 'ticket', 'keys' => ['dyn_tickets_select_category', 'dyn_tickets_select_department', 'dyn_tickets_ask_subject', 'dyn_tickets_operation_canceled', 'dyn_tickets_invalid_subject_text', 'dyn_tickets_ask_media_new', 'dyn_tickets_ask_media_reply', 'dyn_tickets_media_yes', 'dyn_tickets_media_no', 'dyn_tickets_cancel', 'dyn_tickets_ask_media_count', 'dyn_tickets_ask_media_upload', 'dyn_tickets_invalid_media_choice', 'dyn_tickets_invalid_media_type', 'dyn_tickets_media_received', 'dyn_tickets_ask_body_text', 'dyn_tickets_ask_body_text_short', 'dyn_tickets_not_available', 'dyn_tickets_reply_recorded', 'dyn_tickets_invalid_body_text', 'dyn_tickets_created', 'dyn_tickets_closed_locked', 'dyn_tickets_no_media', 'dyn_tickets_closed_confirm']],
        'wallet_ext' => ['title' => 'کیف پول و پرداخت', 'icon' => 'wallet', 'keys' => ['dyn_wallet_select_amount', 'dyn_wallet_recheck_canceled', 'dyn_wallet_amount_not_numeric', 'dyn_wallet_rate_unavailable', 'dyn_wallet_calculated_amount_invalid', 'dyn_wallet_hash_not_found', 'dyn_wallet_ask_receipt_photo', 'dyn_wallet_incomplete_info', 'dyn_wallet_hash_already_used', 'dyn_wallet_internal_error_retry', 'dyn_wallet_invalid_charge_amount', 'dyn_wallet_discount_not_applicable_zero', 'dyn_wallet_no_active_card', 'dyn_wallet_select_verified_card', 'dyn_wallet_card_auth_active_notice', 'dyn_wallet_queue_too_busy', 'dyn_wallet_hashchecker_not_loaded', 'dyn_wallet_no_wallet_address_configured', 'dyn_wallet_charge_amount_undetectable', 'dyn_wallet_invoice_not_found', 'dyn_wallet_invoice_not_pending', 'dyn_wallet_prompt_amount_prompt', 'dyn_wallet_prompt_amount_out_of_range', 'dyn_wallet_prompt_debt_must_pay_first', 'dyn_wallet_prompt_has_discount_yes', 'dyn_wallet_prompt_has_discount_no', 'dyn_wallet_prompt_ask_has_discount', 'dyn_wallet_prompt_auth_success']],
        'tonpay' => ['title' => 'قالب پیام‌های پرداخت تون‌پی (TonPay Payment Message Templates)', 'icon' => 'wallet', 'keys' => tonpayTemplateKeys()],
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
    if ($key === 'text_custom_price_summary') return 'custom_pricing';
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
        $rawPayload = $_POST['_text_payload'] ?? null;
        $payload = is_string($rawPayload) ? json_decode($rawPayload, true) : null;
        $validPayload = is_array($payload) && ($payload['version'] ?? null) === 1
            && isset($payload['t'], $payload['rich']) && is_array($payload['t']) && is_array($payload['rich']);
        if ($validPayload) {
            $validPayload = count($payload['t']) === count($payload['rich']);
            foreach ($payload['t'] as $key => $value) {
                if (!array_key_exists($key, $payload['rich'])) {
                    $validPayload = false;
                    break;
                }
            }
        }
        if ($validPayload) {
            foreach ([$payload['t'], $payload['rich']] as $values) {
                foreach ($values as $value) {
                    if (!is_string($value)) {
                        $validPayload = false;
                        break 2;
                    }
                }
            }
        }
        if (!$validPayload) {
            $_SESSION['textbot_save_error'] = 'داده‌های فرم کامل یا معتبر نیستند؛ تغییری ذخیره نشد. صفحه را تازه کنید و دوباره ذخیره کنید.';
            header('Location: textbot.php');
            exit;
        }
        $newValues = $payload['t'];


        $cur = [];
        $r = $pdo->query("SELECT id_text, text FROM textbot");
        if ($r) {
            foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cur[(string)$row['id_text']] = (string)$row['text'];
            }
        }

        $cur += $textDefaults;
        $updated = 0;
        $edgeRejected = [];
        $tonpayTemplateErrors = [];
        $richPrepared = [];
        $richDropped = [];
        $richRejected = [];
        $richBlocked = [];
        $richErrors = [];
        $richPost = $payload['rich'];
        foreach ($richPost as $rKey => $rRaw) {
            $rKey = (string)$rKey;
            if (!isset($cur[$rKey])) continue;
            $rText = array_key_exists($rKey, $newValues) ? (string)$newValues[$rKey] : (string)$cur[$rKey];
            $rCompanion = fx_textbot_rich_prefix() . $rKey;
            $rIssue = null;
            $rState = fx_textbot_rich_state_for_save($rRaw, $rText, $rIssue);
            if ($rState === null) {
                if ($rIssue !== null) {
                    $richRejected[] = $rKey;
                    $richBlocked[$rKey] = true;
                    $richErrors[$rKey] = $rIssue;
                    continue;
                }
                if (isset($cur[$rCompanion])) {
                    $richDropped[$rCompanion] = true;
                }
                continue;
            }
            $newValues[$rKey] = (string)$rState['html'];
            $richPrepared[$rKey] = $rState;
        }
        foreach ($newValues as $key => $val) {
            $key = (string)$key;
            $val = (string)$val;
            if (isset($richBlocked[$key])) continue;
            $rCompanion = fx_textbot_rich_prefix() . $key;
            $hasRichChange = (isset($richPrepared[$key]) && ($cur[$rCompanion] ?? '') !== fx_textbot_rich_state_encode($richPrepared[$key])) || isset($richDropped[$rCompanion]);
            if (isset($cur[$key]) && $cur[$key] === $val && !$hasRichChange) {
                continue;
            }
            $tonpayTemplateIssue = tonpayTemplateValidate($key, $val);
            if ($tonpayTemplateIssue !== '') {
                $tonpayTemplateErrors[$key] = $tonpayTemplateIssue;
                continue;
            }
            if (fx_textbot_edge_violation($key, $val) !== '') {
                $edgeRejected[] = $key;
                continue;
            }
            try {
                if ($hasRichChange) {
                    $pdo->beginTransaction();
                }
                $upd = $pdo->prepare("INSERT INTO textbot (id_text, text) VALUES (:k, :v) ON DUPLICATE KEY UPDATE text = :v2");
                $upd->execute([':k' => $key, ':v' => $val, ':v2' => $val]);
                if (isset($richPrepared[$key])) {
                    $rJson = fx_textbot_rich_state_encode($richPrepared[$key]);
                    $upd->execute([':k' => $rCompanion, ':v' => $rJson, ':v2' => $rJson]);
                } elseif (isset($richDropped[$rCompanion])) {
                    $pdo->prepare("DELETE FROM textbot WHERE id_text = :k")->execute([':k' => $rCompanion]);
                }
                if ($hasRichChange) {
                    $pdo->commit();
                }
                $updated++;
            } catch (\Throwable $e) {
                if ($hasRichChange && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $richErrors[$key] = 'ذخیرهٔ متن انجام نشد؛ نسخهٔ قبلی حفظ شد.';
                $richRejected[] = $key;
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
        $redirect = 'textbot.php?saved=' . $updated;
        if (!empty($tonpayTemplateErrors)) {
            $_SESSION['tonpay_template_errors'] = $tonpayTemplateErrors;
        }
        if (!empty($edgeRejected)) {
            $redirect .= '&edge_rejected=' . urlencode(implode(',', $edgeRejected));
        }
        if (!empty($richRejected)) {
            $_SESSION['rich_template_errors'] = $richErrors;
            $redirect .= '&rich_rejected=' . urlencode(implode(',', $richRejected));
        }
        header('Location: ' . $redirect);
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
        } elseif (fx_textbot_edge_violation($key, $val) !== '') {
            $errors[] = fx_textbot_edge_error_message();
        } elseif (tonpayTemplateValidate($key, $val) !== '') {
            $errors[] = tonpayTemplateValidate($key, $val);
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
                $d = $pdo->prepare("DELETE FROM textbot WHERE id_text = :k OR id_text = :rk");
                $d->execute([':k' => $key, ':rk' => fx_textbot_rich_prefix() . $key]);
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


foreach ($textDefaults as $defaultKey => $defaultText) {
    if (!in_array($defaultKey, array_column($rows, 'id_text'), true)) {
        $rows[] = ['id_text' => $defaultKey, 'text' => $defaultText];
    }
}
$richStates = [];
$visibleRows = [];
$richPrefix = fx_textbot_rich_prefix();
foreach ($rows as $row) {
    $rowId = (string)$row['id_text'];
    if (strpos($rowId, $richPrefix) === 0) {
        $richStates[substr($rowId, strlen($richPrefix))] = (string)$row['text'];
        continue;
    }
    $visibleRows[] = $row;
}

$grouped = [];
foreach ($visibleRows as $row) {
    $g = faoxima_text_group((string)$row['id_text']);
    if (!isset($grouped[$g])) $grouped[$g] = [];
    $grouped[$g][] = $row;
}

$categories = faoxima_text_categories();
$tabOrder = ['sys', 'srv', 'help', 'fin', 'custom_pricing', 'ref', 'err_general', 'err_purchase', 'tickets', 'wallet_ext', 'tonpay', 'ref_ext', 'misc', 'cron', 'jsontext_users', 'jsontext_admin', 'other'];
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
$tonpayTemplateFlash = isset($_SESSION['tonpay_template_errors']) && is_array($_SESSION['tonpay_template_errors']) ? $_SESSION['tonpay_template_errors'] : [];
unset($_SESSION['tonpay_template_errors']);
$edgeRejectedKeys = isset($_GET['edge_rejected']) ? array_values(array_filter(explode(',', (string)$_GET['edge_rejected']), 'strlen')) : [];
$richRejectedKeys = isset($_GET['rich_rejected']) ? array_values(array_filter(explode(',', (string)$_GET['rich_rejected']), 'strlen')) : [];
$richTemplateErrors = isset($_SESSION['rich_template_errors']) && is_array($_SESSION['rich_template_errors']) ? $_SESSION['rich_template_errors'] : [];
unset($_SESSION['rich_template_errors']);
if (isset($_SESSION['textbot_save_error'])) {
    $flash['err'] = (string) $_SESSION['textbot_save_error'];
    unset($_SESSION['textbot_save_error']);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>متن‌های ربات | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <script src="js/theme.js?v=flat50" defer>

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
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 8px;
            display: grid;
            grid-template-columns: 220px 1fr auto;
            gap: var(--space-4);
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
            font-size: 11px;
            font-family: 'Vazirmatn', sans-serif;
            white-space: nowrap;
        }
        .text-row textarea {
            width: 100%;
            background: var(--surface-3);
            border: 1px solid var(--border-mid);
            border-radius: var(--radius-md);
            color: var(--text-main);
            padding: 10px 12px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 13.5px;
            min-height: 44px;
            resize: vertical;
            line-height: 1.7;
        }
        .text-row textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
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
        .text-row textarea.edge-invalid,
        #editTextArea.edge-invalid {
            border-color: var(--color-danger);
            background: var(--color-danger-soft);
        }
        .text-key__hint {
            font-family: 'Vazirmatn', sans-serif;
            font-size: 10.5px;
            color: var(--text-muted);
            word-break: break-word;
        }
        .text-key__edge-error {
            color: var(--color-danger);
            font-size: 11px;
            font-family: 'Vazirmatn', sans-serif;
            direction: rtl;
            text-align: right;
        }
        .text-key__edge-error[hidden],
        .edit-edge-error[hidden] { display: none; }
        .edit-edge-error {
            color: var(--color-danger);
            font-size: 12.5px;
            margin-top: 6px;
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
            border-radius: var(--radius-md);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 13.5px;
        }
        .filter-bar input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        .search-scope {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px;
            flex: 0 1 360px;
            padding: 4px;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
            background: var(--surface-2);
        }
        .search-scope__btn {
            min-height: 36px;
            padding: 7px 12px;
            border: 0;
            border-radius: var(--radius-sm);
            background: transparent;
            color: var(--text-muted);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 12.5px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color .15s ease, color .15s ease, box-shadow .15s ease;
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
            border-radius: var(--radius-md);
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
            border-radius: var(--radius-lg);
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
            border-radius: var(--radius-sm);
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
            border-radius: var(--radius-sm);
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
        .edit-rich-note {
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 10px;
            direction: rtl;
            text-align: right;
        }
        .edit-rich-note[hidden] { display: none; }
        .modal-box--rich { max-width: 1120px; width: min(1120px, calc(100vw - 24px)); }
        .rich-modal-body { flex: 1 1 auto; min-height: 0; padding: 14px 18px; overflow-y: auto; overscroll-behavior: contain; }
        .rich-head-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .rich-head-actions svg { width: 18px; height: 18px; flex-shrink: 0; }
        #richUndoBtn { color: #e05260; gap: 6px; }
        #richRedoBtn { color: #229e70; gap: 6px; }
        .rich-mobile-tabs { display: none; }
        .rich-table-controls { border-bottom: 1px solid var(--border-soft); }
        .rich-table-controls > summary { padding: 10px 12px; cursor: pointer; color: var(--text-muted); font-size: 12px; background: var(--surface-3); }
        .rich-table-drag-hint { margin: 0; padding: 8px 10px 2px; color: var(--text-muted); font-size: 10.5px; line-height: 1.7; }
        .rich-table td.rich-row-grip-cell { width: 32px; padding: 2px; background: var(--surface-3); vertical-align: middle; }
        .rich-row-grip { display: flex; align-items: center; justify-content: center; width: 28px; min-height: 38px; padding: 0; border: 0; border-radius: var(--radius-sm); color: var(--text-muted); background: transparent; cursor: grab; touch-action: none; user-select: none; -webkit-user-select: none; }
        .rich-row-grip svg { width: 18px; height: 18px; }
        .rich-row-grip:hover, .rich-row-grip:focus-visible { color: var(--accent); background: var(--accent-soft); outline: 2px solid var(--accent); }
        .rich-table tr.is-row-dragging { opacity: .4; }
        .rich-table tr.is-drop-before > td, .rich-table tr.is-drop-before > th { border-top: 3px solid var(--accent); }
        .rich-table tr.is-drop-after > td, .rich-table tr.is-drop-after > th { border-bottom: 3px solid var(--accent); }
        .rich-table td.is-drop-left, .rich-table th.is-drop-left { box-shadow: inset 4px 0 var(--accent); background: var(--accent-soft); }
        .rich-table td.is-drop-right, .rich-table th.is-drop-right { box-shadow: inset -4px 0 var(--accent); background: var(--accent-soft); }
        .rich-table td[data-cell-id], .rich-table th[data-cell-id] { position: relative; }
        .rich-cell-editor { min-height: 30px; outline: none; overflow-wrap: anywhere; }
        .rich-cell-editor:focus { background: var(--accent-soft); }
        .rich-cell-editor.has-cell-grip { padding-inline-end: 30px; }
        .rich-cell-grip { position: absolute; inset-inline-end: 2px; top: 50%; transform: translateY(-50%); width: 28px; min-height: 32px; opacity: .7; }
        .rich-cell-grip:hover, .rich-cell-grip:focus-visible { opacity: 1; }
        .rich-cell-grip:disabled { opacity: .25; cursor: not-allowed; }
        .rich-table td.is-row-dragging, .rich-table th.is-row-dragging { opacity: .4; }
        .rich-row-ghost { position: fixed; z-index: 11000; pointer-events: none; max-width: min(320px, 80vw); padding: 12px 16px; border: 1px solid var(--accent); border-radius: var(--radius-md); background: var(--surface-2); color: var(--text-main); box-shadow: var(--shadow-2); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; direction: rtl; }
        @media (max-width: 700px) {
            #modal-rich-text { padding: 8px; padding-top: calc(8px + env(safe-area-inset-top, 0px)); padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px)); }
            #modal-rich-text .modal-box--rich { width: 100%; max-width: none; height: calc(100dvh - 16px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px)); max-height: 100%; border-radius: var(--radius-lg); }
            .modal-box--rich .modal-head { position: static; align-items: flex-start; gap: 10px; padding: 12px; }
            .modal-box--rich .modal-head__title { min-width: 0; font-size: 13px; line-height: 1.7; }
            .modal-box--rich .modal-head__title code { display: block; max-width: 42vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; direction: ltr; }
            .rich-head-actions { gap: 4px; }
            .rich-head-actions .btn { min-height: 36px; padding: 6px 8px; font-size: 11px; }
            .rich-head-actions svg { width: 16px; height: 16px; }
            .rich-modal-body { padding: 10px; -webkit-overflow-scrolling: touch; }
            .modal-box--rich .rich-toolbar { margin: 0 0 10px; }
            .modal-box--rich .rich-toolbar__group { width: 100%; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 4px; padding: 4px; }
            .modal-box--rich .rich-toolbar .fmt-btn { min-height: 38px; padding: 5px 2px; font-size: 11px; justify-content: center; }
            .rich-mobile-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; margin-bottom: 12px; padding: 4px; border-radius: var(--radius-md); background: var(--surface-3); }
            .rich-mobile-tabs button { min-height: 36px; border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--text-muted); font: inherit; font-size: 12px; cursor: pointer; }
            .rich-mobile-tabs button[aria-pressed="true"] { background: var(--surface-1); color: var(--accent); box-shadow: 0 1px 5px rgba(0,0,0,.08); }
            .rich-modal-body[data-mobile-view="editor"] .rich-preview-pane, .rich-modal-body[data-mobile-view="preview"] .rich-editor-pane { display: none; }
            .rich-modal-body[data-mobile-view="preview"] .rich-toolbar { display: none; }
            .modal-box--rich .rich-layout { display: block; }
            .modal-box--rich .rich-blocks { max-height: none; overflow: visible; padding: 0; }
            .modal-box--rich .rich-preview-pane { position: static; }
            .modal-box--rich .rich-preview__bubble { max-height: none; min-height: 160px; }
            .modal-box--rich .rich-block__bar { gap: 5px; padding: 7px; }
            .modal-box--rich .rich-block__label { font-size: 10px; }
            .modal-box--rich .rich-type-trigger { min-width: 110px; gap: 8px; }
            .modal-box--rich .rich-table-options { gap: 10px; padding: 10px; }
            .modal-box--rich .rich-table-cellbar { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); padding: 8px; gap: 6px; }
            .modal-box--rich .rich-table-cellbar .rich-block__label { grid-column: 1 / -1; line-height: 1.7; }
            .modal-box--rich .rich-table-cellbar button, .modal-box--rich .rich-table-cellbar select { min-height: 36px; padding: 5px 3px; font-size: 10px; }
            .modal-box--rich .rich-table-cellbar .rich-alignment-trigger { min-width: 0; width: 100%; gap: 6px; padding-inline: 7px; }
            .modal-box--rich .rich-table-wrap { padding: 6px; }
            .modal-box--rich .rich-table td, .modal-box--rich .rich-table th { font-size: 12px; line-height: 1.85; word-break: break-word; }
            .modal-box--rich .rich-options { margin: 14px 0 0; padding: 12px 2px; }
            .modal-box--rich .modal-foot { display: grid; grid-template-columns: auto 1fr auto; gap: 6px; padding: 10px; background: var(--surface-2); }
            .modal-box--rich .modal-foot .btn { min-height: 40px; padding: 6px 9px; font-size: 11px; white-space: normal; }
        }
        .rich-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 2px 0 10px;
        }
        .rich-toolbar__group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 4px;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
            background: var(--surface-2);
        }
        .rich-toolbar .fmt-btn { padding: 6px 10px; font-size: 12px; }
        .rich-emoji-panel {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 4px;
            padding: 8px;
            border: 1px solid var(--border-mid);
            border-radius: var(--radius-lg);
            background: var(--surface-2);
            margin-bottom: 10px;
            max-height: 190px;
            overflow-y: auto;
        }
        .rich-emoji-panel[hidden] { display: none; }
        .rich-emoji {
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-sm);
            background: var(--surface-3);
            font-size: 20px;
            line-height: 1;
            padding: 6px 0;
            cursor: pointer;
        }
        .rich-emoji:hover { border-color: var(--accent); background: var(--accent-soft); }
        .rich-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
            gap: 14px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .rich-layout { grid-template-columns: 1fr; }
        }
        .rich-editor-pane { min-width: 0; }
        .rich-preview-pane {
            position: sticky;
            top: 12px;
            min-width: 0;
        }
        .rich-blocks { max-height: 52vh; overflow-y: auto; padding-inline-end: 4px; }
        .rich-block {
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
            background: var(--surface-2);
            margin-bottom: 8px;
            overflow: hidden;
        }
        .rich-block__bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            padding: 6px 8px;
            border-bottom: 1px solid var(--border-soft);
            background: var(--surface-3);
        }
        .rich-block__label { font-size: 11.5px; color: var(--text-muted); }
        .rich-block__spacer { flex: 1 1 auto; }
        .rich-mini-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            border: 1px solid var(--border-mid);
            border-radius: var(--radius-sm);
            background: var(--surface-2);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 11.5px;
            padding: 4px 8px;
            cursor: pointer;
        }
        .rich-mini-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
        .rich-mini-btn:disabled { opacity: .45; cursor: not-allowed; }
        .rich-select, .rich-input {
            border: 1px solid var(--border-mid);
            border-radius: var(--radius-md);
            background: var(--surface-2);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 12px;
            padding: 4px 8px;
            min-width: 0;
        }
        .rich-input--caption { flex: 1 1 180px; }
        .rich-editor {
            min-height: 38px;
            padding: 9px 12px;
            line-height: 1.8;
            font-size: 13.5px;
            outline: none;
            word-break: break-word;
        }
        .rich-editor:focus { background: var(--accent-soft); }
        .rich-editor p { margin: 0 0 8px; }
        .rich-editor p:last-child { margin-bottom: 0; }
        .rich-list { margin: 0; padding: 8px 26px 8px 12px; list-style: disc; }
        .rich-list--ordered { list-style: decimal; }
        .rich-list li { margin-bottom: 6px; }
        .rich-list__row { display: flex; align-items: flex-start; gap: 6px; }
        .rich-list__row .rich-editor { flex: 1 1 auto; min-height: 30px; padding: 4px 8px; }
        .rich-code {
            width: 100%;
            min-height: 90px;
            border: 0;
            border-top: 1px solid var(--border-soft);
            background: var(--surface-2);
            color: var(--text-main);
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            line-height: 1.6;
            padding: 10px 12px;
            direction: ltr;
            text-align: left;
            resize: vertical;
        }
        .rich-details-head { display: flex; gap: 6px; padding: 8px; border-bottom: 1px solid var(--border-soft); }
        .rich-table-options, .rich-table-cellbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 7px 8px;
            border-bottom: 1px solid var(--border-soft);
            background: var(--surface-2);
        }
        .rich-table-cellbar { background: var(--surface-3); }
        .rich-table-formatbar { display: flex; flex-wrap: wrap; gap: 7px; padding: 8px; border-bottom: 1px solid var(--border-soft); background: var(--surface-2); }
        .rich-table-formatbar button { flex: 1 1 auto; min-height: 36px; }
        .rich-table tr[hidden], .rich-table-pager[hidden] { display: none; }
        .rich-table-pager { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); align-items: center; gap: 8px; margin: 0 8px 8px; padding: 10px; border: 1px solid var(--border-soft); border-radius: var(--radius-md); background: var(--surface-2); }
        .rich-table-pager button { min-height: 42px; width: 100%; }
        .rich-table-pager__status { color: var(--text-main); text-align: center; font-size: 11px; line-height: 1.8; }
        .rich-table-pager__status small { display: block; color: var(--text-muted); font-size: 10px; }
        .rich-check { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; color: var(--text-main); }
        .rich-table-wrap { overflow-x: auto; padding: 8px; }
        .rich-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .rich-table td, .rich-table th {
            border: 1px dashed var(--border-mid);
            padding: 6px 8px;
            min-width: 70px;
            vertical-align: middle;
            font-size: 12.5px;
        }
        .rich-table th { background: var(--surface-3); font-weight: 700; }
        .rich-table.is-bordered td, .rich-table.is-bordered th { border-style: solid; }
        .rich-table.is-striped tr:nth-child(even) td { background: rgba(255,255,255,.035); }
        .rich-table.is-compact td, .rich-table.is-compact th { padding: 2px 4px; }
        .rich-table td[contenteditable="true"]:focus, .rich-table th[contenteditable="true"]:focus {
            outline: 2px solid var(--accent);
            outline-offset: -2px;
            background: var(--accent-soft);
        }
        .rich-table td.is-selected, .rich-table th.is-selected {
            outline: 2px solid var(--accent);
            outline-offset: -2px;
        }
        .rich-addbar {
            margin-top: 12px;
        }
        .rich-addbar .rich-add-text { width: 100%; min-height: 42px; justify-content: center; border: 1px dashed var(--border-mid); border-radius: var(--radius-md); background: var(--surface-2); color: var(--text-main); }
        .rich-type-trigger { display: inline-flex; align-items: center; justify-content: space-between; gap: 24px; min-width: 150px; padding: 7px 10px; cursor: pointer; }
        .rich-type-trigger[aria-expanded="true"] { border-color: var(--accent); background: var(--accent-soft); }
        .rich-alignment-trigger { min-width: 110px; gap: 12px; }
        .rich-alignment-trigger:disabled { opacity: .45; cursor: not-allowed; }
        .rich-type-menu { position: fixed; inset: auto; margin: 0; z-index: 10000; width: 220px; max-height: min(340px, 60vh); overflow-y: auto; padding: 6px; border: 1px solid var(--border-mid); border-radius: var(--radius-lg); background: var(--surface-2); color: var(--text-main); box-shadow: 0 12px 36px rgba(0,0,0,.22); direction: rtl; }
        .rich-type-menu[hidden] { display: none; }
        .rich-type-option { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 9px 11px; border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--text-main); font: inherit; font-size: 12px; text-align: right; cursor: pointer; }
        .rich-type-option:hover, .rich-type-option:focus-visible, .rich-type-option[aria-selected="true"] { background: var(--accent-soft); color: var(--accent); outline: none; }
        .rich-type-option:disabled { opacity: .45; cursor: not-allowed; }
        .rich-addbar__label { font-size: 11.5px; color: var(--text-muted); }
        .rich-addbar .fmt-btn { padding: 5px 9px; font-size: 11.5px; }
        .rich-preview__title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .rich-preview__bubble {
            background: #182533;
            color: #ffffff;
            border-radius: var(--radius-lg);
            padding: 12px 14px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 13.5px;
            line-height: 1.85;
            min-height: 120px;
            max-height: 46vh;
            overflow-y: auto;
            word-break: break-word;
        }
        .rich-preview__bubble p { margin: 0 0 8px; }
        .rich-preview__bubble h1, .rich-preview__bubble h2, .rich-preview__bubble h3,
        .rich-preview__bubble h4, .rich-preview__bubble h5, .rich-preview__bubble h6 {
            margin: 10px 0 6px;
            line-height: 1.5;
        }
        .rich-preview__bubble blockquote {
            margin: 8px 0;
            padding: 4px 10px;
            border-inline-start: 3px solid #3f8ae0;
            color: #d6e0ea;
        }
        .rich-preview__bubble pre {
            background: #0f1a24;
            border-radius: var(--radius-sm);
            padding: 8px 10px;
            overflow-x: auto;
            direction: ltr;
            text-align: left;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }
        .rich-preview__bubble code {
            background: rgba(255,255,255,.08);
            border-radius: var(--radius-xs);
            padding: 1px 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }
        .rich-preview__bubble pre code { background: transparent; padding: 0; }
        .rich-preview__bubble mark { background: #ffd54f; color: #182533; border-radius: var(--radius-xs); padding: 0 2px; }
        .rich-preview__bubble tg-spoiler { background: #2c3e50; color: transparent; border-radius: var(--radius-xs); }
        .rich-preview__bubble ul, .rich-preview__bubble ol { margin: 6px 0; padding-inline-start: 22px; }
        .rich-preview__bubble hr { border: 0; border-top: 1px solid rgba(255,255,255,.2); margin: 10px 0; }
        .rich-preview__bubble table {
            border-collapse: collapse;
            width: 100%;
            margin: 8px 0;
            table-layout: fixed;
        }
        .rich-preview__bubble td, .rich-preview__bubble th {
            border: 1px solid rgba(255,255,255,.22);
            padding: 6px 8px;
            vertical-align: middle;
        }
        .rich-preview__bubble table:not([bordered]) td,
        .rich-preview__bubble table:not([bordered]) th { border-color: transparent; }
        .rich-preview__bubble table[striped] tr:nth-child(even) td { background: rgba(255,255,255,.045); }
        .rich-preview__bubble table[compact] td,
        .rich-preview__bubble table[compact] th { padding: 2px 5px; }
        .rich-preview__bubble th { background: rgba(255,255,255,.06); }
        .rich-preview__bubble caption {
            caption-side: top;
            font-weight: 700;
            padding-bottom: 5px;
        }
        .rich-preview__bubble a { color: #6ab2f2; pointer-events: none; }
        .rich-preview__hint { font-size: 11px; color: var(--text-muted); margin-top: 6px; }
        .rich-placeholders {
            margin-top: 8px;
            font-size: 11.5px;
            color: var(--text-muted);
            line-height: 1.9;
            word-break: break-word;
        }
        .rich-placeholders code {
            background: var(--surface-3);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-xs);
            padding: 1px 5px;
            direction: ltr;
            display: inline-block;
            margin: 0 2px;
        }
        .rich-options {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }
        .rich-note { font-size: 11.5px; color: var(--text-muted); }
        .modal-box--rich .rich-options { margin: 18px 0 14px; padding: 12px 8px; border-top: 1px solid var(--border-soft); row-gap: 10px; }
        .rich-backup { margin-top: 10px; }
        .rich-backup summary {
            cursor: pointer;
            font-size: 12px;
            color: var(--text-muted);
            padding: 4px 0;
        }
        .rich-backup pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: var(--surface-3);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-sm);
            padding: 8px 10px;
            max-height: 160px;
            overflow-y: auto;
            font-size: 12px;
            margin: 6px 0;
        }
        .rich-empty {
            padding: 14px;
            text-align: center;
            color: var(--text-muted);
            font-size: 12.5px;
            border: 1px dashed var(--border-mid);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-settings-theme">

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
                <div class="alert alert-success">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span><?php echo $savedNum; ?> متن به‌روزرسانی شد.</span>
                </div>
            <?php elseif ($savedNum === 0): ?>
                <div class="alert alert-info">
                    تغییری انجام نشد.
                </div>
            <?php endif; ?>
            <?php if ($addedKey !== ''): ?>
                <div class="alert alert-success">
                    کلید جدید <code><?php echo htmlspecialchars($addedKey, ENT_QUOTES); ?></code> افزوده شد.
                </div>
            <?php endif; ?>
            <?php if ($delKey !== ''): ?>
                <div class="alert alert-warning">
                    کلید <code><?php echo htmlspecialchars($delKey, ENT_QUOTES); ?></code> حذف شد.
                </div>
            <?php endif; ?>
            <?php if (!empty($edgeRejectedKeys)): ?>
                <div class="alert alert-danger">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span>
                        <?php echo htmlspecialchars(fx_textbot_edge_error_message(), ENT_QUOTES, 'UTF-8'); ?>
                        ذخیره نشد:
                        <?php foreach ($edgeRejectedKeys as $rk): ?>
                            <code><?php echo htmlspecialchars($rk, ENT_QUOTES, 'UTF-8'); ?></code>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php if (!empty($richRejectedKeys)): ?>
                <div class="alert alert-danger">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span>
                        متن‌های زیر ذخیره نشدند و نسخهٔ قبلی آن‌ها حفظ شد:
                        <?php foreach ($richRejectedKeys as $rk): ?>
                            <code><?php echo htmlspecialchars($rk, ENT_QUOTES, 'UTF-8'); ?></code>
                            <span><?php echo htmlspecialchars($richTemplateErrors[$rk] ?? 'متن پرمیوم از محدودیت مجاز بیشتر است.', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php foreach ($tonpayTemplateFlash as $tplKey => $tplIssue): ?>
                <div class="alert alert-danger">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span><code><?php echo htmlspecialchars((string)$tplKey, ENT_QUOTES, 'UTF-8'); ?></code> ذخیره نشد: <?php echo htmlspecialchars((string)$tplIssue, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($flash['err']): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($flash['err'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="filter-bar">
                <div class="filter-bar__search">
                    <input type="search" id="searchBox" placeholder="جستجو در کلید، متن یا دسته‌بندی…">
                    <span class="chip" id="searchResultCount"><?php echo count($visibleRows); ?> کلید</span>
                </div>
                <div class="search-scope" role="group" aria-label="محدوده جستجو">
                    <button type="button" class="search-scope__btn active" data-search-scope="global" aria-pressed="true">جست‌وجوی سراسری</button>
                    <button type="button" class="search-scope__btn" data-search-scope="category" aria-pressed="false">جست‌وجوی دسته‌بندی‌شده</button>
                </div>
            </div>

            <form method="POST" action="textbot.php" id="textForm" autocomplete="off">
                <?php echo fx_csrf_field(); ?>
                <input type="hidden" name="_action" value="save">
                <input type="hidden" name="_text_payload" id="textSavePayload" value="">

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
                            $richEligibility = fx_textbot_rich_eligibility($k);
                            $richJson = isset($richStates[$k]) ? fx_textbot_rich_state_decode_storage((string)$richStates[$k]) : '';
                        ?>
                            <div class="text-row"
                                 data-search-key="<?php echo htmlspecialchars($k . ($k === 'text_custom_price_summary' ? ' خلاصه قیمت سرویس دلخواه مبلغ کل قیمت هر گیگ افزایش قیمت بر اساس مدت خرید تمدید تحویل' : ''), ENT_QUOTES, 'UTF-8'); ?>"
                                 data-search-text="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>"
                                 data-search-category="<?php echo htmlspecialchars(faoxima_tab_title($tabKey, $categories), ENT_QUOTES, 'UTF-8'); ?>">
                                <code class="text-key">
                                    <span class="text-key__id"><?php echo htmlspecialchars($k, ENT_QUOTES); ?></span>
                                    <?php if (faoxima_text_has_placeholder($v)): ?>
                                        <span class="text-key__badge" title="این متن دارای متغیر جایگزین‌شونده است؛ در ویرایش حذف نکنید">
                                            <?php echo icon('circle-exclamation', 'svg-icon'); ?> متغیر
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($k === 'text_custom_price_summary'): ?>
                                        <span class="text-key__badge">متن افزایش قیمت هر گیگ بر اساس مدت</span>
                                        <span class="text-key__hint">همان دو خط حجم، مدت، قیمت هر گیگ و مبلغ کل؛ تغییر این قالب در خرید، تمدید و تحویل سرویس اعمال می‌شود. برای حذف نمایش، خالی ذخیره کنید.</span>
                                        <span class="text-key__hint" dir="ltr">{volume}, {days}, {price_per_gb}, {total}</span>
                                    <?php endif; ?>
                                    <?php if ($k === 'text_pishinvoice'): ?>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="openTextEditor('text_custom_price_summary')">ویرایش متن مبلغ کل و قیمت هر گیگ</button>
                                        <span class="text-key__hint">برای تعیین جای خلاصهٔ قیمت، متغیر زیر را در متن یا جدول قرار دهید:</span>
                                        <span class="text-key__hint" dir="ltr">{custom_price_summary}</span>
                                    <?php endif; ?>
                                    <?php if ($k === 'text_start'): ?>
                                        <span class="text-key__hint" dir="ltr"><?php echo '{name}, {first_name}, {last_name}, {username}, {shanase}, {referral_code}, {phone}, {register_date}, {balance}, {order_count}, {payment_count}, {affiliate_count}, {group}, {date_now}, {time_now}, {time}, {version}'; ?></span>
                                    <?php endif; ?>
                                    <?php if (tonpayTemplateIsKnown($k)): ?>
                                        <span class="text-key__hint" dir="ltr"><?php echo htmlspecialchars(tonpayTemplatePlaceholderHint($k) !== '' ? tonpayTemplatePlaceholderHint($k) : 'بدون متغیر', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <?php if (fx_textbot_is_routing_key($k)): ?>
                                        <span class="text-key__edge-error" id="edge-err-<?php echo htmlspecialchars($k, ENT_QUOTES); ?>" hidden><?php echo htmlspecialchars(fx_textbot_edge_error_message(), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </code>
                                <textarea id="ta-<?php echo htmlspecialchars($k, ENT_QUOTES); ?>"
                                        class="text-edit-source"
                                        data-key="<?php echo htmlspecialchars($k, ENT_QUOTES); ?>"
                                        data-orig="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-rich-allowed="<?php echo $richEligibility['allowed'] ? '1' : '0'; ?>"
                                        data-rich-tables="<?php echo $richEligibility['tables'] ? '1' : '0'; ?>"
                                        data-rich-kind="<?php echo htmlspecialchars($richEligibility['kind'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-rich-reason="<?php echo htmlspecialchars($richEligibility['reason'], ENT_QUOTES, 'UTF-8'); ?>"
                                        rows="1" readonly autocomplete="off" onfocus="openTextEditor(this.dataset.key)" onclick="openTextEditor(this.dataset.key)"><?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <input type="hidden" id="rich-<?php echo htmlspecialchars($k, ENT_QUOTES); ?>" data-rich-key="<?php echo htmlspecialchars($k, ENT_QUOTES); ?>" data-orig="<?php echo htmlspecialchars($richJson, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($richJson, ENT_QUOTES, 'UTF-8'); ?>">
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
                    <button type="submit" class="btn btn-primary" id="textSaveBtn">
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
            <div class="edit-edge-error" id="editEdgeError" hidden><?php echo htmlspecialchars(fx_textbot_edge_error_message(), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="edit-rich-note" id="editRichNote" hidden></div>
        <div class="modal-foot">
            <button type="button" class="btn btn-outline btn-sm" id="premiumTextBtn" onclick="openPremiumEditor()">پرمیوم</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeTextEditor()">لغو</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="confirmTextEditor()">تایید</button>
        </div>
    </div>
</div>


<div id="modal-rich-text" class="modal-overlay">
    <div class="modal-box modal-box--rich">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش پرمیوم — <code id="richKeyLabel"></code></span>
            <div class="rich-head-actions">
                <button type="button" class="btn btn-outline btn-sm" id="richUndoBtn" onclick="richUndo()" title="واگرد آخرین تغییر (Ctrl+Z)" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10h11a7 7 0 0 1 0 14" transform="translate(0 -4)"/><path d="m7 2-4 4 4 4"/></svg>واگرد</button>
                <button type="button" class="btn btn-outline btn-sm" id="richRedoBtn" onclick="richRedo()" title="انجام دوبارهٔ تغییر (Ctrl+Shift+Z یا Ctrl+Y)" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 6H10a7 7 0 0 0 0 14"/><path d="m17 2 4 4-4 4"/></svg>ازنو</button>
                <button type="button" class="modal-close" onclick="cancelRichEditor()">&times;</button>
            </div>
        </div>
        <div class="rich-modal-body" id="richModalBody" data-mobile-view="editor">
        <div class="rich-toolbar" role="toolbar" aria-label="قالب‌بندی پرمیوم">
            <div class="rich-toolbar__group">
                <button type="button" class="fmt-btn" data-rich-cmd="bold" title="ضخیم">ضخیم</button>
                <button type="button" class="fmt-btn" data-rich-cmd="spoiler" title="اسپویلر">اسپویلر</button>
                <button type="button" class="fmt-btn" data-rich-cmd="code" title="کد درون‌خطی">کد</button>
                <button type="button" class="fmt-btn" data-rich-cmd="link" title="پیوند">پیوند</button>
                <button type="button" class="fmt-btn" data-rich-cmd="quote" title="ایجاد بلوک نقل‌قول از متن انتخاب‌شده">نقل‌قول</button>
                <button type="button" class="fmt-btn" id="richWrapTableBtn" onclick="richWrapTextInTables()" title="قرار دادن متن‌های پیام در جدول بدون حذف محتوا">جدول</button>
            </div>
        </div>
        <div class="rich-mobile-tabs" aria-label="نمای ویرایشگر">
            <button type="button" data-rich-view="editor" aria-pressed="true">ویرایش متن</button>
            <button type="button" data-rich-view="preview" aria-pressed="false">پیش‌نمایش پیام</button>
        </div>
        <div class="rich-layout">
            <div class="rich-editor-pane">
                <div class="rich-blocks" id="richBlocks"></div>
                <div class="rich-addbar">
                    <button type="button" class="fmt-btn rich-add-text" data-rich-block="p">＋ افزودن متن</button>
                </div>
            </div>
            <aside class="rich-preview-pane">
                <div class="rich-preview__title">پیش‌نمایش تقریبی تلگرام</div>
                <div class="rich-preview__bubble" id="richPreview" dir="rtl"></div>
                <div class="rich-preview__hint">این پیش‌نمایش تقریبی است و نمایش نهایی در تلگرام ممکن است متفاوت باشد.</div>
                <div class="rich-placeholders" id="richPlaceholders" dir="auto"></div>
            </aside>
        </div>
        <div class="rich-options">
            <label class="rich-check"><input type="checkbox" id="richRtl" checked> چیدمان راست‌به‌چپ (RTL)</label>
            <span class="rich-note" id="richEntryNote"></span>
        </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-outline btn-sm" onclick="cancelRichEditor()">لغو</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="backToPlainEditor()">بازگشت به ویرایشگر متنی</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="confirmRichEditor()">تایید</button>
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
function closeModal(id) { if (id === 'modal-rich-text') { richCloseTypeMenu(false); if (richRowDragCancel) richRowDragCancel(); } var m = document.getElementById(id); if (m) m.classList.remove('active'); }

function deleteKey(k) {
    if (!confirm('کلید «' + k + '» و متن آن حذف شود؟')) return;
    document.getElementById('deleteKey').value = k;
    document.getElementById('deleteForm').submit();
}

var TEXT_EDGE = <?php echo fx_textbot_edge_js_config(); ?>;
var TEXT_EDGE_LEAD = null;
var TEXT_EDGE_TRAIL = null;
try {
    TEXT_EDGE_LEAD = new RegExp('^' + TEXT_EDGE.cls, 'u');
    TEXT_EDGE_TRAIL = new RegExp(TEXT_EDGE.cls + '$', 'u');
} catch (e) {
    TEXT_EDGE_LEAD = null;
    TEXT_EDGE_TRAIL = null;
}
function textEdgeIsRouting(key) {
    return TEXT_EDGE.keys.indexOf(String(key || '').toLowerCase()) !== -1;
}
function textEdgeInvalid(value) {
    value = String(value || '');
    if (value === '') return false;
    if (TEXT_EDGE_LEAD && TEXT_EDGE_TRAIL) return TEXT_EDGE_LEAD.test(value) || TEXT_EDGE_TRAIL.test(value);
    return value !== value.trim();
}
function refreshEditorEdge() {
    var ta = document.getElementById('editTextArea');
    var err = document.getElementById('editEdgeError');
    if (!ta || !err) return;
    var bad = !!textEditorCurrentKey && textEdgeIsRouting(textEditorCurrentKey) && textEdgeInvalid(ta.value);
    ta.classList.toggle('edge-invalid', bad);
    err.hidden = !bad;
}

var textEditorCurrentKey = null;
function updateRichEntryState(src) {
    var btn = document.getElementById('premiumTextBtn');
    var note = document.getElementById('editRichNote');
    if (!btn || !note) return;
    var allowed = src && src.dataset && src.dataset.richAllowed === '1';
    var reason = src && src.dataset ? (src.dataset.richReason || '') : '';
    btn.disabled = !allowed;
    btn.title = allowed ? 'ویرایش بصری پیام پرمیوم' : reason;
    note.hidden = !!allowed;
    note.textContent = allowed ? '' : ('ویرایش پرمیوم غیرفعال است: ' + reason);
}
function openTextEditor(key) {
    var modal = document.getElementById('modal-edit-text');
    if (textEditorCurrentKey === key && modal && modal.classList.contains('active')) return;
    var src = document.getElementById('ta-' + key);
    if (!src) return;
    textEditorCurrentKey = key;
    document.getElementById('editTextKeyLabel').textContent = key;
    document.getElementById('editTextArea').value = src.value;
    updateRichEntryState(src);
    refreshEditorEdge();
    openModal('modal-edit-text');
    document.getElementById('editTextArea').focus();
}
function closeTextEditor() {
    richPending = null;
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
document.querySelectorAll('.text-format-bar .fmt-btn[data-tag]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        wrapTextSelection(btn.dataset.tag);
    });
});
(function () {
    var ta = document.getElementById('editTextArea');
    if (!ta) return;
    ta.addEventListener('input', refreshEditorEdge);
    ta.addEventListener('blur', refreshEditorEdge);
})();
function confirmTextEditor() {
    if (!textEditorCurrentKey) return;
    var key = textEditorCurrentKey;
    var src = document.getElementById('ta-' + key);
    if (src) {
        src.value = document.getElementById('editTextArea').value;
    }
    var hidden = document.getElementById('rich-' + key);
    var companion = richPending && richPending.key === key ? richPending.stateJson : (hidden ? hidden.value : '');
    if (hidden && key === 'text_custom_price_summary' && src && src.value.trim() === '') {
        hidden.value = '';
    } else if (hidden && companion) {
        var state = null;
        try { state = JSON.parse(companion); } catch (e) { state = null; }
        if (state && richNormalizeText(state.html) === richNormalizeText(src ? src.value : '')) {
            hidden.value = companion;
        } else if (src && state) {
            var rebuilt = richStateFromHtml(src.value);
            rebuilt.html = src.value;
            rebuilt.is_rtl = state.is_rtl !== false;
            rebuilt.plain = typeof state.plain === 'string' ? state.plain : rebuilt.plain;
            hidden.value = JSON.stringify(rebuilt);
        }
    }
    if (src) src.dispatchEvent(new Event('input', { bubbles: true }));
    closeTextEditor();
}

var richEditorKey = null;
var richEntryDraft = '';
var richEntryCompanion = '';
var richEntryPending = null;
var richPending = null;
var richState = null;
var richFocused = null;
var richSelectionRange = null;
var richSelectedCellId = null;
var richCellSeq = 0;
var richTablesAllowed = true;
var richPreviewTimer = null;
var richHistory = [];
var richHistoryIndex = -1;
var richHistoryRestoring = false;

function richHistoryButtons() {
    document.getElementById('richUndoBtn').disabled = richHistoryIndex <= 0;
    document.getElementById('richRedoBtn').disabled = richHistoryIndex < 0 || richHistoryIndex >= richHistory.length - 1;
}

function richHistoryRecord() {
    if (!richState || richHistoryRestoring) return;
    var rtl = document.getElementById('richRtl');
    var snapshot = JSON.stringify({ blocks: richState.blocks.map(richBlockToSave), plain: richState.plain || '', is_rtl: !!(rtl && rtl.checked) });
    if (richHistory[richHistoryIndex] === snapshot) return;
    richHistory = richHistory.slice(0, richHistoryIndex + 1);
    richHistory.push(snapshot);
    if (richHistory.length > 100) richHistory.shift();
    richHistoryIndex = richHistory.length - 1;
    richHistoryButtons();
}

function richHistoryRestore(index) {
    if (!richState || index < 0 || index >= richHistory.length) return;
    if (richPreviewTimer) clearTimeout(richPreviewTimer);
    var snapshot = JSON.parse(richHistory[index]);
    var active = document.activeElement;
    var activeBlock = active && active.closest ? active.closest('.rich-block') : null;
    var blockIndex = activeBlock ? parseInt(activeBlock.dataset.index, 10) : -1;
    var field = active && active.dataset ? active.dataset.field : '';
    var listIndex = active && active.dataset ? active.dataset.li : undefined;
    var cellIndex = activeBlock && active && active.dataset.cellId ? Array.prototype.indexOf.call(activeBlock.querySelectorAll('[contenteditable="true"][data-cell-id]'), active) : -1;
    richHistoryRestoring = true;
    try {
        richHistoryIndex = index;
        richState = { version: 1, blocks: snapshot.blocks.map(richNormalizeLoadedBlock), plain: snapshot.plain, is_rtl: snapshot.is_rtl };
        richSelectedCellId = null;
        document.getElementById('richRtl').checked = snapshot.is_rtl;
        richRenderAll();
        var replacementBlock = document.getElementById('richBlocks').children[blockIndex];
        var replacement = null;
        if (replacementBlock) {
            if (cellIndex >= 0) replacement = replacementBlock.querySelectorAll('[contenteditable="true"][data-cell-id]')[cellIndex];
            else if (field === 'li') replacement = replacementBlock.querySelector('[data-li="' + listIndex + '"]');
            else if (field) replacement = replacementBlock.querySelector('[data-field="' + field + '"]');
            else if (active && active.dataset.tableCaption !== undefined) replacement = replacementBlock.querySelector('[data-table-caption]');
        }
        if (replacement) {
            replacement.focus();
            if (replacement.isContentEditable) {
                var selection = window.getSelection();
                var range = document.createRange();
                range.selectNodeContents(replacement);
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }
    } finally {
        richHistoryRestoring = false;
        richHistoryButtons();
    }
}

function richUndo() {
    richHistoryRecord();
    richHistoryRestore(richHistoryIndex - 1);
}

function richRedo() {
    richHistoryRestore(richHistoryIndex + 1);
}

var RICH_TAGS_FULL = ['p','b','strong','i','em','u','ins','s','strike','del','code','mark','sub','sup','tg-spoiler','tg-emoji','a','tg-time','tg-math','br','tg-reference','pre','h1','h2','h3','h4','h5','h6','hr','footer','ul','ol','li','blockquote','aside','details','summary','table','caption','tr','th','td','tg-button-row','tg-button','figure','figcaption','cite'];
var RICH_TAGS_INLINE = ['b','strong','i','em','u','ins','s','strike','del','code','mark','sub','sup','tg-spoiler','tg-emoji','a','tg-time','tg-math','br','tg-reference'];
var RICH_ATTRS = {'a':['href','name'],'tg-emoji':['emoji-id'],'img':['src','alt'],'code':['class'],'td':['colspan','rowspan','align','valign'],'th':['colspan','rowspan','align','valign'],'table':['bordered','striped','compact'],'ol':['start','type','reversed'],'details':['open'],'tg-time':['unix','format'],'tg-button-row':['align'],'tg-button':['type','data','url','query','text','style','forward-text','request-write-access'],'tg-reference':['name']};

function rxEscHtml(value) {
    var d = document.createElement('div');
    d.textContent = String(value == null ? '' : value);
    return d.innerHTML;
}

function richSanitizeHtml(html, inlineOnly) {
    var src = String(html == null ? '' : html);
    var doc = new DOMParser().parseFromString('<div id="rich-sanitize-root">' + src + '</div>', 'text/html');
    var root = doc.getElementById('rich-sanitize-root');
    if (!root) return '';
    var allowed = {};
    (inlineOnly ? RICH_TAGS_INLINE : RICH_TAGS_FULL).forEach(function (tag) { allowed[tag] = true; });
    var drop = { script: 1, style: 1, iframe: 1, object: 1, embed: 1, form: 1, input: 1, button: 1, textarea: 1, select: 1, option: 1, svg: 1, math: 1, meta: 1, link: 1 };
    function walk(node) {
        var children = Array.prototype.slice.call(node.childNodes);
        children.forEach(function (child) {
            if (child.nodeType === 8) {
                node.removeChild(child);
                return;
            }
            if (child.nodeType !== 1) return;
            var tag = child.tagName.toLowerCase();
            if (!allowed[tag]) {
                if (drop[tag]) {
                    node.removeChild(child);
                    return;
                }
                while (child.firstChild) node.insertBefore(child.firstChild, child);
                node.removeChild(child);
                walk(node);
                return;
            }
            Array.prototype.slice.call(child.attributes).forEach(function (attr) {
                var name = attr.name.toLowerCase();
                var list = RICH_ATTRS[tag] || [];
                if (list.indexOf(name) === -1 || /^on/i.test(name)) {
                    child.removeAttribute(attr.name);
                    return;
                }
                var value = child.getAttribute(attr.name) || '';
                if (name === 'href') {
                    if (!/^(https?:\/\/|mailto:|tel:|tg:\/\/|#)/i.test(value)) child.removeAttribute(attr.name);
                } else if (name === 'src') {
                    if (!/^tg:\/\/emoji\?id=\d+$/i.test(value)) child.removeAttribute(attr.name);
                } else if (name === 'class') {
                    if (tag !== 'code' || !/^language-[a-z0-9_+-]{1,32}$/i.test(value)) child.removeAttribute(attr.name);
                } else if (name === 'colspan' || name === 'rowspan') {
                    var n = parseInt(value, 10) || 1;
                    if (n < 1 || n > (name === 'rowspan' ? 100 : 20)) child.setAttribute(attr.name, '1');
                } else if (name === 'align') {
                    if (['left', 'center', 'right'].indexOf(value) === -1) child.setAttribute(attr.name, 'left');
                } else if (name === 'valign') {
                    if (['top', 'middle', 'bottom'].indexOf(value) === -1) child.setAttribute(attr.name, 'middle');
                } else if (name === 'emoji-id') {
                    if (!/^\d+$/.test(value)) child.removeAttribute(attr.name);
                }
            });
            walk(child);
        });
    }
    walk(root);
    return root.innerHTML;
}

function richNormalizeText(value) {
    return String(value == null ? '' : value).replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
}

function richDetectRtl(value) {
    return /[؀-ۿݐ-ݿﭐ-﷿ﹰ-﻿]/.test(String(value == null ? '' : value));
}

function richNewCell(html, opts) {
    richCellSeq += 1;
    var cell = { id: 'rc' + richCellSeq, html: String(html == null ? '' : html), header: false, colspan: 1, rowspan: 1, align: 'left', valign: 'middle' };
    if (opts) {
        Object.keys(opts).forEach(function (key) { cell[key] = opts[key]; });
    }
    return cell;
}

function richNewTable(rows, cols) {
    var table = { type: 'table', bordered: true, striped: false, compact: false, caption: '', rows: [] };
    for (var r = 0; r < rows; r++) {
        var row = [];
        for (var c = 0; c < cols; c++) row.push(richNewCell(''));
        table.rows.push(row);
    }
    return table;
}

function richBlockKind(block) {
    if (!block) return 'p';
    if (block.type === 'h') return 'h' + (block.level || 2);
    if (block.type === 'quote') return block.expandable ? 'quote-expandable' : 'quote';
    return block.type || 'p';
}

function richBlockLabel(block) {
    var kind = richBlockKind(block);
    var labels = { p: 'متن', h1: 'سرتیتر ۱', h2: 'سرتیتر ۲', h3: 'سرتیتر ۳', h4: 'سرتیتر ۴', h5: 'سرتیتر ۵', h6: 'سرتیتر ۶', ul: 'لیست نشانه‌دار', ol: 'لیست شماره‌دار', quote: 'نقل‌قول', 'quote-expandable': 'نقل‌قول جمع‌شونده', pre: 'بلوک کد', hr: 'جداکننده', details: 'جزئیات بازشو', table: 'جدول' };
    return labels[kind] || 'بلوک';
}

function richParsePlain(text) {
    var blocks = [];
    String(text == null ? '' : text).replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').forEach(function (line) {
        if (line.trim() === '') return;
        blocks.push({ type: 'p', html: rxEscHtml(line) });
    });
    if (!blocks.length) blocks.push({ type: 'p', html: '' });
    return blocks;
}

function richIsBlockTag(tag) {
    return ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'pre', 'blockquote', 'details', 'hr', 'table', 'aside', 'footer', 'figure'].indexOf(tag) !== -1;
}

function richSplitLines(html) {
    var out = [];
    var cur = '';
    var inTag = false;
    var quote = null;
    for (var i = 0; i < html.length; i++) {
        var ch = html[i];
        if (inTag) {
            cur += ch;
            if (quote) {
                if (ch === quote) quote = null;
            } else if (ch === '"' || ch === "'") {
                quote = ch;
            } else if (ch === '>') {
                inTag = false;
            }
            continue;
        }
        if (ch === '<') {
            inTag = true;
            cur += ch;
            continue;
        }
        if (ch === '\n') {
            out.push(cur);
            cur = '';
            continue;
        }
        cur += ch;
    }
    out.push(cur);
    return out.map(function (line) { return line.trim(); }).filter(function (line) { return line !== ''; });
}

function richParseHtml(html) {
    var blocks = [];
    var src = String(html == null ? '' : html).trim();
    if (src === '') return [{ type: 'p', html: '' }];
    var doc = new DOMParser().parseFromString('<div id="rich-parse-root">' + src + '</div>', 'text/html');
    var root = doc.getElementById('rich-parse-root');
    if (!root) return [{ type: 'p', html: richSanitizeHtml(src, true) }];
    var buffer = '';
    function flush() {
        var text = buffer.trim();
        buffer = '';
        if (text === '') return;
        richSplitLines(text).forEach(function (line) {
            blocks.push({ type: 'p', html: richSanitizeHtml(line, true) });
        });
    }
    Array.prototype.forEach.call(root.childNodes, function (node) {
        if (node.nodeType === 3) {
            buffer += rxEscHtml(node.nodeValue);
            return;
        }
        if (node.nodeType !== 1) return;
        var tag = node.tagName.toLowerCase();
        if (!richIsBlockTag(tag)) {
            buffer += node.outerHTML;
            return;
        }
        flush();
        var block = richParseBlock(node, tag);
        if (block) blocks.push(block);
    });
    flush();
    if (!blocks.length) blocks.push({ type: 'p', html: '' });
    return blocks;
}

function richParseBlock(node, tag) {
    if (tag === 'p' || tag === 'div' || tag === 'footer' || tag === 'figure') {
        return { type: 'p', html: richSanitizeHtml(node.innerHTML, true) };
    }
    if (/^h[1-6]$/.test(tag)) {
        return { type: 'h', level: parseInt(tag.slice(1), 10), html: richSanitizeHtml(node.innerHTML, true) };
    }
    if (tag === 'ul' || tag === 'ol') {
        var items = [];
        Array.prototype.forEach.call(node.children, function (child) {
            if (child.tagName && child.tagName.toLowerCase() === 'li') items.push(richSanitizeHtml(child.innerHTML, true));
        });
        if (!items.length) items = [richSanitizeHtml(node.innerHTML, true)];
        return { type: 'list', ordered: tag === 'ol', items: items };
    }
    if (tag === 'pre') {
        var code = node.querySelector('code');
        var language = '';
        if (code) {
            var cls = code.getAttribute('class') || '';
            var m = cls.match(/(?:^|\s)language-([a-z0-9_+-]+)/i);
            if (m) language = m[1];
        }
        return { type: 'pre', text: node.textContent || '', language: language };
    }
    if (tag === 'blockquote' || tag === 'aside') {
        return { type: 'quote', expandable: node.hasAttribute('expandable'), html: richSanitizeHtml(node.innerHTML, false) };
    }
    if (tag === 'details') {
        var summary = '';
        var clone = node.cloneNode(true);
        var summaryEl = clone.querySelector('summary');
        if (summaryEl) {
            summary = (summaryEl.textContent || '').trim();
            if (summaryEl.parentNode) summaryEl.parentNode.removeChild(summaryEl);
        }
        return { type: 'details', summary: summary || 'جزئیات', open: node.hasAttribute('open'), html: richSanitizeHtml(clone.innerHTML, false) };
    }
    if (tag === 'hr') return { type: 'hr' };
    if (tag === 'table') return richParseTable(node);
    return { type: 'p', html: richSanitizeHtml(node.innerHTML, true) };
}

function richParseTable(node) {
    var table = { type: 'table', bordered: node.hasAttribute('bordered'), striped: node.hasAttribute('striped'), compact: node.hasAttribute('compact'), caption: '', rows: [] };
    var caption = node.querySelector('caption');
    if (caption) table.caption = (caption.textContent || '').trim();
    var cells = [];
    var rowCount = 0;
    Array.prototype.forEach.call(node.querySelectorAll('tr'), function (tr, r) {
        rowCount = r + 1;
        var c = 0;
        Array.prototype.forEach.call(tr.children, function (td) {
            if (!td.tagName) return;
            var cellTag = td.tagName.toLowerCase();
            if (cellTag !== 'td' && cellTag !== 'th') return;
            while (richCellOccupied(cells, r, c)) c++;
            var align = (td.getAttribute('align') || '').toLowerCase();
            var valign = (td.getAttribute('valign') || '').toLowerCase();
            var cell = richNewCell('', {
                header: cellTag === 'th',
                colspan: Math.min(20, Math.max(1, parseInt(td.getAttribute('colspan') || '1', 10) || 1)),
                rowspan: Math.min(100, Math.max(1, parseInt(td.getAttribute('rowspan') || '1', 10) || 1)),
                align: ['left', 'center', 'right'].indexOf(align) !== -1 ? align : 'left',
                valign: ['top', 'middle', 'bottom'].indexOf(valign) !== -1 ? valign : 'middle'
            });
            cell.html = richSanitizeHtml(td.innerHTML, true);
            cell._r = r;
            cell._c = c;
            for (var rr = r; rr < r + cell.rowspan; rr++) {
                cells[rr] = cells[rr] || [];
                for (var cc = c; cc < c + cell.colspan; cc++) cells[rr][cc] = cell;
            }
            c += cell.colspan;
        });
    });
    if (!cells.length) return richNewTable(2, 2);
    var rows = [];
    for (var i = 0; i < rowCount; i++) rows.push([]);
    cells.forEach(function (row) {
        row.forEach(function (cell) {
            if (cell && rows[cell._r] && rows[cell._r].indexOf(cell) === -1) rows[cell._r].push(cell);
        });
    });
    rows.forEach(function (row) { row.sort(function (a, b) { return a._c - b._c; }); });
    table.rows = rows;
    return table;
}

function richCellOccupied(cells, r, c) {
    return !!(cells[r] && cells[r][c]);
}

function richPlainFromHtml(html) {
    var doc = new DOMParser().parseFromString('<div id="rich-plain-root">' + String(html == null ? '' : html) + '</div>', 'text/html');
    var root = doc.getElementById('rich-plain-root');
    if (!root) return String(html == null ? '' : html);
    Array.prototype.slice.call(root.querySelectorAll('table')).forEach(function (table) {
        var holder = doc.createElement('p');
        holder.textContent = '\n' + richTablePlain(table) + '\n';
        if (table.parentNode) table.parentNode.replaceChild(holder, table);
    });
    Array.prototype.slice.call(root.querySelectorAll('br')).forEach(function (br) {
        if (br.parentNode) br.parentNode.replaceChild(doc.createTextNode('\n'), br);
    });
    Array.prototype.slice.call(root.querySelectorAll('li')).forEach(function (li) {
        li.insertBefore(doc.createTextNode('• '), li.firstChild);
    });
    Array.prototype.slice.call(root.querySelectorAll('p,h1,h2,h3,h4,h5,h6,blockquote,details,summary,ul,ol,pre,tr')).forEach(function (el) {
        var after = doc.createTextNode('\n');
        if (el.nextSibling) el.parentNode.insertBefore(after, el.nextSibling);
        else el.parentNode.appendChild(after);
    });
    Array.prototype.slice.call(root.querySelectorAll('hr')).forEach(function (hr) {
        if (hr.parentNode) hr.parentNode.replaceChild(doc.createTextNode('\n──────────\n'), hr);
    });
    var out = root.textContent || '';
    return out.replace(/[ \t]+/g, ' ').replace(/\n{3,}/g, '\n\n').trim();
}

function richTablePlain(table) {
    var lines = [];
    var caption = table.querySelector('caption');
    if (caption && (caption.textContent || '').trim() !== '') lines.push((caption.textContent || '').trim());
    Array.prototype.forEach.call(table.querySelectorAll('tr'), function (tr) {
        var cells = [];
        var allHeader = true;
        Array.prototype.forEach.call(tr.children, function (cell) {
            if (!/^(TD|TH)$/.test(cell.tagName)) return;
            if (cell.tagName !== 'TH') allHeader = false;
            var value = (cell.textContent || '').replace(/\s+/g, ' ').trim();
            if (value !== '') cells.push(value);
        });
        if (!cells.length) return;
        lines.push(cells.length === 2 && !allHeader ? cells[0] + ': ' + cells[1] : cells.join(' | '));
    });
    return lines.join('\n');
}

function richStateFromHtml(source) {
    var text = String(source == null ? '' : source);
    var marker = text.match(/<!--\s*rxrtl:([01])\s*-->/i);
    text = text.replace(/<!--\s*rxrtl:[01]\s*-->\s*/gi, '');
    text = text.replace(/<!--\s*rxalign:swap\s*-->\s*/gi, '');
    var hasTags = /<[a-zA-Z][^>]*>/.test(text);
    return {
        version: 1,
        is_rtl: marker ? marker[1] === '1' : richDetectRtl(text),
        blocks: hasTags ? richParseHtml(text) : richParsePlain(text),
        plain: hasTags ? richPlainFromHtml(text) : text
    };
}

function richNormalizeLoadedBlock(block) {
    if (!block || typeof block !== 'object') return null;
    var type = String(block.type || '');
    if (type === 'p' || type === 'h' || type === 'quote' || type === 'details') {
        var out = { type: type, html: String(block.html == null ? '' : block.html) };
        if (type === 'h') out.level = Math.min(6, Math.max(1, parseInt(block.level || 2, 10) || 2));
        if (type === 'quote') out.expandable = !!block.expandable;
        if (type === 'details') {
            out.summary = String(block.summary == null ? '' : block.summary);
            out.open = !!block.open;
        }
        return out;
    }
    if (type === 'list') {
        var items = Array.isArray(block.items) ? block.items.map(function (item) { return String(item == null ? '' : item); }) : [''];
        if (!items.length) items = [''];
        return { type: 'list', ordered: !!block.ordered, items: items };
    }
    if (type === 'pre') {
        return { type: 'pre', text: String(block.text == null ? '' : block.text), language: String(block.language == null ? '' : block.language) };
    }
    if (type === 'hr') return { type: 'hr' };
    if (type === 'table') {
        var rows = Array.isArray(block.rows) ? block.rows : [];
        var outRows = [];
        rows.forEach(function (row) {
            if (!Array.isArray(row)) return;
            var outRow = [];
            row.forEach(function (cell) {
                if (!cell || typeof cell !== 'object') return;
                outRow.push(richNewCell(String(cell.html == null ? '' : cell.html), {
                    header: !!cell.header,
                    colspan: Math.min(20, Math.max(1, parseInt(cell.colspan || 1, 10) || 1)),
                    rowspan: Math.min(100, Math.max(1, parseInt(cell.rowspan || 1, 10) || 1)),
                    align: ['left', 'center', 'right'].indexOf(cell.align) !== -1 ? cell.align : 'left',
                    valign: ['top', 'middle', 'bottom'].indexOf(cell.valign) !== -1 ? cell.valign : 'middle'
                }));
            });
            outRows.push(outRow);
        });
        if (!outRows.length) return null;
        return { type: 'table', bordered: !!block.bordered, striped: !!block.striped, compact: !!block.compact, caption: String(block.caption == null ? '' : block.caption), rows: outRows };
    }
    return null;
}

function richNormalizeLoadedState(state, source) {
    if (!state || typeof state !== 'object' || !Array.isArray(state.blocks) || !state.blocks.length) return null;
    if (richNormalizeText(state.html || '') !== richNormalizeText(source)) return null;
    var blocks = [];
    state.blocks.forEach(function (block) {
        var normalized = richNormalizeLoadedBlock(block);
        if (normalized) blocks.push(normalized);
    });
    if (!blocks.length) return null;
    return {
        version: 1,
        is_rtl: state.is_rtl !== false,
        blocks: blocks,
        plain: typeof state.plain === 'string' ? state.plain : richPlainFromHtml(source)
    };
}

function richEl(tag, className) {
    var el = document.createElement(tag);
    if (className) el.className = className;
    return el;
}

function richSpacer() {
    return richEl('span', 'rich-block__spacer');
}

function richMiniBtn(label, action, index, disabled) {
    var btn = richEl('button', 'rich-mini-btn');
    btn.type = 'button';
    btn.textContent = label;
    btn.dataset.blockAct = action;
    btn.dataset.index = String(index);
    btn.disabled = !!disabled;
    return btn;
}

function richCheck(option, label, checked, tableIndex) {
    var wrap = richEl('label', 'rich-check');
    var input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = !!checked;
    input.dataset.tableOpt = option;
    input.dataset.tableIndex = String(tableIndex);
    wrap.appendChild(input);
    wrap.appendChild(document.createTextNode(' ' + label));
    return wrap;
}

function richBlockTypeSelect(block, index) {
    var trigger = richEl('button', 'rich-select rich-type-trigger');
    trigger.type = 'button';
    trigger.dataset.blockPicker = String(index);
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-label', 'انتخاب قالب بلوک');
    var current = block.type === 'list' ? (block.ordered ? 'ol' : 'ul') : richBlockKind(block);
    var label = richEl('span');
    label.textContent = 'قالب موجود';
    richBlockTypeOptions().forEach(function (option) {
        if (option[0] === current) label.textContent = option[1];
    });
    trigger.appendChild(label);
    var arrow = richEl('span');
    arrow.textContent = '⌄';
    arrow.setAttribute('aria-hidden', 'true');
    trigger.appendChild(arrow);
    return trigger;
}

var richTypeMenu = null;
var richTypeTrigger = null;

function richBlockTypeOptions() {
    return [['p', 'متن'], ['h1', 'سرتیتر ۱'], ['h2', 'سرتیتر ۲'], ['h3', 'سرتیتر ۳'], ['h4', 'سرتیتر ۴'], ['h5', 'سرتیتر ۵'], ['h6', 'سرتیتر ۶'], ['quote', 'نقل‌قول'], ['quote-expandable', 'نقل‌قول جمع‌شونده'], ['pre', 'بلوک کد'], ['hr', 'جداکننده'], ['details', 'جزئیات'], ['table', 'جدول']];
}

function richCloseTypeMenu(restoreFocus) {
    var trigger = richTypeTrigger;
    richTypeTrigger = null;
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    if (richTypeMenu) {
        if (typeof richTypeMenu.hidePopover === 'function' && richTypeMenu.matches(':popover-open')) richTypeMenu.hidePopover();
        richTypeMenu.hidden = true;
    }
    if (restoreFocus && trigger && trigger.isConnected) trigger.focus();
}

function richOpenTypeMenu(trigger) {
    if (trigger.disabled) return;
    if (richTypeTrigger === trigger) { richCloseTypeMenu(true); return; }
    richCloseTypeMenu(false);
    if (!richState) return;
    var axis = trigger.dataset.alignmentPicker || '';
    var index = parseInt(axis ? trigger.dataset.tableIndex : trigger.dataset.blockPicker, 10);
    var block = richState.blocks[index];
    if (!block) return;
    var cell = axis ? richFindCell(block, richSelectedCellId) : null;
    if (axis && !cell) return;
    if (!richTypeMenu) {
        richTypeMenu = richEl('div', 'rich-type-menu');
        richTypeMenu.id = 'richBlockTypeMenu';
        richTypeMenu.setAttribute('role', 'listbox');
        richTypeMenu.setAttribute('aria-label', 'قالب بلوک');
        richTypeMenu.setAttribute('popover', 'manual');
        richTypeMenu.hidden = true;
        document.body.appendChild(richTypeMenu);
        richTypeMenu.addEventListener('click', function (ev) {
            var option = ev.target.closest('[data-block-kind]');
            if (!option || option.disabled || !richTypeTrigger) return;
            var chosenAxis = richTypeTrigger.dataset.alignmentPicker || '';
            var blockIndex = parseInt(chosenAxis ? richTypeTrigger.dataset.tableIndex : richTypeTrigger.dataset.blockPicker, 10);
            var chosenCell = chosenAxis ? richFindCell(richState.blocks[blockIndex], richTypeMenu.dataset.cellId) : null;
            var kind = option.dataset.blockKind;
            richCloseTypeMenu(false);
            if (chosenAxis) {
                if (!chosenCell) return;
                chosenCell[chosenAxis] = kind;
                richRenderAll();
            } else richConvertBlock(blockIndex, kind);
        });
        richTypeMenu.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') { ev.preventDefault(); richCloseTypeMenu(true); return; }
            if (ev.key === 'Tab') { richCloseTypeMenu(true); return; }
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(ev.key) === -1) return;
            ev.preventDefault();
            var items = Array.prototype.slice.call(richTypeMenu.querySelectorAll('button:not(:disabled)'));
            var at = items.indexOf(document.activeElement);
            var next = ev.key === 'Home' ? 0 : ev.key === 'End' ? items.length - 1 : (at + (ev.key === 'ArrowUp' ? -1 : 1) + items.length) % items.length;
            if (items[next]) items[next].focus();
        });
    }
    richTypeTrigger = trigger;
    richTypeMenu.innerHTML = '';
    richTypeMenu.dataset.cellId = cell ? cell.id : '';
    richTypeMenu.setAttribute('aria-label', axis === 'align' ? 'تراز افقی' : axis === 'valign' ? 'تراز عمودی' : 'قالب بلوک');
    var current = axis ? cell[axis] || (axis === 'align' ? 'left' : 'middle') : richBlockKind(block);
    var choices = axis ? richAlignmentOptions(axis) : richBlockTypeOptions();
    choices.forEach(function (option) {
        var button = richEl('button', 'rich-type-option');
        button.type = 'button';
        button.dataset.blockKind = option[0];
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', option[0] === current ? 'true' : 'false');
        button.textContent = option[1] + (option[0] === current ? '  ✓' : '');
        button.disabled = !axis && option[0] === 'table' && !richTablesAllowed;
        richTypeMenu.appendChild(button);
    });
    trigger.setAttribute('aria-controls', richTypeMenu.id);
    trigger.setAttribute('aria-expanded', 'true');
    richTypeMenu.hidden = false;
    if (typeof richTypeMenu.showPopover === 'function') richTypeMenu.showPopover();
    var rect = trigger.getBoundingClientRect();
    var width = Math.min(220, window.innerWidth - 24);
    richTypeMenu.style.width = width + 'px';
    var height = richTypeMenu.getBoundingClientRect().height;
    richTypeMenu.style.left = Math.max(12, Math.min(rect.right - width, window.innerWidth - width - 12)) + 'px';
    richTypeMenu.style.top = Math.max(12, rect.bottom + 6 + height <= window.innerHeight - 12 ? rect.bottom + 6 : rect.top - height - 6) + 'px';
    var selected = richTypeMenu.querySelector('[aria-selected="true"]') || richTypeMenu.querySelector('button:not(:disabled)');
    if (selected) selected.focus();
}

function richAlignmentOptions(axis) {
    return axis === 'align' ? [['left', 'چپ'], ['center', 'وسط'], ['right', 'راست']] : [['top', 'بالا'], ['middle', 'وسط'], ['bottom', 'پایین']];
}

function richPanelAlignment(value) {
    return value === 'center' ? 'center' : value === 'right' ? 'right' : 'left';
}

function richAlignmentTrigger(axis, index) {
    var trigger = richEl('button', 'rich-select rich-type-trigger rich-alignment-trigger');
    trigger.type = 'button';
    trigger.dataset.alignmentPicker = axis;
    trigger.dataset.tableIndex = String(index);
    if (axis === 'align') trigger.dataset.cellAlign = String(index);
    else trigger.dataset.cellValign = String(index);
    var title = axis === 'align' ? 'تراز افقی' : 'تراز عمودی';
    trigger.setAttribute('aria-label', title);
    trigger.title = title;
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.disabled = true;
    var label = richEl('span');
    label.textContent = title;
    trigger.appendChild(label);
    var arrow = richEl('span');
    arrow.textContent = '⌄';
    arrow.setAttribute('aria-hidden', 'true');
    trigger.appendChild(arrow);
    return trigger;
}

function richEditorDiv(block, field, html, inlineOnly) {
    var editor = richEl('div', 'rich-editor');
    editor.contentEditable = 'true';
    editor.dataset.field = field;
    editor.innerHTML = richSanitizeHtml(html || '', inlineOnly);
    return editor;
}

function richRenderBlockBody(block, index) {
    if (block.type === 'p' || block.type === 'h' || block.type === 'quote') {
        return richEditorDiv(block, 'html', block.html, true);
    }
    if (block.type === 'details') {
        var wrap = richEl('div');
        var head = richEl('div', 'rich-details-head');
        var summary = document.createElement('input');
        summary.type = 'text';
        summary.className = 'rich-input';
        summary.placeholder = 'عنوان جزئیات';
        summary.value = block.summary || '';
        summary.dataset.field = 'summary';
        head.appendChild(summary);
        wrap.appendChild(head);
        wrap.appendChild(richEditorDiv(block, 'html', block.html, false));
        return wrap;
    }
    if (block.type === 'list') {
        var listWrap = richEl('div');
        var list = richEl('ul', 'rich-list rich-list--' + (block.ordered ? 'ordered' : 'bullets'));
        if (block.ordered) list.className = 'rich-list rich-list--ordered';
        block.items.forEach(function (item, itemIndex) {
            var li = document.createElement('li');
            var row = richEl('div', 'rich-list__row');
            var editor = richEl('div', 'rich-editor');
            editor.contentEditable = 'true';
            editor.dataset.field = 'li';
            editor.dataset.li = String(itemIndex);
            editor.innerHTML = richSanitizeHtml(item || '', true);
            var remove = richEl('button', 'rich-mini-btn');
            remove.type = 'button';
            remove.textContent = '×';
            remove.title = 'حذف آیتم';
            remove.dataset.listDel = String(itemIndex);
            remove.dataset.index = String(index);
            row.appendChild(editor);
            row.appendChild(remove);
            li.appendChild(row);
            list.appendChild(li);
        });
        listWrap.appendChild(list);
        var add = richEl('button', 'rich-mini-btn');
        add.type = 'button';
        add.textContent = '+ آیتم';
        add.dataset.listAdd = String(index);
        listWrap.appendChild(add);
        return listWrap;
    }
    if (block.type === 'pre') {
        var preWrap = richEl('div');
        var preHead = richEl('div', 'rich-details-head');
        var lang = document.createElement('input');
        lang.type = 'text';
        lang.className = 'rich-input';
        lang.placeholder = 'زبان کد (اختیاری)';
        lang.value = block.language || '';
        lang.dataset.field = 'language';
        lang.style.direction = 'ltr';
        preHead.appendChild(lang);
        preWrap.appendChild(preHead);
        var area = document.createElement('textarea');
        area.className = 'rich-code';
        area.rows = 6;
        area.dataset.field = 'text';
        area.value = block.text || '';
        preWrap.appendChild(area);
        return preWrap;
    }
    if (block.type === 'hr') {
        var hrWrap = richEl('div');
        hrWrap.style.padding = '10px 12px';
        hrWrap.appendChild(document.createElement('hr'));
        return hrWrap;
    }
    if (block.type === 'table') return richRenderTableBlock(block, index);
    return richEditorDiv(block, 'html', block.html, true);
}

function richRenderTableBlock(block, index) {
    var wrap = richEl('div', 'rich-table-block');
    var controls = richEl('details', 'rich-table-controls');
    controls.open = typeof block._toolsOpen === 'boolean' ? block._toolsOpen : !window.matchMedia('(max-width: 700px)').matches;
    controls.addEventListener('toggle', function () { block._toolsOpen = controls.open; });
    var summary = document.createElement('summary');
    summary.textContent = 'تنظیمات و ابزارهای جدول';
    controls.appendChild(summary);
    wrap.appendChild(controls);
    var options = richEl('div', 'rich-table-options');
    options.appendChild(richCheck('bordered', 'خطوط جدول', block.bordered, index));
    options.appendChild(richCheck('striped', 'ردیف‌های یک‌درمیان', block.striped, index));
    options.appendChild(richCheck('compact', 'فاصله کم', block.compact, index));
    var caption = document.createElement('input');
    caption.type = 'text';
    caption.className = 'rich-input rich-input--caption';
    caption.placeholder = 'عنوان جدول (اختیاری)';
    caption.value = block.caption || '';
    caption.dataset.tableCaption = String(index);
    options.appendChild(caption);
    controls.appendChild(options);
    var bar = richEl('div', 'rich-table-cellbar');
    var info = richEl('span', 'rich-block__label');
    info.textContent = 'برای ویرایش، یک سلول را انتخاب کنید';
    bar.appendChild(info);
    [['header', 'سرستون'], ['merge-right', 'ادغام با راست'], ['merge-down', 'ادغام با پایین'], ['split', 'تفکیک']].forEach(function (action) {
        var btn = richEl('button', 'rich-mini-btn');
        btn.type = 'button';
        btn.textContent = action[1];
        btn.dataset.cellAct = action[0];
        btn.dataset.tableIndex = String(index);
        btn.disabled = true;
        bar.appendChild(btn);
    });
    bar.appendChild(richAlignmentTrigger('align', index));
    bar.appendChild(richAlignmentTrigger('valign', index));
    [['add-row', '+ سطر'], ['del-row', '− سطر'], ['add-col', '+ ستون'], ['del-col', '− ستون']].forEach(function (action) {
        var btn = richEl('button', 'rich-mini-btn');
        btn.type = 'button';
        btn.textContent = action[1];
        btn.dataset.tableAct = action[0];
        btn.dataset.tableIndex = String(index);
        bar.appendChild(btn);
    });
    controls.appendChild(bar);
    var formatbar = richEl('div', 'rich-table-formatbar');
    formatbar.setAttribute('role', 'toolbar');
    formatbar.setAttribute('aria-label', 'قالب‌بندی متن سلول');
    [['bold', 'بولد'], ['spoiler', 'اسپویلر'], ['code', 'کد'], ['link', 'ایجاد لینک'], ['quote', 'نقل‌قول']].forEach(function (action) {
        var button = richEl('button', 'rich-mini-btn');
        button.type = 'button';
        button.textContent = action[1];
        button.dataset.tableFormat = action[0];
        button.dataset.tableIndex = String(index);
        button.disabled = true;
        button.title = action[0] === 'quote' ? 'افزودن متن انتخاب‌شده به‌صورت نقل‌قول زیر جدول؛ متن سلول حفظ می‌شود' : action[1] + ' متن انتخاب‌شده در سلول';
        button.addEventListener('pointerdown', function (ev) { richRememberSelection(); ev.preventDefault(); });
        button.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
        button.addEventListener('click', function () {
            var blockEl = button.closest('.rich-block');
            var cellEditor = blockEl && Array.prototype.find.call(blockEl.querySelectorAll('.rich-cell-editor'), function (el) { return el.dataset.cellId === richSelectedCellId; });
            if (!cellEditor) return;
            richFocused = cellEditor;
            richRestoreSelection();
            var selection = window.getSelection();
            if (!selection) return;
            if (!selection.rangeCount || selection.isCollapsed || !cellEditor.contains(selection.getRangeAt(0).commonAncestorContainer)) {
                var range = document.createRange();
                range.selectNodeContents(cellEditor);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            richRememberSelection();
            richApplyInline(action[0]);
        });
        formatbar.appendChild(button);
    });
    controls.appendChild(formatbar);
    var dragHint = richEl('p', 'rich-table-drag-hint');
    dragHint.textContent = 'وسط خانه رها کنید تا متن‌ها کنار هم قرار بگیرند؛ لبهٔ بالا یا پایین برای سطر جداست. دستگیرهٔ داخل هر خانه، همان متن را جابه‌جا می‌کند.';
    wrap.appendChild(dragHint);
    var tableWrap = richEl('div', 'rich-table-wrap');
    var table = richEl('table', 'rich-table' + (block.bordered ? ' is-bordered' : '') + (block.striped ? ' is-striped' : '') + (block.compact ? ' is-compact' : ''));
    var cellDragAllowed = block.rows.every(function (cells) { return cells.every(function (cell) { return cell.rowspan === 1 && cell.colspan === 1; }); });
    block.rows.forEach(function (row, rowIndex) {
        var tr = document.createElement('tr');
        tr.dataset.tableRow = String(rowIndex);
        tr.dataset.tableIndex = String(index);
        var gripCell = richEl('td', 'rich-row-grip-cell');
        gripCell.contentEditable = 'false';
        var grip = richEl('button', 'rich-row-grip');
        grip.type = 'button';
        grip.dataset.rowGrip = String(rowIndex);
        grip.dataset.tableIndex = String(index);
        grip.title = 'برای جابه‌جایی سطر بکشید؛ در گوشی نگه دارید';
        grip.setAttribute('aria-label', 'جابه‌جایی سطر ' + (rowIndex + 1));
        grip.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>';
        gripCell.appendChild(grip);
        tr.appendChild(gripCell);
        row.forEach(function (cell) {
            var td = document.createElement(cell.header ? 'th' : 'td');
            td.dataset.cellId = cell.id;
            td.dataset.tableIndex = String(index);
            td.contentEditable = 'false';
            var editor = richEl('div', 'rich-cell-editor' + (row.length > 1 ? ' has-cell-grip' : ''));
            editor.contentEditable = 'true';
            editor.dataset.cellId = cell.id;
            editor.dataset.tableIndex = String(index);
            editor.innerHTML = richSanitizeHtml(cell.html || '', true);
            td.appendChild(editor);
            if (row.length > 1) {
                var cellGrip = richEl('button', 'rich-row-grip rich-cell-grip');
                cellGrip.type = 'button';
                cellGrip.dataset.cellGrip = cell.id;
                cellGrip.dataset.tableIndex = String(index);
                cellGrip.setAttribute('aria-label', 'جابه‌جایی این خانه');
                cellGrip.title = 'برای جابه‌جایی این خانه بکشید';
                cellGrip.disabled = !cellDragAllowed;
                if (!cellDragAllowed) cellGrip.title = 'برای جابه‌جایی تکی خانه‌ها، ابتدا ادغام‌ها را تفکیک کنید';
                cellGrip.innerHTML = grip.innerHTML;
                td.appendChild(cellGrip);
            }
            if (cell.colspan > 1) td.setAttribute('colspan', String(cell.colspan));
            if (cell.rowspan > 1) td.setAttribute('rowspan', String(cell.rowspan));
            td.setAttribute('align', cell.align || 'left');
            td.setAttribute('valign', cell.valign || 'middle');
            td.style.textAlign = richPanelAlignment(cell.align || 'left');
            td.style.verticalAlign = cell.valign || 'middle';
            if (cell.id === richSelectedCellId) td.classList.add('is-selected');
            tr.appendChild(td);
        });
        table.appendChild(tr);
    });
    tableWrap.appendChild(table);
    wrap.appendChild(tableWrap);
    richTablePagination(wrap, table, block);
    return wrap;
}

function richTablePagination(wrap, table, block) {
    var pages = [];
    var page = null;
    richTableRowGroups(block).forEach(function (group) {
        if (!page || group.end - page.start + 1 > 5) {
            page = { start: group.start, end: group.end };
            pages.push(page);
        } else page.end = group.end;
    });
    if (!pages.length) pages.push({ start: 0, end: 0 });
    block._mobilePage = Math.max(0, Math.min(pages.length - 1, parseInt(block._mobilePage, 10) || 0));
    var selectedRow = block.rows.findIndex(function (row) { return row.some(function (cell) { return cell.id === richSelectedCellId; }); });
    if (selectedRow >= 0) {
        block._mobilePage = pages.findIndex(function (item) { return selectedRow >= item.start && selectedRow <= item.end; });
    }
    var pager = richEl('nav', 'rich-table-pager');
    pager.setAttribute('aria-label', 'صفحه‌بندی ردیف‌های جدول');
    var previous = richEl('button', 'rich-mini-btn');
    previous.type = 'button';
    previous.textContent = 'قبلی';
    var status = richEl('div', 'rich-table-pager__status');
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    var next = richEl('button', 'rich-mini-btn');
    next.type = 'button';
    next.textContent = 'بعدی';
    pager.appendChild(previous);
    pager.appendChild(status);
    pager.appendChild(next);
    wrap.appendChild(pager);
    function number(value) { return String(value).replace(/\d/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]; }); }
    function refresh() {
        var mobile = window.matchMedia('(max-width: 700px)').matches;
        var current = pages[block._mobilePage];
        table.querySelectorAll('tr[data-table-row]').forEach(function (row) {
            var index = parseInt(row.dataset.tableRow, 10);
            row.hidden = mobile && (index < current.start || index > current.end);
        });
        pager.hidden = !mobile || pages.length <= 1;
        previous.disabled = block._mobilePage === 0;
        next.disabled = block._mobilePage === pages.length - 1;
        status.textContent = 'صفحهٔ ' + number(block._mobilePage + 1) + ' از ' + number(pages.length);
        var count = richEl('small');
        count.textContent = 'ردیف ' + number(current.start + 1) + ' تا ' + number(current.end + 1) + ' از ' + number(block.rows.length);
        status.appendChild(count);
        var selected = table.querySelector('.is-selected');
        if (mobile && selected && selected.closest('tr').hidden) {
            richSelectCell(null);
            richFocused = null;
            richSelectionRange = null;
        }
    }
    function change(delta) {
        var target = block._mobilePage + delta;
        if (target < 0 || target >= pages.length) return;
        if (richRowDragCancel) richRowDragCancel();
        richSyncFocused();
        if (richFocused && wrap.contains(richFocused)) {
            richFocused.blur();
            richFocused = null;
            richSelectionRange = null;
        }
        block._mobilePage = target;
        refresh();
    }
    previous.addEventListener('click', function () { change(-1); });
    next.addEventListener('click', function () { change(1); });
    wrap.richRefreshPage = refresh;
    refresh();
}

function richRenderBlock(block, index) {
    var wrap = richEl('div', 'rich-block');
    wrap.dataset.index = String(index);
    var bar = richEl('div', 'rich-block__bar');
    var label = richEl('span', 'rich-block__label');
    label.textContent = richBlockLabel(block);
    bar.appendChild(label);
    bar.appendChild(richBlockTypeSelect(block, index));
    bar.appendChild(richSpacer());
    bar.appendChild(richMiniBtn('↑', 'up', index, index === 0));
    bar.appendChild(richMiniBtn('↓', 'down', index, index === richState.blocks.length - 1));
    bar.appendChild(richMiniBtn('حذف', 'del', index, false));
    wrap.appendChild(bar);
    wrap.appendChild(richRenderBlockBody(block, index));
    return wrap;
}

function richRenderAll() {
    if (richRowDragCancel) richRowDragCancel();
    richCloseTypeMenu(false);
    var host = document.getElementById('richBlocks');
    if (!host || !richState) return;
    richFocused = null;
    richSelectionRange = null;
    if (!richState.blocks.length) richState.blocks.push({ type: 'p', html: '' });
    host.innerHTML = '';
    richState.blocks.forEach(function (block, index) {
        host.appendChild(richRenderBlock(block, index));
    });
    richRefreshPreview();
    richUpdateTableTools();
    richUpdatePlaceholders();
}

function richBlockHtml(block) {
    if (!block) return '';
    if (block.type === 'p') return '<p>' + richSanitizeHtml(block.html, true) + '</p>';
    if (block.type === 'h') {
        var level = Math.min(6, Math.max(1, parseInt(block.level || 2, 10) || 2));
        return '<h' + level + '>' + richSanitizeHtml(block.html, true) + '</h' + level + '>';
    }
    if (block.type === 'quote') {
        return '<blockquote' + (block.expandable ? ' expandable' : '') + '>' + richSanitizeHtml(block.html, false) + '</blockquote>';
    }
    if (block.type === 'details') {
        return '<details' + (block.open ? ' open' : '') + '><summary>' + rxEscHtml(block.summary || '') + '</summary>' + richSanitizeHtml(block.html, false) + '</details>';
    }
    if (block.type === 'list') {
        var tag = block.ordered ? 'ol' : 'ul';
        var items = (block.items || []).map(function (item) { return '<li>' + richSanitizeHtml(item, true) + '</li>'; }).join('');
        return '<' + tag + '>' + items + '</' + tag + '>';
    }
    if (block.type === 'pre') {
        var language = String(block.language || '').replace(/[^a-z0-9_+-]/gi, '');
        var code = rxEscHtml(block.text || '');
        return '<pre>' + (language ? '<code class="language-' + language + '">' : '') + code + (language ? '</code>' : '') + '</pre>';
    }
    if (block.type === 'hr') return '<hr/>';
    if (block.type === 'table') return richTableHtml(block);
    return '';
}

function richTableHtml(block) {
    var attrs = (block.bordered ? ' bordered' : '') + (block.striped ? ' striped' : '') + (block.compact ? ' compact' : '');
    var out = '<table' + attrs + '>';
    if (String(block.caption || '').trim() !== '') out += '<caption>' + rxEscHtml(block.caption) + '</caption>';
    (block.rows || []).forEach(function (row) {
        out += '<tr>';
        row.forEach(function (cell) {
            var tag = cell.header ? 'th' : 'td';
            var cellAttrs = '';
            if (cell.colspan > 1) cellAttrs += ' colspan="' + cell.colspan + '"';
            if (cell.rowspan > 1) cellAttrs += ' rowspan="' + cell.rowspan + '"';
            cellAttrs += ' align="' + (cell.align || 'left') + '" valign="' + (cell.valign || 'middle') + '"';
            out += '<' + tag + cellAttrs + '>' + richSanitizeHtml(cell.html, true) + '</' + tag + '>';
        });
        out += '</tr>';
    });
    return out + '</table>';
}

function richRenderHtml() {
    return richState.blocks.map(richBlockHtml).join('\n');
}

function richRefreshPreview() {
    var host = document.getElementById('richPreview');
    if (!host || !richState) return;
    var rtl = document.getElementById('richRtl');
    host.dir = rtl && rtl.checked ? 'rtl' : 'ltr';
    document.getElementById('richBlocks').dir = host.dir;
    host.innerHTML = richSanitizeHtml(richRenderHtml(), false);
    host.querySelectorAll('td,th').forEach(function (cell) {
        cell.style.textAlign = richPanelAlignment(cell.getAttribute('align') || 'left');
        cell.style.verticalAlign = cell.getAttribute('valign') || 'middle';
    });
    richHistoryRecord();
}

function richRefreshPreviewDebounced() {
    richHistoryRecord();
    if (richPreviewTimer) clearTimeout(richPreviewTimer);
    richPreviewTimer = setTimeout(function () {
        richPreviewTimer = null;
        richRefreshPreview();
        richUpdatePlaceholders();
    }, 120);
}

function richUpdatePlaceholders() {
    var host = document.getElementById('richPlaceholders');
    if (!host || !richState) return;
    var text = richPlainFromHtml(richRenderHtml());
    var found = [];
    var re = /%(\d+\$)?[sd]|\{[a-zA-Z_][a-zA-Z0-9_]*\}/g;
    var match;
    while ((match = re.exec(text)) !== null) {
        if (found.indexOf(match[0]) === -1) found.push(match[0]);
    }
    if (!found.length) {
        host.textContent = '';
        return;
    }
    host.innerHTML = 'متغیرهای شناسایی‌شده: ' + found.map(function (value) { return '<code>' + rxEscHtml(value) + '</code>'; }).join(' ');
}

function richFindCell(block, id) {
    if (!block || block.type !== 'table' || !id) return null;
    for (var r = 0; r < block.rows.length; r++) {
        for (var c = 0; c < block.rows[r].length; c++) {
            if (block.rows[r][c] && block.rows[r][c].id === id) return block.rows[r][c];
        }
    }
    return null;
}

function richFindBlockByCellId() {
    if (!richState || !richSelectedCellId) return null;
    for (var i = 0; i < richState.blocks.length; i++) {
        if (richFindCell(richState.blocks[i], richSelectedCellId)) return { block: richState.blocks[i], index: i };
    }
    return null;
}

function richSelectCell(cellId) {
    richSelectedCellId = cellId || null;
    document.querySelectorAll('.rich-table td.is-selected, .rich-table th.is-selected').forEach(function (el) { el.classList.remove('is-selected'); });
    if (richSelectedCellId) {
        document.querySelectorAll('.rich-table td[data-cell-id], .rich-table th[data-cell-id]').forEach(function (el) {
            if (el.dataset.cellId === richSelectedCellId) el.classList.add('is-selected');
        });
    }
    richUpdateTableTools();
}

function richUpdateTableTools() {
    if (!richState) return;
    if (richSelectedCellId && !richFindBlockByCellId()) richSelectedCellId = null;
    var found = richFindBlockByCellId();
    var cell = found ? richFindCell(found.block, richSelectedCellId) : null;
    document.querySelectorAll('[data-cell-act], [data-cell-align], [data-cell-valign]').forEach(function (button) { button.disabled = true; });
    document.querySelectorAll('[data-table-format]').forEach(function (button) { button.disabled = true; });
    document.querySelectorAll('.rich-table-cellbar .rich-block__label').forEach(function (info) {
        info.textContent = cell ? 'سلول انتخاب شده' : 'برای ویرایش، یک سلول را انتخاب کنید';
    });
    if (!cell || !found) return;
    var blockEl = document.querySelector('.rich-block[data-index="' + found.index + '"]');
    if (!blockEl) return;
    blockEl.querySelectorAll('[data-cell-act]').forEach(function (btn) { btn.disabled = false; });
    blockEl.querySelectorAll('[data-table-format]').forEach(function (btn) { btn.disabled = false; });
    var splitBtn = blockEl.querySelector('[data-cell-act="split"]');
    if (splitBtn) splitBtn.disabled = cell.rowspan * cell.colspan <= 1;
    blockEl.querySelectorAll('[data-alignment-picker]').forEach(function (button) {
        var axis = button.dataset.alignmentPicker;
        var value = cell[axis] || (axis === 'align' ? 'left' : 'middle');
        button.disabled = false;
        richAlignmentOptions(axis).forEach(function (option) {
            if (option[0] === value) button.querySelector('span').textContent = option[1];
        });
    });
}

function rtGrid(block) {
    var occ = [];
    for (var r0 = 0; r0 < block.rows.length; r0++) occ[r0] = occ[r0] || [];
    block.rows.forEach(function (row, r) {
        var c = 0;
        (row || []).forEach(function (cell) {
            if (!cell) return;
            if (!occ[r]) occ[r] = [];
            while (occ[r][c]) c++;
            cell._r = r;
            cell._c = c;
            for (var rr = r; rr < r + cell.rowspan; rr++) {
                occ[rr] = occ[rr] || [];
                for (var cc = c; cc < c + cell.colspan; cc++) occ[rr][cc] = cell;
            }
            c += cell.colspan;
        });
    });
    return occ;
}

function rtPad(occ) {
    var max = 0;
    occ.forEach(function (row) { if (row.length > max) max = row.length; });
    occ.forEach(function (row) { while (row.length < max) row.push(null); });
    return max;
}

function rtRowsFromOcc(block, occ) {
    var meta = new Map();
    occ.forEach(function (row, r) {
        (row || []).forEach(function (cell, c) {
            if (!cell) return;
            var m = meta.get(cell);
            if (!m) {
                meta.set(cell, { minR: r, maxR: r, minC: c, maxC: c });
            } else {
                if (r < m.minR) m.minR = r;
                if (r > m.maxR) m.maxR = r;
                if (c < m.minC) m.minC = c;
                if (c > m.maxC) m.maxC = c;
            }
        });
    });
    var rows = [];
    for (var r = 0; r < occ.length; r++) rows.push([]);
    meta.forEach(function (m, cell) {
        cell.rowspan = m.maxR - m.minR + 1;
        cell.colspan = m.maxC - m.minC + 1;
        cell._r = m.minR;
        cell._c = m.minC;
        if (!rows[m.minR]) rows[m.minR] = [];
        rows[m.minR].push(cell);
    });
    rows.forEach(function (row) { row.sort(function (a, b) { return a._c - b._c; }); });
    block.rows = rows;
}

function rtCovered(occ, r, c) {
    var seen = new Set();
    for (var rr = 0; rr < occ.length; rr++) {
        var row = occ[rr] || [];
        for (var cc = 0; cc < row.length; cc++) {
            var cell = row[cc];
            if (!cell || seen.has(cell)) continue;
            seen.add(cell);
            if (cell._r <= r && cell._r + cell.rowspan > r && cell._c <= c && cell._c + cell.colspan > c) return true;
        }
    }
    return false;
}

function rtAddRow(block) {
    var occ = rtGrid(block);
    if (occ.length >= 100) return false;
    var cols = Math.max(1, rtPad(occ));
    var rowIndex = occ.length;
    var row = [];
    for (var c = 0; c < cols; c++) row.push(rtCovered(occ, rowIndex, c) ? null : richNewCell(''));
    occ.push(row);
    rtRowsFromOcc(block, occ);
    return true;
}

function rtAddCol(block) {
    var occ = rtGrid(block);
    var cols = Math.max(1, rtPad(occ));
    if (cols >= 20) return false;
    occ.forEach(function (row, r) {
        if (!row.length) {
            row.push(richNewCell(''));
            return;
        }
        while (row.length < cols) row.push(null);
        row.push(rtCovered(occ, r, cols) ? null : richNewCell(''));
    });
    rtRowsFromOcc(block, occ);
    return true;
}

function rtDeleteRow(block, rowIndex) {
    var occ = rtGrid(block);
    rtPad(occ);
    occ.splice(rowIndex, 1);
    rtRowsFromOcc(block, occ);
}

function rtDeleteCol(block, colIndex) {
    var occ = rtGrid(block);
    rtPad(occ);
    occ.forEach(function (row) { row.splice(colIndex, 1); });
    rtRowsFromOcc(block, occ);
}

function rtMerge(block, mode) {
    var cell = richFindCell(block, richSelectedCellId);
    if (!cell) return 'no-cell';
    var occ = rtGrid(block);
    rtPad(occ);
    var target = null;
    if (mode === 'right') {
        var right = occ[cell._r] ? occ[cell._r][cell._c + cell.colspan] : null;
        if (right && right !== cell && right._r === cell._r && right.rowspan === cell.rowspan) target = right;
    } else {
        var downRow = occ[cell._r + cell.rowspan] || [];
        var down = downRow[cell._c];
        if (down && down !== cell && down._c === cell._c && down.colspan === cell.colspan) target = down;
    }
    if (!target) return 'invalid';
    var parts = [];
    if (String(cell.html || '').trim() !== '') parts.push(cell.html);
    if (String(target.html || '').trim() !== '') parts.push(target.html);
    cell.html = parts.join(' ');
    cell.header = !!cell.header || !!target.header;
    if (mode === 'right') cell.colspan += target.colspan;
    else cell.rowspan += target.rowspan;
    block.rows.forEach(function (row) {
        var at = row.indexOf(target);
        if (at !== -1) row.splice(at, 1);
    });
    rtRowsFromOcc(block, rtGrid(block));
    return 'ok';
}

function rtSplit(block, cell) {
    if (!cell || cell.rowspan * cell.colspan <= 1) return false;
    var occ = rtGrid(block);
    rtPad(occ);
    var r = cell._r;
    var c = cell._c;
    var rs = cell.rowspan;
    var cs = cell.colspan;
    for (var rr = r; rr < r + rs; rr++) {
        occ[rr] = occ[rr] || [];
        for (var cc = c; cc < c + cs; cc++) occ[rr][cc] = null;
    }
    for (var r2 = r; r2 < r + rs; r2++) {
        occ[r2] = occ[r2] || [];
        for (var c2 = c; c2 < c + cs; c2++) {
            occ[r2][c2] = richNewCell(r2 === r && c2 === c ? cell.html : '', {
                header: r2 === r && c2 === c ? cell.header : false,
                align: cell.align,
                valign: cell.valign
            });
        }
    }
    rtRowsFromOcc(block, occ);
    return true;
}

function richBlockInlineHtml(block) {
    if (!block) return '';
    if (block.type === 'p' || block.type === 'h' || block.type === 'quote' || block.type === 'details') return block.html || '';
    if (block.type === 'list') return (block.items || []).join('<br>');
    if (block.type === 'pre') return rxEscHtml(block.text || '').replace(/\n/g, '<br>');
    if (block.type === 'table') return rxEscHtml(richBlockPlainText(block)).replace(/\n/g, '<br>');
    return '';
}

function richBlockCellHtml(block) {
    var html = richBlockInlineHtml(block);
    if (/<(?:table|ul|ol|details|pre)\b/i.test(html)) html = rxEscHtml(richBlockPlainText(block)).replace(/\n/g, '<br>');
    else html = html.replace(/<\/(?:p|h[1-6]|blockquote|div)>/gi, '<br>');
    if (block.type === 'details' && block.summary) html = '<b>' + rxEscHtml(block.summary) + '</b><br>' + html;
    if (block.type === 'h') html = '<b>' + html + '</b>';
    return richSanitizeHtml(html, true);
}

function richTableFromBlocks(blocks) {
    var table = richNewTable(1, 1);
    table.rows = blocks.map(function (block) { return [richNewCell(richBlockCellHtml(block), { align: 'right' })]; });
    return table;
}

function richWrapTextInTables() {
    if (!richState) return;
    if (!richTablesAllowed) { alert('درج جدول برای این کلید غیرفعال است.'); return; }
    richSyncFocused();
    var blocks = [];
    var pending = [];
    function flush() {
        if (pending.length) blocks.push(richTableFromBlocks(pending));
        pending = [];
    }
    richState.blocks.forEach(function (block) {
        if (block.type === 'table' || block.type === 'hr') {
            flush();
            blocks.push(block);
        } else {
            pending.push(block);
            if (pending.length === 100) flush();
        }
    });
    flush();
    richState.blocks = blocks;
    richSelectedCellId = null;
    richRenderAll();
}

function richMoveTableRow(block, cell, delta) {
    var grid = rtGrid(block);
    var from = cell._r;
    var to = from + delta;
    if (to < 0 || to >= block.rows.length) return false;
    var covered = (grid[from] || []).concat(grid[to] || []);
    if (covered.some(function (item) { return item && item.rowspan > 1; })) return false;
    var row = block.rows[from];
    block.rows[from] = block.rows[to];
    block.rows[to] = row;
    return true;
}

var richRowDragCancel = null;

function richTableRowGroups(block) {
    var grid = rtGrid(block);
    var groups = [];
    for (var start = 0; start < block.rows.length;) {
        var end = start;
        for (var row = start; row <= end; row++) {
            (grid[row] || []).forEach(function (cell) {
                if (cell) end = Math.max(end, cell._r + cell.rowspan - 1);
            });
        }
        end = Math.min(end, block.rows.length - 1);
        groups.push({ start: start, end: end });
        start = end + 1;
    }
    return groups;
}

function richStartRowDrag(ev, grip) {
    if (!richState || ev.isPrimary === false || grip.disabled || (ev.pointerType === 'mouse' && ev.button !== 0)) return;
    if (richRowDragCancel) richRowDragCancel();
    var index = parseInt(grip.dataset.tableIndex, 10);
    var block = richState.blocks[index];
    if (!block || block.type !== 'table') return;
    rtGrid(block);
    var sourceCell = grip.dataset.cellGrip ? richFindCell(block, grip.dataset.cellGrip) : null;
    var plainGrid = block.rows.every(function (row) { return row.every(function (cell) { return cell.rowspan === 1 && cell.colspan === 1; }); });
    if (sourceCell && !plainGrid) return;
    var sourceRow = sourceCell ? sourceCell._r : parseInt(grip.dataset.rowGrip, 10);
    var groups = richTableRowGroups(block);
    var source = groups.find(function (group) { return sourceRow >= group.start && sourceRow <= group.end; });
    if (!source) return;
    var table = grip.closest('table');
    var host = document.getElementById('richBlocks');
    var scroller = host.scrollHeight > host.clientHeight + 1 ? host : document.getElementById('richModalBody');
    var pointerId = ev.pointerId;
    var startX = ev.clientX, startY = ev.clientY, x = startX, y = startY;
    var active = false, ghost = null, timer = null, frame = null, drop = null;
    ev.preventDefault();
    grip.focus({ preventScroll: true });
    if (grip.setPointerCapture) grip.setPointerCapture(pointerId);
    function clearMarks() {
        table.querySelectorAll('.is-drop-before,.is-drop-after,.is-drop-left,.is-drop-right').forEach(function (row) { row.classList.remove('is-drop-before', 'is-drop-after', 'is-drop-left', 'is-drop-right'); });
    }
    function cleanup() {
        clearTimeout(timer);
        if (frame !== null) cancelAnimationFrame(frame);
        document.removeEventListener('pointermove', onMove);
        document.removeEventListener('pointerup', onUp);
        document.removeEventListener('pointercancel', onCancel);
        document.removeEventListener('keydown', onKey);
        window.removeEventListener('blur', onCancel);
        if (grip.hasPointerCapture && grip.hasPointerCapture(pointerId)) grip.releasePointerCapture(pointerId);
        table.querySelectorAll('.is-row-dragging').forEach(function (row) { row.classList.remove('is-row-dragging'); });
        clearMarks();
        if (ghost) ghost.remove();
        if (richRowDragCancel === cleanup) richRowDragCancel = null;
    }
    function highlight() {
        clearMarks();
        drop = null;
        var under = document.elementFromPoint(x, y);
        var row = under && under.closest ? under.closest('tr[data-table-row]') : null;
        if (!row || row.closest('table') !== table) return;
        var targetRow = parseInt(row.dataset.tableRow, 10);
        var target = groups.find(function (group) { return targetRow >= group.start && targetRow <= group.end; });
        if (!target) return;
        var cellElement = under.closest('td[data-cell-id],th[data-cell-id]');
        if (!cellElement) cellElement = row.querySelector('td[data-cell-id],th[data-cell-id]');
        var targetCell = cellElement ? richFindCell(block, cellElement.dataset.cellId) : null;
        var rect = row.getBoundingClientRect();
        var edge = Math.min(14, rect.height * .22);
        var center = y > rect.top + edge && y < rect.bottom - edge;
        if (center && plainGrid && targetCell && (sourceCell ? targetCell !== sourceCell : target !== source)) {
            var targetCells = block.rows[targetRow];
            var movingCells = sourceCell ? [sourceCell] : block.rows[source.start];
            var count = targetCells.length + movingCells.length - (sourceCell && sourceRow === targetRow ? 1 : 0);
            if (count > 20) return;
            var cellRect = cellElement.getBoundingClientRect();
            var left = x < cellRect.left + cellRect.width / 2;
            var rtl = document.getElementById('richRtl').checked;
            drop = { type: 'beside', row: targetCells, cell: targetCell, after: rtl ? left : !left };
            cellElement.classList.add(left ? 'is-drop-left' : 'is-drop-right');
            return;
        }
        if (center || (!sourceCell && target === source)) return;
        if (sourceCell && block.rows.length >= 100 && block.rows[sourceRow].length > 1) return;
        var first = table.querySelector('[data-table-row="' + target.start + '"]');
        var last = table.querySelector('[data-table-row="' + target.end + '"]');
        var before = y < (first.getBoundingClientRect().top + last.getBoundingClientRect().bottom) / 2;
        if (sourceCell && sourceRow === targetRow && block.rows[sourceRow].length === 1) return;
        drop = { type: 'row', boundary: before ? target.start : target.end + 1 };
        (before ? first : last).classList.add(before ? 'is-drop-before' : 'is-drop-after');
    }
    function tick() {
        if (!active) return;
        var rect = scroller.getBoundingClientRect();
        var speed = y < rect.top + 40 ? -10 : y > rect.bottom - 40 ? 10 : 0;
        if (speed) { scroller.scrollTop += speed; highlight(); }
        frame = requestAnimationFrame(tick);
    }
    function begin() {
        if (active || !grip.isConnected) return;
        active = true;
        richSyncFocused();
        richCloseTypeMenu(false);
        if (sourceCell) grip.closest('td,th').classList.add('is-row-dragging');
        else for (var row = source.start; row <= source.end; row++) table.querySelector('[data-table-row="' + row + '"]').classList.add('is-row-dragging');
        ghost = richEl('div', 'rich-row-ghost');
        ghost.textContent = richPlainFromHtml(sourceCell ? sourceCell.html : block.rows.slice(source.start, source.end + 1).map(function (cells) { return cells.map(function (cell) { return cell.html; }).join(' · '); }).join('<br>')) || 'جابه‌جایی متن';
        document.body.appendChild(ghost);
        moveGhost();
        highlight();
        frame = requestAnimationFrame(tick);
    }
    function moveGhost() {
        if (!ghost) return;
        ghost.style.left = Math.max(8, Math.min(x - ghost.offsetWidth / 2, window.innerWidth - ghost.offsetWidth - 8)) + 'px';
        ghost.style.top = Math.max(8, y - ghost.offsetHeight - 12) + 'px';
    }
    function onMove(e) {
        if (e.pointerId !== pointerId) return;
        x = e.clientX; y = e.clientY;
        if (!active) {
            var distance = Math.abs(x - startX) + Math.abs(y - startY);
            if (distance > (ev.pointerType === 'mouse' ? 5 : 8)) begin();
        }
        if (active) { e.preventDefault(); moveGhost(); highlight(); }
    }
    function onUp(e) {
        if (e.pointerId !== pointerId) return;
        x = e.clientX; y = e.clientY;
        if (active) highlight();
        var target = drop;
        var moved = active && target !== null;
        cleanup();
        if (!moved || richState.blocks[index] !== block) return;
        if (target.type === 'beside') {
            var cells;
            if (sourceCell) {
                var from = block.rows[sourceRow];
                cells = from.splice(from.indexOf(sourceCell), 1);
                if (!from.length) block.rows.splice(sourceRow, 1);
            } else {
                cells = block.rows.splice(source.start, 1)[0];
            }
            var at = target.row.indexOf(target.cell) + (target.after ? 1 : 0);
            target.row.splice.apply(target.row, [at, 0].concat(cells));
            richSelectedCellId = cells[0].id;
        } else if (sourceCell) {
            var original = block.rows[sourceRow];
            original.splice(original.indexOf(sourceCell), 1);
            var boundary = target.boundary;
            if (!original.length) {
                block.rows.splice(sourceRow, 1);
                if (boundary > sourceRow) boundary--;
            }
            block.rows.splice(boundary, 0, [sourceCell]);
            richSelectedCellId = sourceCell.id;
        } else {
            var length = source.end - source.start + 1;
            var rows = block.rows.splice(source.start, length);
            var destination = target.boundary;
            if (destination > source.start) destination -= length;
            block.rows.splice.apply(block.rows, [destination, 0].concat(rows));
            richSelectedCellId = null;
        }
        richRenderAll();
    }
    function onCancel(e) { if (!e || e.pointerId === undefined || e.pointerId === pointerId) cleanup(); }
    function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); cleanup(); } }
    document.addEventListener('pointermove', onMove, { passive: false });
    document.addEventListener('pointerup', onUp);
    document.addEventListener('pointercancel', onCancel);
    document.addEventListener('keydown', onKey);
    window.addEventListener('blur', onCancel);
    richRowDragCancel = cleanup;
    if (ev.pointerType !== 'mouse') timer = setTimeout(begin, 240);
}

function richSwapCellText(block, cell, delta) {
    var grid = rtGrid(block);
    var rtl = document.getElementById('richRtl').checked;
    var step = rtl ? -delta : delta;
    var column = step > 0 ? cell._c + cell.colspan : cell._c - 1;
    var adjacent = (grid[cell._r] || [])[column];
    if (!adjacent || adjacent === cell) return false;
    var html = cell.html;
    cell.html = adjacent.html;
    adjacent.html = html;
    richSelectedCellId = adjacent.id;
    return true;
}

function richBlockPlainText(block) {
    if (!block) return '';
    if (block.type === 'pre') return block.text || '';
    return richPlainFromHtml(richBlockHtml(block));
}

function richConvertBlock(index, kind) {
    var old = richState.blocks[index];
    if (!old) return;
    var html = richBlockInlineHtml(old);
    var plain = richBlockPlainText(old);
    var next;
    if (kind === 'p') next = { type: 'p', html: html };
    else if (/^h[1-6]$/.test(kind)) next = { type: 'h', level: parseInt(kind.slice(1), 10), html: html };
    else if (kind === 'ul' || kind === 'ol') {
        var items = html.split(/<br\s*\/?>|\n/i).map(function (line) { return line.trim(); }).filter(function (line) { return line !== ''; });
        if (!items.length) items = [html || ''];
        next = { type: 'list', ordered: kind === 'ol', items: items };
    } else if (kind === 'quote') next = { type: 'quote', expandable: false, html: html };
    else if (kind === 'quote-expandable') next = { type: 'quote', expandable: true, html: html };
    else if (kind === 'pre') next = { type: 'pre', text: plain, language: '' };
    else if (kind === 'hr') next = { type: 'hr' };
    else if (kind === 'details') next = { type: 'details', summary: 'جزئیات', open: false, html: html };
    else if (kind === 'table') {
        if (!richTablesAllowed) {
            alert('درج جدول برای این کلید غیرفعال است.');
            return;
        }
        if (old.type === 'table') return;
        next = richTableFromBlocks([old]);
    } else next = { type: 'p', html: html };
    richState.blocks[index] = next;
    richSelectedCellId = null;
    richRenderAll();
}

function richBlockToSave(block) {
    if (!block) return { type: 'p', html: '' };
    if (block.type === 'p') return { type: 'p', html: richSanitizeHtml(block.html, true) };
    if (block.type === 'h') return { type: 'h', level: block.level, html: richSanitizeHtml(block.html, true) };
    if (block.type === 'quote') return { type: 'quote', expandable: !!block.expandable, html: richSanitizeHtml(block.html, false) };
    if (block.type === 'details') return { type: 'details', summary: block.summary || '', open: !!block.open, html: richSanitizeHtml(block.html, false) };
    if (block.type === 'list') return { type: 'list', ordered: !!block.ordered, items: (block.items || []).map(function (item) { return richSanitizeHtml(item, true); }) };
    if (block.type === 'pre') return { type: 'pre', text: String(block.text || ''), language: String(block.language || '') };
    if (block.type === 'hr') return { type: 'hr' };
    if (block.type === 'table') {
        return {
            type: 'table',
            bordered: !!block.bordered,
            striped: !!block.striped,
            compact: !!block.compact,
            caption: String(block.caption || ''),
            rows: (block.rows || []).map(function (row) {
                return row.map(function (cell) {
                    return {
                        html: richSanitizeHtml(cell.html, true),
                        header: !!cell.header,
                        colspan: cell.colspan,
                        rowspan: cell.rowspan,
                        align: cell.align,
                        valign: cell.valign
                    };
                });
            })
        };
    }
    return { type: 'p', html: '' };
}

function richCollectState() {
    var blocks = richState.blocks.map(richBlockToSave);
    var html = richSanitizeHtml(blocks.map(richBlockHtml).join('\n'), false);
    var rtl = document.getElementById('richRtl');
    return {
        version: 1,
        is_rtl: !!(rtl && rtl.checked),
        blocks: blocks,
        html: html,
        plain: typeof richState.plain === 'string' ? richState.plain : ''
    };
}

function richValidate(state) {
    if (!state.html || state.html.trim() === '') return 'متن پیام نمی‌تواند خالی باشد.';
    var storedHtml = '<!--rxrtl:' + (state.is_rtl ? '1' : '0') + '-->\n' + state.html;
    if (Array.from(storedHtml).length > 16000) return 'متن پرمیوم همراه با اطلاعات جهت بیش از ۱۶۰۰۰ کاراکتر است.';
    if (new TextEncoder().encode(storedHtml).length > 60000) return 'حجم متن پرمیوم بیش از حد مجاز ذخیره‌سازی است.';
    if (state.blocks.length > 500) return 'تعداد بلوک‌ها بیش از حد مجاز است.';
    for (var i = 0; i < state.blocks.length; i++) {
        var block = state.blocks[i];
        if (block.type === 'table') {
            if (!richTablesAllowed) return 'درج جدول برای این کلید غیرفعال است.';
            var rows = block.rows || [];
            if (!rows.length || rows.length > 100) return 'جدول باید بین ۱ تا ۱۰۰ سطر داشته باشد.';
            var occupied = [];
            for (var r = 0; r < rows.length; r++) {
                var c = 0;
                for (var j = 0; j < rows[r].length; j++) {
                    var cell = rows[r][j];
                    while (occupied[r] && occupied[r][c]) c++;
                    var endCol = c + cell.colspan;
                    var endRow = r + cell.rowspan;
                    if (endCol > 20 || endRow > rows.length) return 'محدودهٔ سلول ادغام‌شده از ابعاد مجاز جدول خارج است.';
                    for (var rr = r; rr < endRow; rr++) {
                        occupied[rr] = occupied[rr] || [];
                        for (var cc = c; cc < endCol; cc++) {
                            if (occupied[rr][cc]) return 'سلول‌های ادغام‌شده با یکدیگر تداخل دارند.';
                            occupied[rr][cc] = true;
                        }
                    }
                    c = endCol;
                }
                if (!rows[r].length && (!occupied[r] || !occupied[r].some(Boolean))) return 'ساختار سطر خالی جدول معتبر نیست.';
            }
        }
    }
    return '';
}

function openPremiumEditor() {
    if (!textEditorCurrentKey) return;
    var key = textEditorCurrentKey;
    var src = document.getElementById('ta-' + key);
    if (!src) return;
    if (src.dataset.richAllowed !== '1') {
        alert('ویرایش پرمیوم برای این کلید غیرفعال است: ' + (src.dataset.richReason || ''));
        return;
    }
    var draft = document.getElementById('editTextArea').value;
    var hidden = document.getElementById('rich-' + key);
    richTablesAllowed = src.dataset.richTables === '1';
    richEntryDraft = draft;
    richEntryCompanion = hidden ? hidden.value : '';
    richEntryPending = richPending;
    closeModal('modal-edit-text');
    textEditorCurrentKey = null;
    openRichEditor(key, draft);
}

function openRichEditor(key, source) {
    richEditorKey = key;
    richSelectedCellId = null;
    richFocused = null;
    document.getElementById('richModalBody').dataset.mobileView = 'editor';
    document.querySelectorAll('[data-rich-view]').forEach(function (button) { button.setAttribute('aria-pressed', button.dataset.richView === 'editor' ? 'true' : 'false'); });
    document.getElementById('richKeyLabel').textContent = key;
    var hidden = document.getElementById('rich-' + key);
    var companion = hidden && hidden.value ? hidden.value : richEntryCompanion;
    if (richPending && richPending.key === key) companion = richPending.stateJson;
    var loaded = null;
    if (companion) {
        try {
            loaded = JSON.parse(companion);
        } catch (e) {
            loaded = null;
        }
    }
    var normalized = richNormalizeLoadedState(loaded, source);
    richState = normalized || richStateFromHtml(source);
    richHistory = [];
    richHistoryIndex = -1;
    var rtl = document.getElementById('richRtl');
    if (rtl) rtl.checked = !!richState.is_rtl;
    document.getElementById('richWrapTableBtn').disabled = !richTablesAllowed;
    var note = document.getElementById('richEntryNote');
    var src = document.getElementById('ta-' + key);
    if (note) {
        if (!richTablesAllowed) note.textContent = 'درج جدول برای این کلید غیرفعال است.';
        else if (src && src.dataset.richKind === 'caption') note.textContent = src.dataset.richReason || '';
        else note.textContent = '';
    }
    richRenderAll();
    openModal('modal-rich-text');
}

function confirmRichEditor() {
    if (!richEditorKey || !richState) return;
    var state = richCollectState();
    var problem = richValidate(state);
    if (problem) {
        alert(problem);
        return;
    }
    var key = richEditorKey;
    var src = document.getElementById('ta-' + key);
    if (src) {
        src.value = state.html;
    }
    var hidden = document.getElementById('rich-' + key);
    if (hidden) hidden.value = JSON.stringify(state);
    if (src) src.dispatchEvent(new Event('input', { bubbles: true }));
    closeModal('modal-rich-text');
    richEditorKey = null;
    richState = null;
    richSelectedCellId = null;
    richPending = null;
    richEntryPending = null;
}

function cancelRichEditor() {
    if (!richEditorKey) {
        closeModal('modal-rich-text');
        return;
    }
    var key = richEditorKey;
    var draft = richEntryDraft;
    var hidden = document.getElementById('rich-' + key);
    if (hidden) hidden.value = richEntryCompanion;
    closeModal('modal-rich-text');
    richEditorKey = null;
    richState = null;
    richSelectedCellId = null;
    richPending = richEntryPending;
    richEntryPending = null;
    var ta = document.getElementById('editTextArea');
    if (ta) ta.value = draft;
    textEditorCurrentKey = key;
    var src = document.getElementById('ta-' + key);
    if (src) updateRichEntryState(src);
    refreshEditorEdge();
    openModal('modal-edit-text');
    if (ta) ta.focus();
}

function backToPlainEditor() {
    if (!richEditorKey || !richState) return;
    var state = richCollectState();
    var problem = richValidate(state);
    if (problem) {
        alert(problem);
        return;
    }
    var key = richEditorKey;
    richPending = { key: key, stateJson: JSON.stringify(state) };
    closeModal('modal-rich-text');
    richEditorKey = null;
    richState = null;
    richSelectedCellId = null;
    textEditorCurrentKey = key;
    var ta = document.getElementById('editTextArea');
    if (ta) ta.value = state.html;
    var src = document.getElementById('ta-' + key);
    if (src) updateRichEntryState(src);
    refreshEditorEdge();
    openModal('modal-edit-text');
    if (ta) ta.focus();
}

function richSyncFocused() {
    var editor = richFocused;
    if (!editor || !richState) return;
    var blockEl = editor.closest ? editor.closest('.rich-block') : null;
    if (!blockEl) return;
    var index = parseInt(blockEl.dataset.index, 10);
    var block = richState.blocks[index];
    if (!block) return;
    if (editor.dataset.cellId) {
        var cell = richFindCell(block, editor.dataset.cellId);
        if (cell) cell.html = editor.innerHTML;
    } else if (editor.dataset.field === 'li') {
        block.items[parseInt(editor.dataset.li, 10)] = editor.innerHTML;
    } else if (editor.dataset.field === 'html') {
        block.html = editor.innerHTML;
    }
}

function richInsertHtml(html) {
    var editor = richFocused;
    if (!editor) {
        alert('ابتدا مکان درج را در متن انتخاب کنید.');
        return;
    }
    if (editor.focus) editor.focus();
    var ok = false;
    try {
        ok = document.execCommand('insertHTML', false, html);
    } catch (e) {
        ok = false;
    }
    if (!ok) {
        var sel = window.getSelection();
        if (sel && sel.rangeCount) {
            var range = sel.getRangeAt(0);
            range.deleteContents();
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            var frag = document.createDocumentFragment();
            while (tmp.firstChild) frag.appendChild(tmp.firstChild);
            range.insertNode(frag);
        }
    }
}

function richWrapSelection(tag, attrs, placeholder) {
    var editor = richFocused;
    if (!editor) {
        alert('ابتدا یک بخش از متن را انتخاب کنید.');
        return;
    }
    var open = '<' + tag;
    if (attrs) {
        Object.keys(attrs).forEach(function (key) {
            open += ' ' + key + '="' + String(attrs[key]).replace(/"/g, '&quot;') + '"';
        });
    }
    open += '>';
    var close = '</' + tag + '>';
    if (editor.focus) editor.focus();
    var sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) {
        richInsertHtml(open + rxEscHtml(placeholder || '') + close);
        return;
    }
    var range = sel.getRangeAt(0);
    if (!editor.contains(range.commonAncestorContainer)) {
        richInsertHtml(open + rxEscHtml(placeholder || '') + close);
        return;
    }
    var wrapper = document.createElement(tag);
    if (attrs) {
        Object.keys(attrs).forEach(function (key) { wrapper.setAttribute(key, attrs[key]); });
    }
    if (range.collapsed) wrapper.textContent = placeholder || '';
    else wrapper.appendChild(range.extractContents());
    range.insertNode(wrapper);
    sel.removeAllRanges();
    var next = document.createRange();
    next.selectNodeContents(wrapper);
    sel.addRange(next);
}

function richRememberSelection() {
    var selection = window.getSelection();
    if (!richState || !selection || !selection.rangeCount) return;
    var range = selection.getRangeAt(0);
    var node = range.commonAncestorContainer;
    var element = node.nodeType === 1 ? node : node.parentElement;
    var editor = element && element.closest('[contenteditable="true"]');
    var host = document.getElementById('richBlocks');
    if (!editor || !host.contains(editor) || !editor.contains(range.startContainer) || !editor.contains(range.endContainer)) return;
    richFocused = editor;
    richSelectionRange = range.cloneRange();
}

function richRestoreSelection() {
    if (!richFocused || !richFocused.isConnected) return;
    richFocused.focus();
    if (!richSelectionRange || !richFocused.contains(richSelectionRange.commonAncestorContainer)) return;
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(richSelectionRange.cloneRange());
}

function richApplyInline(command) {
    var editor = richFocused;
    if (!editor) {
        var active = document.activeElement;
        if (active && active.isContentEditable) editor = active;
    }
    if (!editor) {
        alert('ابتدا یک بخش از متن را انتخاب کنید.');
        return;
    }
    richFocused = editor;
    richRestoreSelection();
    if (command === 'quote') {
        var selection = window.getSelection();
        if (!selection || !selection.rangeCount || selection.isCollapsed || !editor.contains(selection.getRangeAt(0).commonAncestorContainer)) {
            alert('ابتدا متن موردنظر برای نقل‌قول را انتخاب کنید.');
            return;
        }
        var content = document.createElement('div');
        content.appendChild(selection.getRangeAt(0).cloneContents());
        var blockEl = editor.closest('.rich-block');
        var index = blockEl ? parseInt(blockEl.dataset.index, 10) : -1;
        if (index < 0 || !richState) return;
        richSyncFocused();
        richState.blocks.splice(index + 1, 0, { type: 'quote', expandable: false, html: richSanitizeHtml(content.innerHTML, true) });
        richRenderAll();
        return;
    }
    if (command === 'bold') {
        document.execCommand('styleWithCSS', false, false);
        document.execCommand('bold', false, null);
    } else if (command === 'spoiler') {
        richWrapSelection('tg-spoiler', null, 'متن مخفی');
    } else if (command === 'code') {
        richWrapSelection('code', null, 'متن کد');
    } else if (command === 'link') {
        richRememberSelection();
        var url = prompt('نشانی پیوند را وارد کنید (https://، tg:// یا mailto:):', 'https://');
        if (!url) return;
        if (!/^(https?:\/\/|mailto:|tel:|tg:\/\/|#)/i.test(url)) {
            alert('نشانی پیوند معتبر نیست.');
            return;
        }
        richRestoreSelection();
        richWrapSelection('a', { href: url }, 'متن پیوند');
    }
    richRememberSelection();
    richSyncFocused();
    richRefreshPreviewDebounced();
}

(function () {
    var host = document.getElementById('richBlocks');
    if (!host) return;
    var modal = document.getElementById('modal-rich-text');
    document.addEventListener('selectionchange', richRememberSelection);
    document.querySelectorAll('[data-rich-view]').forEach(function (button) {
        button.addEventListener('click', function () {
            richSyncFocused();
            richCloseTypeMenu(false);
            var body = document.getElementById('richModalBody');
            body.dataset.mobileView = button.dataset.richView;
            body.scrollTop = 0;
            document.querySelectorAll('[data-rich-view]').forEach(function (tab) { tab.setAttribute('aria-pressed', tab === button ? 'true' : 'false'); });
            if (button.dataset.richView === 'preview') richRefreshPreview();
        });
    });
    host.addEventListener('pointerdown', function (ev) {
        var grip = ev.target && ev.target.closest ? ev.target.closest('[data-row-grip],[data-cell-grip]') : null;
        if (grip) { richStartRowDrag(ev, grip); return; }
        var cell = ev.target && ev.target.closest ? ev.target.closest('td[data-cell-id],th[data-cell-id]') : null;
        if (cell) richSelectCell(cell.dataset.cellId);
    });
    host.addEventListener('contextmenu', function (ev) {
        if (ev.target && ev.target.closest && ev.target.closest('[data-row-grip],[data-cell-grip]')) ev.preventDefault();
    });
    document.addEventListener('click', function (ev) {
        if (!richTypeTrigger || ev.target.closest('[data-block-picker],[data-alignment-picker]') || (richTypeMenu && richTypeMenu.contains(ev.target))) return;
        richCloseTypeMenu(false);
    });
    document.addEventListener('scroll', function (ev) {
        if (richTypeTrigger && (!richTypeMenu || !richTypeMenu.contains(ev.target))) richCloseTypeMenu(false);
    }, true);
    window.addEventListener('resize', function () {
        richCloseTypeMenu(false);
        if (richRowDragCancel) richRowDragCancel();
        host.querySelectorAll('.rich-table-block').forEach(function (wrap) {
            if (wrap.richRefreshPage) wrap.richRefreshPage();
        });
    });
    ['richUndoBtn', 'richRedoBtn'].forEach(function (id) {
        document.getElementById(id).addEventListener('mousedown', function (ev) { ev.preventDefault(); });
    });
    modal.addEventListener('keydown', function (ev) {
        if (!richState || ev.isComposing || !(ev.ctrlKey || ev.metaKey) || ev.altKey) return;
        var key = (ev.key || '').toLowerCase();
        var undoKey = ev.code === 'KeyZ' || key === 'z';
        var redoKey = ev.code === 'KeyY' || key === 'y';
        if (!undoKey && !redoKey) return;
        ev.preventDefault();
        if (redoKey || ev.shiftKey) richRedo();
        else richUndo();
    });
    modal.addEventListener('beforeinput', function (ev) {
        if (!richState || (ev.inputType !== 'historyUndo' && ev.inputType !== 'historyRedo')) return;
        ev.preventDefault();
        if (ev.inputType === 'historyUndo') richUndo();
        else richRedo();
    });

    host.addEventListener('focusin', function (ev) {
        var editable = ev.target && ev.target.closest ? ev.target.closest('[contenteditable="true"]') : null;
        if (editable) richFocused = editable;
        var cellEl = ev.target && ev.target.closest ? ev.target.closest('td[data-cell-id],th[data-cell-id]') : null;
        if (cellEl) richSelectCell(cellEl.dataset.cellId);
    });

    host.addEventListener('input', function (ev) {
        var el = ev.target;
        if (!el || !el.closest) return;
        var blockEl = el.closest('.rich-block');
        if (!blockEl || !richState) return;
        var index = parseInt(blockEl.dataset.index, 10);
        var block = richState.blocks[index];
        if (!block) return;
        if (el.dataset.cellId) {
            var cell = richFindCell(block, el.dataset.cellId);
            if (cell) cell.html = el.innerHTML;
        } else if (el.dataset.field === 'li') {
            block.items[parseInt(el.dataset.li, 10)] = el.innerHTML;
        } else if (el.dataset.field === 'html') {
            block.html = el.innerHTML;
        } else if (el.dataset.field === 'text') {
            block.text = el.value;
        } else if (el.dataset.field === 'summary') {
            block.summary = el.value;
        } else if (el.dataset.field === 'language') {
            block.language = el.value;
        } else if (el.dataset.tableCaption !== undefined) {
            block.caption = el.value;
        }
        richRefreshPreviewDebounced();
    });

    host.addEventListener('keydown', function (ev) {
        var picker = ev.target && ev.target.closest ? ev.target.closest('[data-block-picker],[data-alignment-picker]') : null;
        if (picker && (ev.key === 'ArrowDown' || ev.key === 'ArrowUp')) {
            ev.preventDefault();
            richOpenTypeMenu(picker);
            return;
        }
        if (ev.key === 'Enter' && ev.target && ev.target.isContentEditable) {
            ev.preventDefault();
            if (ev.shiftKey) {
                document.execCommand('insertText', false, '\n');
            } else {
                document.execCommand('insertHTML', false, '<br>');
            }
            var blockEl = ev.target.closest('.rich-block');
            if (blockEl && richState) {
                var block = richState.blocks[parseInt(blockEl.dataset.index, 10)];
                if (block) {
                    if (ev.target.dataset.cellId) {
                        var cell = richFindCell(block, ev.target.dataset.cellId);
                        if (cell) cell.html = ev.target.innerHTML;
                    } else if (ev.target.dataset.field === 'li') {
                        block.items[parseInt(ev.target.dataset.li, 10)] = ev.target.innerHTML;
                    } else if (ev.target.dataset.field === 'html') {
                        block.html = ev.target.innerHTML;
                    }
                }
            }
            richRefreshPreviewDebounced();
        }
    });

    host.addEventListener('paste', function (ev) {
        var el = ev.target;
        if (!el || !el.isContentEditable) return;
        var clip = ev.clipboardData || window.clipboardData;
        if (!clip) return;
        var text = clip.getData('text/plain');
        if (text == null) return;
        ev.preventDefault();
        var html = text.split('\n').map(rxEscHtml).join('<br>');
        document.execCommand('insertHTML', false, html);
    });

    host.addEventListener('change', function (ev) {
        var el = ev.target;
        if (!el || !el.closest) return;
        var blockEl = el.closest('.rich-block');
        if (!blockEl || !richState) return;
        var index = parseInt(blockEl.dataset.index, 10);
        var block = richState.blocks[index];
        if (!block) return;
        if (el.dataset.tableOpt) {
            if (['bordered', 'striped', 'compact'].indexOf(el.dataset.tableOpt) !== -1) {
                block[el.dataset.tableOpt] = !!el.checked;
                richRenderAll();
            }
            return;
        }
        if (el.dataset.tableCaption !== undefined) {
            block.caption = el.value;
            richRefreshPreviewDebounced();
            return;
        }
    });

    host.addEventListener('click', function (ev) {
        var target = ev.target;
        if (!target || !target.closest) return;
        var picker = target.closest('[data-block-picker],[data-alignment-picker]');
        if (picker) { richOpenTypeMenu(picker); return; }
        var blockAct = target.closest('[data-block-act]');
        if (blockAct) {
            var index = parseInt(blockAct.dataset.index, 10);
            var action = blockAct.dataset.blockAct;
            if (action === 'up' && index > 0) {
                var prev = richState.blocks[index - 1];
                richState.blocks[index - 1] = richState.blocks[index];
                richState.blocks[index] = prev;
                richSelectedCellId = null;
                richRenderAll();
            } else if (action === 'down' && index < richState.blocks.length - 1) {
                var next = richState.blocks[index + 1];
                richState.blocks[index + 1] = richState.blocks[index];
                richState.blocks[index] = next;
                richSelectedCellId = null;
                richRenderAll();
            } else if (action === 'del') {
                richState.blocks.splice(index, 1);
                if (!richState.blocks.length) richState.blocks.push({ type: 'p', html: '' });
                richSelectedCellId = null;
                richRenderAll();
            }
            return;
        }
        var listDel = target.closest('[data-list-del]');
        if (listDel) {
            var listIndex = parseInt(listDel.dataset.index, 10);
            var listBlock = richState.blocks[listIndex];
            if (listBlock && listBlock.type === 'list') {
                listBlock.items.splice(parseInt(listDel.dataset.listDel, 10), 1);
                if (!listBlock.items.length) listBlock.items.push('');
                richSelectedCellId = null;
                richRenderAll();
            }
            return;
        }
        var listAdd = target.closest('[data-list-add]');
        if (listAdd) {
            var addIndex = parseInt(listAdd.dataset.listAdd, 10);
            var addBlock = richState.blocks[addIndex];
            if (addBlock && addBlock.type === 'list') {
                addBlock.items.push('');
                richRenderAll();
            }
            return;
        }
        var tableAct = target.closest('[data-table-act]');
        if (tableAct && richState) {
            var tableIndex = parseInt(tableAct.dataset.tableIndex, 10);
            var tableBlock = richState.blocks[tableIndex];
            if (!tableBlock || tableBlock.type !== 'table') return;
            var tableAction = tableAct.dataset.tableAct;
            if (tableAction === 'add-row') {
                if (!rtAddRow(tableBlock)) { alert('حداکثر ۱۰۰ سطر مجاز است.'); return; }
                var addedRow = tableBlock.rows[tableBlock.rows.length - 1];
                if (addedRow && addedRow[0]) richSelectedCellId = addedRow[0].id;
                richRenderAll();
                return;
            }
            if (tableAction === 'add-col') {
                if (!rtAddCol(tableBlock)) { alert('حداکثر ۲۰ ستون مجاز است.'); return; }
                richRenderAll();
                return;
            }
            var selected = richFindCell(tableBlock, richSelectedCellId);
            if (!selected) {
                alert('ابتدا یک سلول را انتخاب کنید.');
                return;
            }
            if (tableAction === 'del-row') {
                if (tableBlock.rows.length <= 1) {
                    alert('حداقل یک سطر لازم است.');
                    return;
                }
                rtDeleteRow(tableBlock, selected._r);
                richSelectedCellId = null;
                richRenderAll();
            } else if (tableAction === 'del-col') {
                var occ = rtGrid(tableBlock);
                var cols = rtPad(occ);
                if (cols <= 1) {
                    alert('حداقل یک ستون لازم است.');
                    return;
                }
                rtDeleteCol(tableBlock, selected._c);
                richSelectedCellId = null;
                richRenderAll();
            }
            return;
        }
        var cellAct = target.closest('[data-cell-act]');
        if (cellAct && richState) {
            var cellIndex = parseInt(cellAct.dataset.tableIndex, 10);
            var cellBlock = richState.blocks[cellIndex];
            if (!cellBlock || cellBlock.type !== 'table') return;
            var cell = richFindCell(cellBlock, richSelectedCellId);
            if (!cell) {
                alert('ابتدا یک سلول را انتخاب کنید.');
                return;
            }
            var cellAction = cellAct.dataset.cellAct;
            if (cellAction === 'header') {
                cell.header = !cell.header;
                richRenderAll();
            } else if (cellAction === 'split') {
                if (!rtSplit(cellBlock, cell)) {
                    alert('این سلول قابل تفکیک نیست.');
                    return;
                }
                richSelectedCellId = null;
                richRenderAll();
            } else if (cellAction === 'merge-right' || cellAction === 'merge-down') {
                var outcome = rtMerge(cellBlock, cellAction === 'merge-right' ? 'right' : 'down');
                if (outcome !== 'ok') {
                    alert(outcome === 'no-cell' ? 'ابتدا یک سلول را انتخاب کنید.' : 'ادغام ممکن نیست؛ سلول مجاور باید هم‌ارتفاع یا هم‌عرض باشد.');
                    return;
                }
                richRenderAll();
            }
            return;
        }
        var cellEl = target.closest('td[data-cell-id],th[data-cell-id]');
        if (cellEl) richSelectCell(cellEl.dataset.cellId);
    });

    document.querySelectorAll('[data-rich-cmd]').forEach(function (btn) {
        btn.addEventListener('pointerdown', function (ev) { richRememberSelection(); ev.preventDefault(); });
        btn.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
        btn.addEventListener('click', function () { richApplyInline(btn.dataset.richCmd); });
    });

    document.querySelectorAll('[data-rich-block]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!richState) return;
            richState.blocks.push({ type: 'p', html: '' });
            richSelectedCellId = null;
            richRenderAll();
        });
    });

    var rtlToggle = document.getElementById('richRtl');
    if (rtlToggle) rtlToggle.addEventListener('change', function () { richRefreshPreview(); });
})();


(function () {
    var counter = document.getElementById('changeCount');
    var areas = document.querySelectorAll('#textForm textarea');
    var saveBtn = document.getElementById('textSaveBtn');
    function persianNum(n){ return String(n).replace(/\d/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'[+d]; }); }
    function refreshEdge() {
        var blocking = 0;
        areas.forEach(function (t) {
            if (!textEdgeIsRouting(t.dataset.key)) return;
            var bad = textEdgeInvalid(t.value);
            t.classList.toggle('edge-invalid', bad);
            var err = document.getElementById('edge-err-' + t.dataset.key);
            if (err) err.hidden = !bad;
            if (bad && t.value !== t.dataset.orig) blocking++;
        });
        if (saveBtn) saveBtn.disabled = blocking > 0;
        return blocking;
    }
    function updateCount() {
        var n = 0;
        areas.forEach(function(t){
            var companion = document.getElementById('rich-' + t.dataset.key);
            var richChanged = companion && companion.value !== companion.dataset.orig;
            if (t.value !== t.dataset.orig || richChanged) { t.classList.add('changed'); n++; }
            else t.classList.remove('changed');
        });
        counter.textContent = persianNum(n) + ' تغییر در انتظار ذخیره';
        refreshEdge();
    }
    refreshEdge();
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
        textForm.addEventListener('submit', function (ev) {
            if (refreshEdge() > 0) {
                ev.preventDefault();
                alert(TEXT_EDGE.message);
                return;
            }
            try {
                var payload = { version: 1, t: Object.create(null), rich: Object.create(null) };
                areas.forEach(function (t) {
                    var key = t.dataset.key;
                    var companion = document.getElementById('rich-' + key);
                    var richChanged = companion && companion.value !== companion.dataset.orig;
                    if (t.value === t.dataset.orig && !richChanged) return;
                    payload.t[key] = t.value;
                    payload.rich[key] = companion ? companion.value : '';
                });
                document.getElementById('textSavePayload').value = JSON.stringify(payload);
            } catch (error) {
                ev.preventDefault();
                alert('آماده‌سازی اطلاعات برای ذخیره انجام نشد؛ دوباره تلاش کنید.');
            }
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
