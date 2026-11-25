<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;
use RuntimeException;

final class Renewal
{
    public static function renew(array $context)
    {
        $order_id = $context['payment']['order_id'];
        $Payment_report = $context['payment']['report'];
        $steppay = $context['payment']['invoice_tag'];
        $Balance_id = $context['payment']['customer'];
        $rxCk = $context['payment']['checkout'];
        $rxToken = $context['fulfillment']['token'];
        $rxPhase = $context['fulfillment']['phase'];
        $rxBeforeState = $context['fulfillment']['before'];
        $rxTargetState = $context['fulfillment']['target'];
        $pdo =& $context['provider']['database'];
        $ManagePanel =& $context['provider']['panel'];
        $setting = $context['presentation']['settings'];
        $textbotlang =& $context['presentation']['language'];
        $keyboard =& $context['presentation']['keyboard'];
        $keyboardextendfnished =& $context['presentation']['renewal_keyboard'];
        $format_price_cart = $context['presentation']['price_display'];
        $paymentNote = $context['presentation']['payment_note'];
        $receipt = $context['presentation']['receipt'];
        $admin_ids = $context['reporting']['administrators'];
        $otherservice = $context['reporting']['extension'];
        $otherreport = $context['reporting']['general'];
        $errorreport = $context['reporting']['error'];

        $balanceformatsell = number_format(\Faoxima\Core\Database\Db::select("user", "Balance", "id", $Balance_id['id'], "select")['Balance'], 0);
        $partsdic = explode("%", $steppay[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->execute();
        $data_order = $stmt->fetch(PDO::FETCH_ASSOC);
        $service_other = $data_order;
        if ($service_other == false) {
            sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_error_contact_support'), $keyboard, 'HTML');
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'renewal_service_other_not_found', true);
        }
        $service_other = json_decode($service_other['value'], true);
        $codeproduct = \Faoxima\Services\Renewal\Plans::renewalNormalizeProductCode($service_other['code_product'] ?? '');
        $rxReservationSel = is_array($service_other['reservation'] ?? null) ? $service_other['reservation'] : [];
        $nameloc = false;
        try {
            $rxInvoiceStmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
            $rxInvoiceStmt->execute([':username' => $usernamepanel, ':id_user' => $Balance_id['id']]);
            $nameloc = $rxInvoiceStmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $nameloc = false;
        }
        if (!is_array($nameloc)) {
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $usernamepanel, "select");
        }
        $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = $data_order['price'];
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (agent = '{$Balance_id['agent']}' OR agent = 'all') AND code_product = '$codeproduct'");
            $stmt->execute([':loc' => (string) $nameloc['Service_location']]);
            $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!is_array($prodcut)) {
            $__refundPr = \Faoxima\Payments\Settlement\Refunds::paymentOnce($pdo, $Payment_report['id_order'], $Balance_id['id'], $Payment_report['price'], 'بازگشت وجه - محصول تمدید در دسترس نیست', (string)($nameloc['id_invoice'] ?? ''));
            if ($__refundPr === 'refunded') {
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_product_unavailable_refunded'), $keyboard, 'HTML');
            } elseif ($__refundPr === 'failed') {
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_product_unavailable_refund_failed'), $keyboard, 'HTML');
            }
            return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'renewal_product_unavailable', $__refundPr);
        }
        $fxOrderValue = json_decode((string) ($data_order['value'] ?? ''), true);
        if (is_array($fxOrderValue) && isset($fxOrderValue['pricing_snapshot']) && is_numeric($data_order['price'] ?? null)) {
            $prodcut['price_product'] = $data_order['price'];
        }
        $rxWasTestService = $nameloc['name_product'] == "سرویس تست";
        $dateacc = date('Y/m/d H:i:s');
        $rxStatements = [];
        if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($marzban_list_get)) {
            if ($rxPhase === 'reconcile') {
                return \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, 'national_stock_renewal_unverifiable', ['username' => (string) $nameloc['username']]);
            }
            if (!\Faoxima\Payments\Fulfillment\Settlement::prepare($pdo, $order_id, $rxToken, null, ['national_stock' => true])) {
                return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'fulfillment_prepare_failed', true);
            }
            $stockNew = method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockReserveForProduct') ? \Faoxima\Services\Fulfillment\Inventory::nmStockReserveForProduct($pdo, $marzban_list_get, $prodcut, $Balance_id['id'], $nameloc['id_invoice'], 'paid_extend_national_stock') : false;
            if (!is_array($stockNew) || (string)($stockNew['content'] ?? '') === '') {
                if (is_array($stockNew) && is_callable([\Faoxima\Services\Fulfillment\Inventory::class, 'nmStockReleaseReservation'])) \Faoxima\Services\Fulfillment\Inventory::nmStockReleaseReservation($pdo, $stockNew);
                $__refundSt = \Faoxima\Payments\Settlement\Refunds::paymentOnce($pdo, $Payment_report['id_order'], $Balance_id['id'], $Payment_report['price'], 'بازگشت وجه - موجودی انبار تمام شده', (string)($nameloc['id_invoice'] ?? ''));
                if ($__refundSt === 'refunded') {
                    sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_stock_empty_refunded'), $keyboard, 'HTML');
                } elseif ($__refundSt === 'failed') {
                    sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_stock_empty_refund_failed'), $keyboard, 'HTML');
                }
                return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'national_stock_empty', $__refundSt);
            }
            $rxStatements = \Faoxima\Orders\Checkout\Settlement::settlementStatements($rxCk, $order_id, $Balance_id['id'], -1, 'تمدید سرویس');
            \Faoxima\Core\Database\Db::update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
            \Faoxima\Core\Database\Db::update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
            \Faoxima\Core\Database\Db::update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
            \Faoxima\Core\Database\Db::update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
            \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
            \Faoxima\Core\Database\Db::update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
            \Faoxima\Core\Database\Db::update("invoice", "user_info", $stockNew['content'], "id_invoice", $nameloc['id_invoice']);
            try { \Faoxima\Core\Database\Db::update("invoice", "source_panel_code", $marzban_list_get['code_panel'], "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
            \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($nameloc['id_invoice'], array('volume_cycle', 'time_cycle'), array('volume', 'volume_report', 'time', 'time_report'));
            $invoiceNew = array_merge((array)$nameloc, ['name_product' => $prodcut['name_product'], 'price_product' => $prodcut['price_product'], 'Volume' => $prodcut['Volume_constraint'], 'Service_time' => $prodcut['Service_time'], 'time_sell' => time(), 'user_info' => $stockNew['content'], 'source_panel_code' => $marzban_list_get['code_panel']]);
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockDeliverConfig')) \Faoxima\Services\Fulfillment\StockPresentation::nmStockDeliverConfig($stockNew, $invoiceNew, '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
            $extend = ['status' => true, 'stock' => true];
        } else {
            $ManagePanel = \Faoxima\Services\Panels\PanelSetup::rxEnsureManagePanel();
            if (!is_object($ManagePanel)) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('DIRECT_PAYMENT_NO_MANAGE_PANEL', 'ManagePanel unavailable; extend aborted without side effects', ['id_order' => $order_id]);
                }
                return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'manage_panel_unavailable', true);
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            $Balance_Low_user = 0;
            $rxRenewMode = \Faoxima\Services\Renewal\Plans::renewalMode($marzban_list_get['Methodextend'] ?? '');
            if (\Faoxima\Services\Renewal\ReservationCriteria::reservationStoredSelection($rxReservationSel) !== null) {
                $rxRenewMode = \Faoxima\Services\Renewal\Plans::renewalMode('رزرو اشتراک');
            }
            $rxQueuedRenewal = is_array($rxRenewMode) && !empty($rxRenewMode['queued']) && (string) ($rxReservationSel['trigger'] ?? '') !== 'immediate';
            $rxPolicy = is_array($rxRenewMode) ? (string) ($rxRenewMode['policy'] ?? '') : '';
            $rxResetUsage = is_array($rxRenewMode) && !empty($rxRenewMode['reset_usage']);
            if (is_array($rxRenewMode) && !empty($rxRenewMode['queued'])) {
                $rxPolicy = (string) ($rxReservationSel['policy'] ?? 'reset_all');
                $rxResetUsage = !empty($rxReservationSel['reset_usage']) && \Faoxima\Services\Renewal\Plans::renewalPolicyAllowsUsageReset($rxPolicy);
            }
            $rxExtendOptions = [
                'source' => 'payment',
                'ref' => $order_id,
                'id_user' => $Balance_id['id'],
                'price' => $Payment_report['price'],
                'reservation' => array_merge($rxReservationSel, ['payment_ref' => $order_id]),
            ];
            $rxReconcilable = !$rxQueuedRenewal && is_array($rxRenewMode) && ($marzban_list_get['type'] ?? '') !== 'Manualsale'
                && (\Faoxima\Services\Fulfillment\Delivery::panelReconcilable($marzban_list_get) || ($marzban_list_get['type'] ?? '') === 'WGDashboard');
            $rxSnap = \Faoxima\Services\Fulfillment\ProviderState::snapshotFromDatauser($DataUserOut, (string) ($marzban_list_get['type'] ?? ''));
            $rxApply = true;
            $rxAfterState = null;
            if ($rxPhase === 'reconcile') {
                if ($rxQueuedRenewal) {
                    if (!\Faoxima\Payments\Fulfillment\Settlement::markApplying($pdo, $order_id, $rxToken)) {
                        return \Faoxima\Payments\Fulfillment\Claims::result('already_processing', 'ownership_lost');
                    }
                } elseif (!$rxReconcilable || !is_array($rxBeforeState) || !is_array($rxTargetState)) {
                    return \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, 'renewal_result_unverifiable', ['username' => (string) $nameloc['username']]);
                } else {
                    $rxClass = \Faoxima\Services\Fulfillment\ProviderState::classify($rxSnap, $rxBeforeState, $rxTargetState);
                    if ($rxClass === 'unknown') {
                        return \Faoxima\Payments\Fulfillment\Settlement::defer($pdo, $order_id, $rxToken, 'renewal_panel_read_failed: ' . (string) ($rxSnap['error'] ?? ''));
                    }
                    if ($rxClass === 'ambiguous') {
                        return \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, 'renewal_panel_state_ambiguous', ['username' => (string) $nameloc['username']]);
                    }
                    if ($rxClass === 'target') {
                        $rxApply = false;
                        $extend = ['status' => true, 'reconciled' => true];
                        $rxAfterState = \Faoxima\Services\Fulfillment\ProviderState::limits($rxSnap);
                        $ManagePanel->rxRenewalLocalSuccess($marzban_list_get, (string) $nameloc['username'], (string) $nameloc['id_invoice']);
                    } elseif (!\Faoxima\Payments\Fulfillment\Settlement::markApplying($pdo, $order_id, $rxToken)) {
                        return \Faoxima\Payments\Fulfillment\Claims::result('already_processing', 'ownership_lost');
                    }
                }
            } else {
                $rxBeforeState = null;
                $rxTargetState = null;
                if ($rxRenewMode === null) {
                    \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('paid_method', 'unknown_renewal_method', ['panel' => $marzban_list_get, 'username' => (string) $nameloc['username'], 'invoice_id' => (string) ($nameloc['id_invoice'] ?? ''), 'invoice_status' => $nameloc['Status'] ?? null, 'method' => (string) ($marzban_list_get['Methodextend'] ?? ''), 'volume_gb' => $prodcut['Volume_constraint'] ?? null, 'days' => $prodcut['Service_time'] ?? null, 'ref' => (string) $order_id, 'mutation' => 'none']);
                    return \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, 'unknown_renewal_method: ' . (string) ($marzban_list_get['Methodextend'] ?? ''), ['username' => (string) $nameloc['username']]);
                }
                if ($rxReconcilable) {
                    if (empty($rxSnap['ok'])) {
                        \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('paid_snapshot', 'panel_read_failed', ['panel' => $marzban_list_get, 'username' => (string) $nameloc['username'], 'invoice_id' => (string) ($nameloc['id_invoice'] ?? ''), 'invoice_status' => $nameloc['Status'] ?? null, 'method' => (string) ($marzban_list_get['Methodextend'] ?? ''), 'volume_gb' => $prodcut['Volume_constraint'] ?? null, 'days' => $prodcut['Service_time'] ?? null, 'snapshot' => $rxSnap, 'ref' => (string) $order_id, 'mutation' => 'none']);
                        return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'renewal_panel_read_failed', true);
                    }
                    if (!empty($rxSnap['exists'])) {
                        $rxPlan = \Faoxima\Services\Renewal\ProviderRenewal::renewalPlanForSnapshot($pdo, $rxSnap, $marzban_list_get, (string) $nameloc['username'], $rxPolicy, $rxResetUsage, $prodcut['Volume_constraint'], $prodcut['Service_time'], (string) $rxRenewMode['method'], null, ['stage' => 'paid_plan', 'invoice_id' => (string) ($nameloc['id_invoice'] ?? ''), 'ref' => (string) $order_id]);
                        if (empty($rxPlan['ok'])) {
                            $extend = ['status' => false, 'code' => (string) ($rxPlan['reason'] ?? 'renewal_refused'), 'no_mutation' => true, 'plan' => $rxPlan, 'msg' => 'renewal refused: ' . (string) ($rxPlan['reason'] ?? '')];
                            $rxApply = false;
                        } else {
                            $rxBeforeState = array_merge(\Faoxima\Services\Fulfillment\ProviderState::limits($rxSnap), [
                                'status' => (string) ($rxSnap['status'] ?? ''),
                                'username' => (string) $nameloc['username'],
                                'panel' => (string) $marzban_list_get['name_panel'],
                                'remaining' => $rxPlan['before_remaining'],
                                'method' => $rxPlan['method'],
                                'policy' => $rxPlan['policy'],
                            ]);
                            $rxTargetState = $rxPlan;
                        }
                    }
                }
                if ($rxApply && !\Faoxima\Payments\Fulfillment\Settlement::prepare($pdo, $order_id, $rxToken, $rxBeforeState, $rxTargetState)) {
                    return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'fulfillment_prepare_failed', true);
                }
            }
            if ($rxApply) {
                try {
                    $extend = \Faoxima\Services\Renewal\ProviderRenewal::apply(
                        $ManagePanel, $marzban_list_get, $prodcut, (string) $nameloc['username'], $rxTargetState, $rxExtendOptions
                    );
                } catch (Throwable $rxPanelError) {
                    return \Faoxima\Payments\Fulfillment\Settlement::reconcileLater($pdo, $order_id, $rxToken, 'renewal_panel_exception: ' . $rxPanelError->getMessage());
                }
            }
            if (\Faoxima\Services\Renewal\ProviderRenewal::renewalIsPending($extend)) {
                return \Faoxima\Payments\Fulfillment\Settlement::reconcileLater($pdo, $order_id, $rxToken, 'renewal_result_unverified: ' . (string) ($extend['operation_id'] ?? '') . ' ' . (is_scalar($extend['msg'] ?? null) ? (string) $extend['msg'] : ''));
            }
            if (in_array((string) ($extend['code'] ?? ''), ['renewal_in_progress', 'renewal_lock_unavailable', 'renewal_schema_unavailable'], true)
                || (($extend['code'] ?? '') === 'panel_read_failed' && !empty($extend['no_mutation']))) {
                return \Faoxima\Payments\Fulfillment\Settlement::defer($pdo, $order_id, $rxToken, 'renewal_deferred: ' . (string) $extend['code']);
            }
            if (($extend['status'] ?? false) == false && empty($extend['no_mutation']) && is_array($rxBeforeState) && is_array($rxTargetState)) {
                $rxPostSnap = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($ManagePanel, (string) $nameloc['Service_location'], (string) $nameloc['username'], (string) ($marzban_list_get['type'] ?? ''));
                $rxClass = \Faoxima\Services\Fulfillment\ProviderState::classify($rxPostSnap, $rxBeforeState, $rxTargetState);
                if ($rxClass === 'target') {
                    $extend = ['status' => true, 'reconciled' => true];
                    $rxAfterState = \Faoxima\Services\Fulfillment\ProviderState::limits($rxPostSnap);
                    $ManagePanel->rxRenewalLocalSuccess($marzban_list_get, (string) $nameloc['username'], (string) $nameloc['id_invoice']);
                } else {
                    return \Faoxima\Payments\Fulfillment\Settlement::reconcileLater($pdo, $order_id, $rxToken, 'renewal_result_unknown: ' . (is_scalar($extend['msg'] ?? null) ? (string) $extend['msg'] : ''));
                }
            }
            if (!empty($extend['status']) && empty($extend['queued']) && is_array($rxTargetState) && $rxAfterState === null) {
                $rxVerify = is_array($extend['rx_renewal'] ?? null) ? $extend['rx_renewal'] : null;
                if ($rxVerify === null || empty($rxVerify['verified']) || !is_array($rxVerify['after'] ?? null)) {
                    return \Faoxima\Payments\Fulfillment\Settlement::reconcileLater($pdo, $order_id, $rxToken, 'renewal_result_unverified');
                }
                $rxAfterState = $rxVerify['after'];
            }
        if ($extend['status'] == false) {
            $__refundEx = \Faoxima\Payments\Settlement\Refunds::paymentOnce($pdo, $Payment_report['id_order'], $Balance_id['id'], $Payment_report['price'], 'بازگشت وجه - خطا در تمدید سرویس', (string)($nameloc['id_invoice'] ?? ''));
            if ($__refundEx === 'duplicate') {
                return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'renewal_failed', $__refundEx);
            }
            if ($__refundEx === 'refunded') {
                sendmessage($Balance_id['id'], $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_failed_refunded', ['amount' => \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price'])]), $keyboard, 'HTML');
            } else {
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_failed_refund_failed'), $keyboard, 'HTML');
            }
            $rxTopupExtendMsg = \Faoxima\Services\Renewal\RenewalText::rxRenewalFailureText($extend, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_renewal_support_error', "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید"));
            if (($extend['code'] ?? '') === 'manual_stock_empty') {
                $rxTopupExtendMsg = "❌ موجودی انبار برای این محصول تمام شده است.";
            }
            $extend['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extend['msg'] ?? null, $extend['detail'] ?? null);
            $textreports = "
        خطای تمدید سرویس
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>کد پیگیری پرداخت : {$order_id}</blockquote>
<blockquote>کد خطا : " . htmlspecialchars((string) ($extend['code'] ?? ''), ENT_QUOTES, 'UTF-8') . "</blockquote>
<blockquote>دلیل خطا : {$extend['msg']}</blockquote>";
            sendmessage($nameloc['id_user'], $rxTopupExtendMsg, null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'renewal_failed', $__refundEx);
        }
            $rxStatements = array_merge($rxStatements, \Faoxima\Orders\Checkout\Settlement::settlementStatements($rxCk, $order_id, $Balance_id['id'], -1, 'تمدید سرویس'));
        }
        $rxCompletion = \Faoxima\Payments\Fulfillment\Settlement::complete($pdo, $order_id, $rxToken, ['operation' => 'getextenduser', 'panel' => $extend, 'target' => $rxTargetState ?? null], $rxStatements);
        if ($rxCompletion !== 'completed') {
            return \Faoxima\Payments\Fulfillment\Settlement::completionOutcome($pdo, $order_id, $rxToken, $rxCompletion);
        }
        if ($rxWasTestService) {
            \Faoxima\Core\Database\Db::update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
            \Faoxima\Core\Database\Db::update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        } elseif ($codeproduct !== 'custom_volume' && \Faoxima\Services\Renewal\ProviderRenewal::renewalIsAppliedVerified($extend, $rxAfterState ?? null)) {
            \Faoxima\Services\Renewal\ProviderRenewal::renewalSyncInvoiceName($nameloc['id_invoice'] ?? '', $prodcut['name_product'] ?? '');
        }

        \Faoxima\Orders\Discounts\DiscountRules::logSale($pdo, [
            'id_user' => $Balance_id['id'],
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'Service_location' => $nameloc['Service_location'],
            'kind' => 'renewal',
            'name_product' => $prodcut['name_product'] ?? null,
            'amount' => $Payment_report['price'],
            'source' => 'bot',
        ]);
        \Faoxima\Core\Database\Db::update("service_other", "output", json_encode($extend), "id", $data_order['id']);
        \Faoxima\Core\Database\Db::update("service_other", "status", "paid", "id", $data_order['id']);
        $partsdic = explode("_", $Balance_id['Processing_value_four']);
        if ($partsdic[0] == "dis") {
            $__discountUseLegacy = true;
            if (is_callable([\Faoxima\Orders\Discounts\SellDiscounts::class, 'discountMarkUsedAtomic'])) {
                $__discountMark = \Faoxima\Orders\Discounts\SellDiscounts::discountMarkUsedAtomic($pdo, $partsdic[1], $Balance_id['id'], 'sell');
                $__discountUseLegacy = empty($__discountMark['ok']) && (string)($__discountMark['reason'] ?? '') === 'db_unavailable';
            }
            if ($__discountUseLegacy) {
                $SellDiscountlimit = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
                $value = intval($SellDiscountlimit['usedDiscount']) + 1;
                \Faoxima\Core\Database\Db::update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
                $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
                $stmt->bindParam(':id_user', $Balance_id['id']);
                $stmt->bindParam(':code', $partsdic[1]);
                $stmt->execute();
            }
            $text_report = "⭕️ یک کاربر با نام کاربری @{$Balance_id['username']}  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'parse_mode' => 'HTML',
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $keyboardextendfnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => "backorder"],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        if ($Balance_id['agent'] == "f") {
            $valurcashbackextend = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
        } else {
            $valurcashbackextend = json_decode(\Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$Balance_id['agenr']];
        }
        $renewCashbackEligible = !is_callable([\Faoxima\Cashback\Eligibility::class, 'shopCashbackEligible'])
            || \Faoxima\Cashback\Eligibility::shopCashbackEligible($pdo, "chashbackextend", $Balance_id['register'] ?? null, "getextenduser", $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
        if ($renewCashbackEligible && intval($valurcashbackextend) != 0) {
            $result = (int) floor(($prodcut['price_product'] * $valurcashbackextend) / 100);
            if (\Faoxima\Cashback\Credits::creditOnce($pdo, $Payment_report['id_order'] ?? '', $Balance_id['id'], $result, 'chashbackextend', 'هدیه بازگشت وجه تمدید سرویس') === 'credited') {
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_cashback_gift', ['amount' => \Faoxima\Core\Support\Formatting::rxFormatToman($result)]), null, 'HTML');
            }
        }
        $priceproductformat = number_format($prodcut['price_product']);
        if (!empty($extend['queued'])) {
            $rxQueuedSuccessTpl = (string) ($extend['reservation']['confirmation'] ?? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_confirmed'));
            $textextend = \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_queued_summary', ['service' => $usernamepanel, 'product' => $prodcut['name_product'], 'price' => $priceproductformat], ['confirmation' => $rxQueuedSuccessTpl]);
        } else {
            $textextend = \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_success', ['service' => $usernamepanel, 'product' => $prodcut['name_product'], 'price' => $priceproductformat]);
            if (is_array($rxBeforeState ?? null) && is_array($rxAfterState ?? null)) {
                $textextend .= "\n" . \Faoxima\Services\Renewal\RenewalText::rxRenewalResultText($rxBeforeState, $rxAfterState);
            } elseif (($rxOutcomeText = \Faoxima\Services\Renewal\RenewalText::rxRenewalOutcomeText($extend)) !== '') {
                $textextend .= "\n" . $rxOutcomeText;
            }
            $rxSameLinkNote = \Faoxima\Services\Renewal\RenewalText::rxRenewalSameLinkNote($extend, (string) ($marzban_list_get['type'] ?? ''), $rxAfterState ?? null);
            if ($rxSameLinkNote !== '') {
                $textextend .= "\n\n" . $rxSameLinkNote;
            }
        }
        sendmessage($Balance_id['id'], $textextend, $keyboardextendfnished, 'HTML');
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_score_earned_2', "📌شما 2 امتیاز جدید کسب کردید."), null, 'html');
            $scorenew = $Balance_id['score'] + 2;
            \Faoxima\Core\Database\Db::update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $timejalali = jdate('Y/m/d H:i:s');
        $text_report = "📣 جزئیات تمدید اکانت در ربات شما ثبت شد .

<blockquote>▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code></blockquote>
<blockquote>▫️نام کاربری کاربر : @{$Balance_id['username']}</blockquote>
<blockquote>▫️نام کاربری کانفیگ :$usernamepanel</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$nameloc['Service_location']}</blockquote>
<blockquote>▫️نام محصول : {$prodcut['name_product']}</blockquote>
<blockquote>▫️حجم محصول : {$prodcut['Volume_constraint']}</blockquote>
<blockquote>▫️زمان محصول : {$prodcut['Service_time']}</blockquote>
<blockquote>▫️مبلغ تمدید : $priceproductformat تومان</blockquote>
<blockquote>▫️موجودی قبل از خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
            \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('renewal', [
                'user_id'    => $Balance_id['id'],
                'amount'     => $prodcut['name_product'],
                'price'      => $priceproductformat,
                'panel_name' => $marzban_list_get['name_panel'] ?? '',
                'category'   => $prodcut['category'] ?? '',
            ], $setting);
        }
        if (empty($extend['queued']) && (string) ($nameloc['Status'] ?? '') !== 'disablebyadmin') {
            \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        }
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {

            $rxFmtBalanceBeforeExtend = \Faoxima\Core\Support\Formatting::rxFormatToman($Balance_id['Balance']);
            $textconfrom = "✅ پرداخت تایید شده
🔋 تمدید سرویس
🪪 نام کاربری کانفیگ : $usernamepanel
🛍 نام محصول : {$prodcut['name_product']}
🌏 نام لوکیشن : {$nameloc['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل تمدید  : {$rxFmtBalanceBeforeExtend}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}

";
            ReceiptMessage::updateReceipt($receipt, $textconfrom);
        }
        return \Faoxima\Payments\Fulfillment\Claims::result('completed');
    }
}
